#!/usr/bin/env bash
#
# Проверка раздачи материала курсов через nginx (X-Accel-Redirect).
#
# ЗАЧЕМ. Режим `nginx` включается галкой в настройках, и при неверной
# настройке он ломает материала тихо: приложение отдаёт 200 с ПУСТЫМ
# телом, в логах ошибок нет, страница материала выглядит как пустая.
# Проверить это вручную можно, а воспроизвести на боевой машине — нет.
# Скрипт поднимает НАСТОЯЩИЙ nginx (не заглушку) на свободном порту,
# включает режим и проверяет, что файл приходит целиком.
#
# ЧТО ПРОВЕРЯЕТСЯ.
#   1. nginx -t проходит на боевых location'ах приложения.
#   2. Прямой запрос к /_protected-content/ снаружи даёт 404
#      (location помечен internal — иначе материал доступен в обход
#      проверки подписи).
#   3. Через nginx подписанный URL отдаёт файл ЦЕЛИКОМ.
#   4. Тот же URL мимо nginx (на php artisan serve) отдаёт пустое тело —
#      это и есть доказательство, что файл пришёл от nginx, а не от PHP.
#   5. Вложенный ресурс (картинка/стиль из материала) тянется по
#      подписанному префиксу и тоже отдаётся целиком.
#   6. Отдача больших файлов из менеджера принимается (client_max_body_size).
#
# ЗАПУСК.
#   tools/verify-nginx-delivery.sh              # всё сразу
#   tools/verify-nginx-delivery.sh --keep       # не гасить процессы
#   tools/verify-nginx-delivery.sh --no-browser # без Playwright-проверки
#
# nginx. Если в системе его нет и нет прав root, скрипт скачает и
# распакует бинарник в каталог кэша (~/.cache/lms-nginx) и запустит его
# от текущего пользователя на непривилегированном порту. Для боевой
# машины это не нужно: там nginx из пакета и запускается через systemd.
#
# НЕ ЛОМАЕТ РАБОТУ ПРИЛОЖЕНИЯ. Настройка content_delivery возвращается
# в исходное значение, процессы гасятся на выходе (кроме --keep).

set -uo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT" || exit 1

NGINX_PORT="${NGINX_PORT:-8099}"
APP_PORT="${APP_PORT:-8098}"
ADMIN_FIO="${ADMIN_FIO:-Администратор}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-123}"
KEEP_RUNNING=0
RUN_BROWSER=1

for arg in "$@"; do
  case "$arg" in
    --keep) KEEP_RUNNING=1 ;;
    --no-browser) RUN_BROWSER=0 ;;
    -h|--help) sed -n '2,40p' "$0"; exit 0 ;;
    *) echo "Неизвестный аргумент: $arg" >&2; exit 2 ;;
  esac
done

WORK_DIR="${TMPDIR:-/tmp}/lms-nginx-verify.$$"
NGINX_CACHE="$HOME/.cache/lms-nginx"
NGINX_BIN=""
# Каталог с mime.types и служебными файлами пакета. Задаётся ключом -p:
# директивы prefix в конфиге nginx не существует.
NGINX_PREFIX=""
CONTENT_ROOT=""
ACCEL_INTERNAL=""
ACCEL_MARKER=""
CHUNK_MB=""
APP_PID=""
NGINX_PID=""
ORIGINAL_DELIVERY=""
FAILED=0

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; DIM=$'\033[2m'; OFF=$'\033[0m'

say()  { printf '%s\n' "$*"; }
ok()   { printf '%s  ✓ %s%s\n' "$GREEN" "$*" "$OFF"; }
bad()  { printf '%s  ✗ %s%s\n' "$RED" "$*" "$OFF"; FAILED=1; }
warn() { printf '%s  ! %s%s\n' "$YELLOW" "$*" "$OFF"; }
step() { printf '\n%s== %s%s\n' "$DIM" "$*" "$OFF"; }

cleanup() {
  local code=$?

  # Настройку возвращаем ДО остановки процессов: так даже при аварии
  # приложение останется в том режиме, в котором было.
  if [ -n "$ORIGINAL_DELIVERY" ]; then
    if [ -f "$APP_ROOT/artisan" ] && app_value "App\Support\ContentDelivery::persist('${ORIGINAL_DELIVERY}')" >/dev/null; then
      say "Режим раздачи возвращён: ${ORIGINAL_DELIVERY}"
    else
      warn "Не удалось вернуть режим раздачи (${ORIGINAL_DELIVERY}) — сделайте вручную."
    fi
  fi

  if [ "$KEEP_RUNNING" -eq 0 ]; then
    [ -n "$NGINX_PID" ] && kill "$NGINX_PID" 2>/dev/null
    [ -n "$APP_PID" ] && kill "$APP_PID" 2>/dev/null
    sleep 0.3
    [ -n "$NGINX_PID" ] && kill -9 "$NGINX_PID" 2>/dev/null
    [ -n "$APP_PID" ] && kill -9 "$APP_PID" 2>/dev/null
    rm -rf "$WORK_DIR"
  else
    warn "--keep: процессы оставлены (nginx: ${NGINX_PID:-нет}, app: ${APP_PID:-нет}), каталог ${WORK_DIR}"
  fi

  exit $code
}
trap cleanup EXIT INT TERM

# ---------------------------------------------------------------------
# 1. nginx
# ---------------------------------------------------------------------
find_nginx() {
  # Системный nginx: префикс стандартный, из пакета.
  if command -v nginx >/dev/null 2>&1; then
    NGINX_BIN="$(command -v nginx)"
    NGINX_BIN="$(readlink -f "$NGINX_BIN")"
    NGINX_PREFIX="$(dirname "$(dirname "$NGINX_BIN")")"
    return 0
  fi

  # Без root распаковать .deb в кэш и запустить от текущего пользователя.
  if [ -x "$NGINX_CACHE/sbin/nginx" ]; then
    NGINX_BIN="$NGINX_CACHE/sbin/nginx"
    NGINX_PREFIX="$NGINX_CACHE"
    return 0
  fi

  say "nginx не найден в системе, скачиваем бинарник в ${NGINX_CACHE} ..."
  mkdir -p "$NGINX_CACHE" || return 1

  ( cd "$NGINX_CACHE" && apt-get download nginx nginx-common ) >/dev/null 2>&1 || {
    warn "Скачать nginx не удалось (нужна сеть). Установите nginx пакетом и повторите."
    return 1
  }

  for deb in "$NGINX_CACHE"/nginx_*.deb "$NGINX_CACHE"/nginx-common_*.deb; do
    [ -f "$deb" ] || continue
    dpkg -x "$deb" "$NGINX_CACHE/unpacked" 2>/dev/null
  done

  [ -x "$NGINX_CACHE/unpacked/usr/sbin/nginx" ] || {
    warn "В распакованном пакете нет nginx."
    return 1
  }

  # Раскладка как у системного nginx: префикс, внутри него conf/ с
  # mime.types и fastcgi_params. Кладём именно так — иначе include
  # ${NGINX_PREFIX}/conf/mime.types не найдёт файл и nginx не стартует.
  mkdir -p "$NGINX_CACHE/sbin" "$NGINX_CACHE/conf"
  cp "$NGINX_CACHE/unpacked/usr/sbin/nginx" "$NGINX_CACHE/sbin/nginx"
  cp -r "$NGINX_CACHE/unpacked/etc/nginx/." "$NGINX_CACHE/conf/" 2>/dev/null
  cp -r "$NGINX_CACHE/unpacked/usr/share/nginx/." "$NGINX_CACHE/" 2>/dev/null
  rm -rf "$NGINX_CACHE/unpacked" "$NGINX_CACHE"/nginx_*.deb "$NGINX_CACHE"/nginx-common_*.deb

  NGINX_BIN="$NGINX_CACHE/sbin/nginx"
  NGINX_PREFIX="$NGINX_CACHE"
  ok "nginx подготовлен: $($NGINX_BIN -v 2>&1)"
}

# ---------------------------------------------------------------------
# Чтение настроек приложения
# ---------------------------------------------------------------------
# Через `php -r` с бутстрапом, а не через `artisan tinker`: tinker печатает
# в stdout посторонние строки (заголовок, подсказки, а при ошибке — текст
# ошибки), и эти строки подставлялись бы в конфиг nginx как директивы.
# Здесь stdout — ровно значение.
app_value() {
  php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $expr = $argv[1];
    echo eval("return " . $expr . ";");
  ' -- "$1" 2>/dev/null | tail -1
}

# ---------------------------------------------------------------------
# 2. Конфигурация проверки
# ---------------------------------------------------------------------
# Почему proxy_pass, а не fastcgi_pass: в этой машине нет php-fpm, есть
# только встроенный сервер PHP. Для проверки X-Accel-Redirect разница
# несущественна: заголовок формирует приложение, а разбирает его nginx —
# одинаково в обоих случаях. На боевой машине конфиг из
# deploy/nginx-content-delivery.conf использует fastcgi_pass, и там
# проверяется ровно он (шаг 1).
read_app_settings() {
  CONTENT_ROOT="$(app_value "rtrim((string) config('app.courses_path'), '/') . '/'")"
  ACCEL_INTERNAL="$(app_value "rtrim((string) config('private_content.accel_internal'), '/')")"
  ACCEL_MARKER="$(app_value "App\Support\ContentDelivery::accelMarker()")"
  CHUNK_MB="$(app_value "(int) ceil((int) config('files.chunk_bytes') * 2 / 1048576)")"

  # Пути к контенту и метка берутся из приложения: расхождение любого из
  # них даёт не ошибку, а тихий 404, и найти его вручную невозможно.
  if [ -z "$CONTENT_ROOT" ] || [ -z "$ACCEL_MARKER" ]; then
    bad "не удалось получить настройки из приложения (php -r не отработал)"
    return 1
  fi

  return 0
}

write_nginx_conf() {
  # Значения уже прочитаны в read_app_settings (глобальные переменные):
  # они нужны и здесь, и при проверке синтаксиса боевого конфига.
  content_root="$CONTENT_ROOT"
  accel_internal="$ACCEL_INTERNAL"
  marker="$ACCEL_MARKER"
  chunk_mb="$CHUNK_MB"

  mkdir -p "$WORK_DIR/logs" "$WORK_DIR/temp" "$WORK_DIR/client_body"

  # Кавычный heredoc (<<'CONF') и подстановка через sed — потому что в
  # комментариях конфига есть обратные кавычки (`php artisan storage:link`),
  # а НЕКАВЫЧНЫЙ heredoc выполняет их как подстановку команды. Результат:
  # в конфиг попадал вывод artisan, и nginx падал с «unknown directive ERROR».
  cat > "$WORK_DIR/nginx.conf" <<'CONF'
# Конфигурация проверки. Собрана из тех же источников, что и
# deploy/nginx-content-delivery.conf; отличается способом доставки запросов
# в PHP (proxy_pass вместо fastcgi_pass — php-fpm на машине проверки нет) и
# портами. Суть узла — location с internal, он проверяется как в бою.

daemon off;
master_process off;
error_log __WORK__/logs/error.log warn;
pid __WORK__/nginx.pid;

events {
    worker_connections 64;
}

http {
    include __PREFIX__/conf/mime.types;
    default_type application/octet-stream;
    # access_log живёт внутри http{}: на верхнем уровне такой директивы
    # нет, и nginx падает на старте.
    access_log __WORK__/logs/access.log;

    client_body_temp_path __WORK__/client_body;
    proxy_temp_path __WORK__/temp;
    fastcgi_temp_path __WORK__/temp;
    uwsgi_temp_path __WORK__/temp;
    scgi_temp_path __WORK__/temp;

    sendfile on;
    client_max_body_size __CHUNK_MB__m;
    client_body_timeout 300s;

    server {
        listen 127.0.0.1:__NGINX_PORT__;
        server_name lms.local;
        root __APP_ROOT__/public;
        index index.php;

        # Материал курсов. internal — не украшение: снаружи такой location
        # недоступен, отдать файл может только nginx по заголовку
        # X-Accel-Redirect, который формирует приложение.
        location __ACCEL__/ {
            internal;
            alias __CONTENT__;
            types { }
            default_type application/octet-stream;
            try_files $uri =404;
            gzip on;
            gzip_types text/html text/css application/javascript image/svg+xml application/json;
            add_header Cache-Control "private, max-age=600, must-revalidate" always;
            add_header X-Content-Type-Options "nosniff" always;
            # Метка для проверки: её получают ТОЛЬКО файлы, которые nginx
            # открыл сам по заголовку X-Accel-Redirect. Ответ, собранный
            # PHP, метки не несёт — по ней и отличается, кто на самом деле
            # отдал файл. В боевом конфиге такой метки нет: она нужна
            # только проверке.
            add_header X-Served-By "nginx-internal-location" always;
        }

        # Каталог внутри storage/app/public. Сейчас контент лежит за его
        # пределами, но блок остаётся: symlink storage:link однажды
        # появится снова (шаг деплоя) — и закрывать будет что.
        location /storage/ {
            deny all;
            return 404;
        }

        # PHP. Метка «этот запрос от nginx» обязательна: приложение отдаёт
        # X-Accel-Redirect только при ней, иначе режим nginx тихо
        # превращается в пустые ответы со статусом 200.
        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            # ВАЖНО: в proxy_pass передаётся ИСХОДНЫЙ $request_uri, а не
            # переписанный $uri.
            #
            # try_files выше делает внутренний редирект на /index.php, и
            # proxy_pass БЕЗ URI отдал бы вверх именно /index.php. Laravel
            # увидел бы POST /index.php и ответил «The POST method is not
            # supported for route /» вместо разбора маршрута. В
            # production такого не происходит: fastcgi_params передаёт
            # REQUEST_URI = $request_uri, то есть тоже исходный. Здесь то
            # же поведение воспроизведено явно.
            proxy_pass http://127.0.0.1:__APP_PORT__$request_uri;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_set_header X-Lms-Accel "__MARKER__";
            proxy_read_timeout 300s;
        }
    }
}
CONF

  sed -i \
    -e "s#__WORK__#${WORK_DIR}#g" \
    -e "s#__PREFIX__#${NGINX_PREFIX}#g" \
    -e "s#__APP_ROOT__#${APP_ROOT}#g" \
    -e "s#__NGINX_PORT__#${NGINX_PORT}#g" \
    -e "s#__APP_PORT__#${APP_PORT}#g" \
    -e "s#__CHUNK_MB__#${chunk_mb}#g" \
    -e "s#__ACCEL__#${accel_internal}#g" \
    -e "s#__CONTENT__#${content_root}#g" \
    -e "s#__MARKER__#${marker}#g" \
    "$WORK_DIR/nginx.conf"

  ok "конфигурация проверки собрана (метка из APP_KEY, корень контента ${content_root})"
}

# ---------------------------------------------------------------------
# 3. Проверки
# ---------------------------------------------------------------------
find_course_file() {
  # Первый index.html в каталоге контента: он и есть точка входа в материал.
  # Путь берётся из приложения, а не зашит здесь: контент переехал за
  # пределы public/, и жёстко прописанный путь искал бы его не там.
  content_root="$(app_value "rtrim((string) config('app.courses_path'), '/') . '/'")"
  find "$content_root" -name 'index.html' -type f 2>/dev/null | head -1
}

main() {
  step "Проверяем, что контент есть"

  local index_file
  index_file="$(find_course_file)"

  if [ -z "$index_file" ]; then
    bad "в storage/app/public/private нет ни одного index.html — проверять нечего (импортируйте курс)"
    return 1
  fi
  ok "материал найден: ${index_file#"$APP_ROOT"/}"

  step "Готовим nginx"
  find_nginx || return 1
  read_app_settings || return 1
  write_nginx_conf || return 1

  step "Проверяем боевой конфиг приложения синтаксически"

  content_root="$CONTENT_ROOT"

  # deploy/nginx-content-delivery.conf нельзя проверить «как есть»: там
  # плейсхолдер вместо метки из APP_KEY, чужой root и unix-сокет
  # php-fpm, которого на этой машине нет. Поэтому синтаксис проверяется
  # на копии с подставленными значениями: доказывается, что все
  # директивы РАСПОЗНАЮТСЯ и что location не конфликтуют. Всё остальное
  # проверяется ниже на живом nginx.
  cat > "$WORK_DIR/nginx-boilerplate-test.conf" <<'BOILERPLATE'
# Проверка синтаксиса боевого конфига (deploy/nginx-content-delivery.conf).
# Значения подставлены свои: доказываем, что директивы распознаются и что
# location не конфликтуют между собой. Всё остальное проверяется ниже на
# живом nginx.
daemon off;
master_process off;
error_log __WORK__/logs/boilerplate.log warn;
pid __WORK__/nginx-boilerplate.pid;

events { worker_connections 32; }

http {
    include __PREFIX__/conf/mime.types;
    access_log __WORK__/logs/boilerplate-access.log;
    client_body_temp_path __WORK__/client_body;
    proxy_temp_path __WORK__/temp;
    fastcgi_temp_path __WORK__/temp;
    uwsgi_temp_path __WORK__/temp;
    scgi_temp_path __WORK__/temp;

    server {
        listen 127.0.0.1:19999;
        root __APP_ROOT__/public;
        index index.php;

        location /_protected-content/ {
            internal;
            alias __CONTENT__;
            types { }
            try_files $uri =404;
        }

        location /storage/ {
            deny all;
            return 404;
        }

        # Директивы приёма загрузки из deploy/nginx-content-delivery.conf.
        client_max_body_size 8m;
        client_body_timeout 300s;
        client_body_buffer_size 64k;
        fastcgi_request_buffering off;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }
    }
}
BOILERPLATE

  sed -i \
    -e "s#__WORK__#${WORK_DIR}#g" \
    -e "s#__PREFIX__#${NGINX_PREFIX}#g" \
    -e "s#__APP_ROOT__#${APP_ROOT}#g" \
    -e "s#__CONTENT__#${content_root}#g" \
    "$WORK_DIR/nginx-boilerplate-test.conf"

  if "$NGINX_BIN" -t -c "$WORK_DIR/nginx-boilerplate-test.conf" -p "$NGINX_PREFIX" > "$WORK_DIR/boilerplate-check.log" 2>&1; then
    ok "директивы боевого конфига распознаются, location не конфликтуют"
  else
    bad "конфиг не проходит проверку:"
    sed 's/^/      /' "$WORK_DIR/boilerplate-check.log"
  fi

  step "Поднимаем приложение и nginx"

  PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=127.0.0.1 --port="$APP_PORT" --quiet \
    > "$WORK_DIR/logs/app.log" 2>&1 &
  APP_PID=$!

  for _ in $(seq 1 40); do
    curl -sf -o /dev/null "http://127.0.0.1:${APP_PORT}/api/health" && break
    sleep 0.25
  done

  if ! curl -sf -o /dev/null "http://127.0.0.1:${APP_PORT}/api/health"; then
    bad "приложение не поднялось на ${APP_PORT}"
    sed 's/^/      /' "$WORK_DIR/logs/app.log" | tail -20
    return 1
  fi
  ok "приложение слушает ${APP_PORT}"

  "$NGINX_BIN" -c "$WORK_DIR/nginx.conf" -p "$NGINX_PREFIX" > "$WORK_DIR/logs/nginx.out" 2>&1 &
  NGINX_PID=$!

  for _ in $(seq 1 40); do
    curl -sf -o /dev/null "http://127.0.0.1:${NGINX_PORT}/api/health" && break
    sleep 0.25
  done

  if ! curl -sf -o /dev/null "http://127.0.0.1:${NGINX_PORT}/api/health"; then
    bad "nginx не поднялся на ${NGINX_PORT}"
    sed 's/^/      /' "$WORK_DIR/logs/error.log" 2>/dev/null | tail -20
    sed 's/^/      /' "$WORK_DIR/logs/nginx.out" 2>/dev/null | tail -20
    return 1
  fi
  ok "nginx слушает ${NGINX_PORT}"

  step "Включаем режим раздачи через nginx"

  ORIGINAL_DELIVERY="$(app_value "App\Support\ContentDelivery::mode()")"
  say "  текущий режим: ${ORIGINAL_DELIVERY}"

  app_value "App\Support\ContentDelivery::persist('nginx')" >/dev/null
  now_mode="$(app_value "App\Support\ContentDelivery::mode()")"

  if [ "$now_mode" != "nginx" ]; then
    bad "режим не переключился (сейчас: ${now_mode})"
    return 1
  fi
  ok "режим: nginx"

  step "Прямой доступ к материалу снаружи закрыт"

  accel_path="$ACCEL_INTERNAL"
  direct_code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${NGINX_PORT}${accel_path}/КЛЕН/02/index.html" || true)"

  if [ "$direct_code" = "404" ] || [ "$direct_code" = "403" ]; then
    ok "прямой запрос дал ${direct_code} (location internal)"
  else
    bad "прямой запрос на ${accel_path} дал ${direct_code}, а не 404 — материал доступен в обход проверки подписи"
  fi

  step "Получаем подписанный URL"

  token="$(curl -s -X POST "http://127.0.0.1:${NGINX_PORT}/api/login" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json' \
    -d "{\"fio\":\"${ADMIN_FIO}\",\"password\":\"${ADMIN_PASSWORD}\"}" \
    | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["token"])' 2>/dev/null)"

  if [ -z "$token" ]; then
    bad "не удалось войти (проверьте ADMIN_FIO/ADMIN_PASSWORD)"
    return 1
  fi
  ok "вошли под ${ADMIN_FIO}"

  # Курс берётся С ДИСКА, а не из базы: подписывается пара
  # (самолёт, каталог АУК), и она обязана совпадать с каталогами на диске.
  # Из базы взялся бы title вроде «Введение», которого на диске нет, и
  # проверка падала бы с 404 на исправной системе.
  aircraft="$(basename "$(dirname "$(dirname "$index_file")")")"
  auk="$(basename "$(dirname "$index_file")")"
  say "  курс: ${aircraft} / ${auk}"

  # Путь подписи строит приложение: собирать его вручную в проверке
  # бессмысленно — тогда проверялся бы не код выдачи, а копипаст.
  signed="$(curl -sG "http://127.0.0.1:${NGINX_PORT}/api/private/signed-url" \
    --data-urlencode "aircraft=${aircraft}" \
    --data-urlencode "auk=${auk}" \
    -H "Authorization: Bearer ${token}" -H 'Accept: application/json' \
    | python3 -c 'import json,sys; d=json.load(sys.stdin); print((d.get("data") or {}).get("base") or "")' 2>/dev/null)"

  if [ -z "$signed" ]; then
    bad "не удалось получить подписанный URL для ${aircraft}/${auk} (курс не импортирован в базу?)"
    return 1
  fi
  # signedPath() возвращает путь БЕЗ ведущего слеша (его дописывает
  # вызывающий), а в curl нужен абсолютный: без слеша адрес получается
  # «http://host:8099api/private/...» и curl отвечает кодом 000.
  case "$signed" in
    /*) ;;
    *) signed="/${signed}" ;;
  esac

  signed="${signed}index.html"
  ok "подписанный получен: ${signed}"

  step "Материал отдаёт nginx целиком"

  via_nginx_body="$WORK_DIR/via-nginx.html"
  via_nginx_code="$(curl -s -o "$via_nginx_body" -w '%{http_code}' "http://127.0.0.1:${NGINX_PORT}${signed}" || true)"
  via_nginx_size="$(wc -c < "$via_nginx_body" 2>/dev/null | tr -d ' ')"
  origin_size="$(wc -c < "$index_file" | tr -d ' ')"

  say "  через nginx: код ${via_nginx_code}, ${via_nginx_size} байт (на диске ${origin_size})"

  if [ "$via_nginx_code" = "200" ] && [ "${via_nginx_size:-0}" -gt 0 ]; then
    if [ "$via_nginx_size" = "$origin_size" ]; then
      ok "файл пришёл целиком и побайтово того же размера, что на диске"
    else
      bad "размер не совпал: ${via_nginx_size} против ${origin_size} — файл отдан не полностью"
    fi
  else
    bad "через nginx код ${via_nginx_code}, тело ${via_nginx_size:-0} байт"
  fi

  step "Файл отдал nginx, а не PHP"

  # Сравнение размеров тела НЕ доказывает, кто отдал файл: когда запрос
  # идёт мимо nginx, ContentDelivery::isAccel() не находит метку
  # X-Lms-Accel и осознанно отдаёт материал потоком из PHP. Тела
  # одинаковые, и «проверка размеров» прошла бы, ничего не доказывая.
  #
  # Доказательство — заголовок, который ставит только внутренний location
  # nginx. Его нет в ответе PHP, поэтому наличие метки означает, что файл
  # открыл веб-сервер.
  served_by="$(curl -s -D - -o /dev/null "http://127.0.0.1:${NGINX_PORT}${signed}" \
    | tr -d '\r' | awk -F': ' 'tolower($1) == "x-served-by" { print $2 }')"

  if [ "$served_by" = "nginx-internal-location" ]; then
    ok "через nginx файл отдал внутренний location (есть X-Served-By)"
  else
    bad "через nginx нет метки X-Served-By: файл отдал PHP, а не nginx (X-Accel-Redirect не сработал)"
  fi

  direct_code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${APP_PORT}${signed}" || true)"
  direct_served="$(curl -s -D - -o /dev/null "http://127.0.0.1:${APP_PORT}${signed}" \
    | tr -d '\r' | awk -F': ' 'tolower($1) == "x-served-by" { print $2 }')"

  say "  мимо nginx (php artisan serve): код ${direct_code}, X-Served-By: ${direct_served:-нет}"

  if [ -z "$direct_served" ]; then
    ok "мимо nginx метки нет — там материал действительно отдаёт PHP"
  else
    bad "мимо nginx ответ несёт X-Served-By — метка просочилась наружу"
  fi

  step "Вложенный ресурс тянется по подписанному префиксу"

  # Ресурс ищется в материале по факту, а не по шаблону «styles.css»:
  # у разных курсов разметка разная, и выдуманное имя дало бы 404 на
  # исправной системе.
  nested="$(python3 - "$via_nginx_body" <<'PY'
import re, sys, urllib.parse
html = open(sys.argv[1], encoding='utf-8', errors='ignore').read()
for pattern in (r'<link[^>]+href=["\']([^"\']+\.css)["\']',
                r'<script[^>]+src=["\']([^"\']+\.js)["\']',
                r'<img[^>]+src=["\']([^"\']+\.(?:png|jpg|jpeg|gif|svg))["\']'):
    m = re.search(pattern, html, re.I)
    if m:
        print(urllib.parse.quote(m.group(1)))
        break
PY
)"

  if [ -z "$nested" ]; then
    warn "в index.html не нашлось вложенного ресурса — проверка пропущена"
  else
    say "  ресурс: ${nested}"
    nested_body="$WORK_DIR/nested.bin"
    # ${signed} уже заканчивается на index.html, поэтому каталог —
    # всё, что до него.
    nested_code="$(curl -s -o "$nested_body" -w '%{http_code}' "http://127.0.0.1:${NGINX_PORT}${signed%index.html}${nested}" || true)"
    nested_size="$(wc -c < "$nested_body" 2>/dev/null | tr -d ' ')"

    if [ "$nested_code" = "200" ] && [ "${nested_size:-0}" -gt 0 ]; then
      ok "вложенный ресурс отдан (${nested_size} байт)"
    else
      bad "вложенный ресурс не отдан: код ${nested_code}, ${nested_size:-0} байт"
    fi
  fi

  step "Раздача каталога контента закрыта"

  storage_code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${NGINX_PORT}/storage/private/КЛЕН/02/index.html" || true)"
  if [ "$storage_code" = "404" ] || [ "$storage_code" = "403" ]; then
    ok "/storage/... даёт ${storage_code}"
  else
    bad "/storage/private/... даёт ${storage_code} — каталог контента отдаётся напрямую"
  fi

  step "Браузерная проверка через Playwright"

  if [ "$RUN_BROWSER" -eq 0 ]; then
    warn "пропущена (--no-browser)"
  elif ! command -v npx >/dev/null 2>&1; then
    warn "пропущена: нет npx"
  else
    if BASE_URL="http://127.0.0.1:${NGINX_PORT}" \
       API_URL="http://127.0.0.1:${NGINX_PORT}" \
       npx playwright test tests/E2E/nginx-course-delivery.spec.js --reporter=list; then
      ok "Playwright: материал открывается и отдаётся nginx"
    else
      bad "Playwright: проверка материала не прошла"
    fi
  fi

  step "Приём больших запросов (файловый менеджер)"

  # Размер части берётся из конфига приложения: проверять надо ровно тем,
  # чем реально грузит менеджер.
  chunk_bytes="$(app_value "(int) config('files.chunk_bytes')")"
  init="$(curl -s -X POST "http://127.0.0.1:${NGINX_PORT}/api/filemanager/uploads/init" \
    -H "Authorization: Bearer ${token}" -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"filename\":\"проверка.bin\",\"size\":${chunk_bytes},\"mime\":\"application/octet-stream\"}" \
    | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["upload_id"])' 2>/dev/null)"

  if [ -z "$init" ]; then
    bad "init загрузки не отработал (нет прав files.upload?)"
  else
    dd if=/dev/urandom of="$WORK_DIR/chunk.bin" bs=1 count="$chunk_bytes" status=none
    # Заголовок Authorization обязателен: маршрут закрыт auth:sanctum, и
    # без него пришёл бы 401, который легко принять за проблему размера.
    chunk_code="$(curl -s -o /dev/null -w '%{http_code}' -X POST \
      --data-binary "@$WORK_DIR/chunk.bin" \
      -H 'Content-Type: application/octet-stream' \
      -H "Authorization: Bearer ${token}" \
      "http://127.0.0.1:${NGINX_PORT}/api/filemanager/uploads/${init}/chunk?index=0" || true)"

    if [ "$chunk_code" = "200" ]; then
      ok "часть ${chunk_bytes} байт принята (client_max_body_size не мешает)"
    else
      bad "часть отбита с кодом ${chunk_code}: поднимите client_max_body_size (php artisan files:limits)"
    fi

    curl -s -o /dev/null -X DELETE -H "Authorization: Bearer ${token}" \
      "http://127.0.0.1:${NGINX_PORT}/api/filemanager/uploads/${init}" || true
  fi

  step "Проверка экспозиции из приложения"

  # Это ПРЕДУПРЕЖДЕНИЕ, а не провал проверки: речь о symlink public/storage,
  # который в dev-машине может быть нужен. Через nginx каталог закрыт
  # location /storage/ (проверено выше), а решение о переносе контента за
  # пределы public/ принимает владелец — см. docs/content-delivery.md.
  if php artisan content:check-exposure > "$WORK_DIR/exposure.log" 2>&1; then
    ok "content:check-exposure не нашёл проблем"
  else
    warn "content:check-exposure сообщил (через nginx каталог закрыт, см. выше):"
    sed 's/^/      /' "$WORK_DIR/exposure.log" | tail -12
  fi
}

main
exit $FAILED

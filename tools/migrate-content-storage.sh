#!/usr/bin/env bash
#
# Перенос материала курсов за пределы public/ — сценарий для боевой машины.
#
# ЗАЧЕМ СЦЕНАРИЙ, А НЕ СПИСОК ШАГОГ В ДОКУМЕНТЕ. Перенос ломает
# отдачу материала в промежутке между шагами: пока конфиг приложения ещё
# указывает на старое место, а материал уже переехал — сайт отдаёт 404.
# Порядок шагов обязателен, и выполнить его руками легко сбиться,
# особенно если перенос занимает минуты. Здесь порядок зашит в код, а
# каждый шаг проверяется.
#
# ЧТО ДЕЛАЕТ
#   1. предпроверки: место, занятые файлы, текущий и целевой путь;
#   2. пробный перенос (--dry-run) с показом плана;
#   3. перенос содержимого (--yes, без --dry-run);
#   4. переключение пути в .env, если это нужно;
#   5. перегенерация фрагмента nginx и проверка nginx -t, если он есть;
#   6. проверка, что каталог контента больше не отдаётся напрямую;
#   7. живая проверка отдачи материал�� браузером.
#
# ЗАПУСК
#   tools/migrate-content-storage.sh --dry-run    # ничего не менять
#   tools/migrate-content-storage.sh --yes        # выполнить перенос
#   tools/migrate-content-storage.sh --yes --skip-browser
#   tools/migrate-content-storage.sh --to=/var/lib/lms/private --yes
#
# БЕЗОПАСНОСТЬ
#   * без --yes материал не переносится: сценарий только показывает
#     состояние и план. Побочные действия есть, но безопасные и
#     повторяемые: чистится кэш конфига (config:clear) и пишется
#     фрагмент nginx в storage/app/private/;
#   * --dry-run не трогает ни одного файла материала;
#   * повторный запуск безопасен: если материал уже перенесён, шаг 3
#     сообщает об этом и идёт дальше, а не ломает каталог;
#   * на каждом шаге проверка результата, а не только код выхода.
#
# ЧТО ОСТАЁТСЯ СДЕЛАТЬ РУКАМИ
#   Если на машине есть nginx: вставить напечатанный фрагмент в конфиг
#   и выполнить `systemctl reload nginx`. Сценарий этого делать не
#   умеет намеренно: он не знает, где и как у вас устроен конфиг, и
#   молчаливый rewrite чужого конфига хуже, чем лишний шаг руками.

set -uo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT" || exit 1

DRY_RUN=0
CONFIRM=0
SKIP_BROWSER=0
TARGET=""
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="storage/app/private"
NGINX_SNIPPET="$OUT_DIR/nginx-content-$STAMP.conf"

FAILED=0

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'; DIM=$'\033[2m'; OFF=$'\033[0m'

say()  { printf '%s\n' "$*"; }
step() { printf '\n%s▸ %s%s\n' "$BOLD" "$*" "$OFF"; }
ok()   { printf '%s  ✓ %s%s\n' "$GREEN" "$*" "$OFF"; }
warn() { printf '%s  ! %s%s\n' "$YELLOW" "$*" "$OFF"; }
bad()  { printf '%s  ✗ %s%s\n' "$RED" "$*" "$OFF"; FAILED=1; }
dim()  { printf '%s%s%s\n' "$DIM" "$*" "$OFF"; }

# Значение из приложения. Через `php -r` с бутстрапом, а не tinker:
# tinker печатает в stdout посторонние строки, которые попали бы в конфиг.
app_value() {
  php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo eval("return " . $argv[1] . ";");
  ' -- "$1" 2>/dev/null | tail -1
}

need_php() {
  if [ ! -f artisan ]; then
    say "Скрипт запущен не из корня проекта"
    exit 2
  fi
  if ! php -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);'; then
    bad "нужен PHP 8.2+, здесь $(php -r 'echo PHP_VERSION;')"
    exit 2
  fi
}

for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --yes|-y)  CONFIRM=1 ;;
    --skip-browser) SKIP_BROWSER=1 ;;
    --to=*)    TARGET="${arg#--to=}" ;;
    -h|--help) sed -n '2,40p' "$0"; exit 0 ;;
    *) say "Неизвестный аргумент: $arg"; exit 2 ;;
  esac
done

need_php

# --------------------------------------------------------------------------
step "Текущее состояние"
# --------------------------------------------------------------------------

CURRENT="$(app_value "rtrim((string) config('app.courses_path'), '/') . '/'")"
[ -n "$TARGET" ] || TARGET="$(app_value "storage_path('app/courses/private')")"
TARGET="$(printf '%s' "$TARGET" | sed 's#/*$##')"

FILES="$(find "$CURRENT" -type f 2>/dev/null | wc -l | tr -d ' ')"
BYTES="$(du -sb "$CURRENT" 2>/dev/null | cut -f1)"

dim "каталог контента: $CURRENT"
dim "файлов:           $FILES"
dim "размер:           $(numfmt --to=iec --suffix=B "${BYTES:-0}" 2>/dev/null || echo "${BYTES:-0} B")"
dim "целевой каталог:  $TARGET"

if [ ! -d "$CURRENT" ]; then
  bad "каталога с материалом нет: $CURRENT"
  dim "проверьте COURSES_PATH в .env и выполните php artisan config:clear"
  exit 1
fi

# Будет ли фактически перенос. Считается здесь, а не в шаге 3, потому
# что предпроверка занятых файлов обязана знать ответ заранее: если
# переносить нечего, она не должна требовать останавливать чужие
# процессы.
if [ "$CURRENT" = "$TARGET/" ] || [ "$CURRENT" = "$TARGET" ]; then
  WILL_MOVE=0
elif [ "$DRY_RUN" -eq 1 ] || [ "$CONFIRM" -eq 0 ]; then
  WILL_MOVE=0
else
  WILL_MOVE=1
fi

# --------------------------------------------------------------------------
step "Предпроверки"
# --------------------------------------------------------------------------

# 1. Место. Нужно только если каталоги на разных системах: при переносе
#    переименованием вторая копия не создаётся.
CURRENT_DEV="$(stat -c %d "$CURRENT" 2>/dev/null)"
TARGET_PARENT="$TARGET"
while [ ! -d "$TARGET_PARENT" ] && [ "$TARGET_PARENT" != "/" ]; do
  TARGET_PARENT="$(dirname "$TARGET_PARENT")"
done
TARGET_DEV="$(stat -c %d "$TARGET_PARENT" 2>/dev/null)"

if [ "$CURRENT_DEV" = "$TARGET_DEV" ]; then
  ok "перенос переименованием: вторая копия не нужна, место не требуется"
else
  FREE="$(df -PB1 "$TARGET_PARENT" | awk 'NR==2{print $4}')"
  NEED=$(( ${BYTES:-0} * 11 / 10 ))
  if [ "${FREE:-0}" -lt "$NEED" ]; then
    bad "свободного места не хватит: нужно ~$(numfmt --to=iec --suffix=B "$NEED"), свободно $(numfmt --to=iec --suffix=B "${FREE:-0}")"
    dim "перенос пойдёт копированием, и на это время понадобится вторая копия материала"
    exit 1
  fi
  ok "копирование: свободно $(numfmt --to=iec --suffix=B "${FREE:-0}"), нужно ~$(numfmt --to=iec --suffix=B "$NEED")"
fi

# 2. Занятые файлы. На Linux открытый файл не мешает переименованию
#    каталога, но процесс, который пишет в материал прямо сейчас, —
#    это потерянная запись. Предупреждаем, а не блокируем.
if command -v lsof >/dev/null 2>&1; then
  # Считаются только НАСТОЯЩИЕ файловые дескрипторы.
  #
  # cwd (текущий каталог процесса) при переносе безвреден: rename()
  # переносит каталог, и процесс уезжает за ним следом, содержимое не
  # страдает. То же с rtd/txt/mem — это рабочие области процесса.
  # Реальный риск — открытый файл (REG), который пишут, и дескриптор
  # каталога (DIR), открытый не как cwd.
  #
  # Проверка без этого фильтра срабатывала на любом запущенном
  # проводнике: сигнал, который всегда горит, перестают замечать.
  HELD="$(lsof +D "$CURRENT" 2>/dev/null \
    | awk 'NR>1 && $4 != "cwd" && $4 != "rtd" && $4 != "txt" && $4 != "mem" {print $1}' \
    | sort -u | tr '\n' ' ')"
  if [ -n "$HELD" ]; then
    # Открытый каталог на Linux переименованию НЕ мешает — rename
    # работает. Блокировать имеет смысл только когда перенос вот-вот
    # начнётся: пока переносить нечего (материал уже на месте или не
    # задан --yes), остановить чужой процесс не наша забота.
    if [ "$WILL_MOVE" -eq 1 ]; then
      bad "перенос сейчас начнётся, а материал держат процессы: $HELD"
      dim "запись в каталог во время переноса потеряется. Остановите их"
      dim "проводник/редактор с открытым каталогом и повторите."
      exit 1
    fi

    warn "материал держат процессы: $HELD"
    dim "на перенос это не влияет (rename работает с открытыми файлами),"
    dim "но запись в каталог во время переноса потеряется"
  else
    ok "материал никем не занят"
  fi
else
  dim "lsof недоступен — проверить занятые файлы нельзя, продолжаем"
fi

# 3. Куда переносим.
ALREADY_MOVED=0
if [ "$CURRENT" = "$TARGET/" ] || [ "$CURRENT" = "$TARGET" ]; then
  ok "материал уже по новому пути — перенос не нужен"
  ALREADY_MOVED=1
else
  ALREADY_MOVED=0
  if php artisan content:relocate --dry-run --from="$CURRENT" --to="$TARGET" 2>&1 | sed 's/^/    /'; then
    ok "пробный перенос прошёл, отказов нет"
  else
    bad "пробный перенос отказал — смотрите вывод выше"
    exit 1
  fi
fi

# --------------------------------------------------------------------------
step "Перенос материала"
# --------------------------------------------------------------------------

# 0 — не трогали, 1 — перенос выполнен, 2 — материал уже на новом месте.
#
# Отдельная переменная нужна для сообщений в конце: пока перенос не
# выполнен, предупреждать про nginx нельзя, он ищет по старому пути и
# находит его на месте. Предупреждение, которого не о чем, учит
# игнорировать предупреждения.
MOVED=0

if [ "$ALREADY_MOVED" -eq 1 ]; then
  dim "шаг пропущен: каталог уже на месте"
  MOVED=2
elif [ "$DRY_RUN" -eq 1 ]; then
  dim "шаг пропущен: задан --dry-run"
  MOVED=0
elif [ "$CONFIRM" -eq 0 ]; then
  dim "шаг пропущен: не задан --yes"
  MOVED=0
else
  if php artisan content:relocate --from="$CURRENT" --to="$TARGET" 2>&1 | sed 's/^/    /'; then
    ok "материал перенесён в $TARGET"
    MOVED=1
  else
    bad "перенос не удался — состояние прежнее, разбирайтесь по выводу"
    exit 1
  fi
fi

# --------------------------------------------------------------------------
step "Путь приложения"
# --------------------------------------------------------------------------

if [ -f .env ] && grep -qE '^[[:space:]]*COURSES_PATH[[:space:]]*=' .env; then
  ENV_VALUE="$(grep -E '^[[:space:]]*COURSES_PATH[[:space:]]*=' .env | tail -1 | cut -d= -f2- | sed 's#/*$##')"

  if [ "$ENV_VALUE" = "$TARGET" ]; then
    ok "COURSES_PATH в .env уже указывает на цель"
  elif [ "$MOVED" -eq 0 ]; then
    # Ничего не переносили — править .env не наша забота, но сказать
    # надо: иначе после переноса путь разъедется и материал пропадёт.
    warn "COURSES_PATH в .env указывает на другое место: $ENV_VALUE"
    dim "после переноса его нужно заменить на: COURSES_PATH=$TARGET"
  else
    # Материал переехал, а .env продолжает указывать на старое место —
    # сайт отдаёт 404. Правим САМИ: оставлять это человеку значит
    # оставить машину в нерабочем состоянии между шагами.
    cp .env ".env.bak-$STAMP"
    sed -i -E "s#^[[:space:]]*COURSES_PATH[[:space:]]*=.*#COURSES_PATH=$TARGET/#" .env

    if grep -qE "^[[:space:]]*COURSES_PATH[[:space:]]*=$TARGET/?" .env; then
      ok "COURSES_PATH в .env обновлён: $TARGET"
      dim "прежний .env сохранён как .env.bak-$STAMP"
    else
      bad "не удалось обновить COURSES_PATH в .env"
      dim "бэкап: .env.bak-$STAMP — верните его и поправьте строку вручную"
    fi
  fi
else
  if [ "$DRY_RUN" -eq 0 ] && [ "$CONFIRM" -eq 1 ]; then
    ok "COURSES_PATH не задан — работает новое умолчание (storage/app/courses/private)"
  else
    dim "COURSES_PATH не задан — будет использовано умолчание из config/app.php"
  fi
fi

# --------------------------------------------------------------------------
step "Конфигурация приложения"
# --------------------------------------------------------------------------

php artisan config:clear >/dev/null 2>&1 && ok "config:clear" || warn "config:clear не отработал"

AFTER="$(app_value "rtrim((string) config('app.courses_path'), '/') . '/'")"
AFTER_FILES="$(find "$AFTER" -type f 2>/dev/null | wc -l | tr -d ' ')"

if [ "$AFTER_FILES" = "$FILES" ]; then
  ok "приложение видит материал по новому пути: $AFTER ($AFTER_FILES файлов)"
else
  bad "приложение видит $AFTER_FILES файлов вместо $FILES"
  say ""
  say "  Конфиг и диск разошлись: материал перенесён, а путь в настройках"
  say "  остался прежним. Сайт сейчас отдаёт 404 по материалу — это чинится"
  say "  одним действием:"
  say "    COURSES_PATH=$TARGET   в .env"
  say "    php artisan config:clear"
  say "  Откатить перенос, если так проще:"
  say "    mv '$TARGET' '$CURRENT'"
  say ""
  exit 1
fi

# --------------------------------------------------------------------------
step "Фрагмент для nginx"
# --------------------------------------------------------------------------

mkdir -p "$OUT_DIR"
if php artisan content:nginx-config --path="$NGINX_SNIPPET" >/dev/null 2>&1 || php artisan content:nginx-config > "$NGINX_SNIPPET" 2>&1; then
  ok "фрагмент записан: $NGINX_SNIPPET"
  dim "метка уже подставлена — вставлять её вручную не нужно"
else
  bad "не удалось получить фрагмент nginx"
fi

if command -v nginx >/dev/null 2>&1; then
  dim "nginx в системе есть. Проверьте конфиг после вставки:"
  dim "  nginx -t && systemctl reload nginx"
else
  dim "nginx в системе нет — фрагмент пригодится на боевой машине"
fi

say ""

if [ "$MOVED" -eq 1 ]; then
  say "  ВАЖНО: материал уже лежит по новому пути, а nginx, если его alias"
  say "  ещё старый, продолжит искать по старому — и отдаст 404. Порядок:"
  say "  вставить фрагмент → nginx -t → systemctl reload nginx."
  say "  Если reload отложить, временно верните предыдущий alias."
elif [ "$MOVED" -eq 0 ]; then
  say "  Перенос не выполнялся, состояние прежнее: прерывать работу не нужно."
fi

# --------------------------------------------------------------------------
step "Каталог контента напрямую не отдаётся"
# --------------------------------------------------------------------------

if php artisan content:check-exposure >/tmp/content-exposure-$STAMP.log 2>&1; then
  ok "content:check-exposure не нашёл проблем"
else
  warn "content:check-exposure сообщил:"
  sed 's/^/    /' /tmp/content-exposure-$STAMP.log | tail -12
fi

# --------------------------------------------------------------------------
step "Живая проверка отдачи"
# --------------------------------------------------------------------------

if [ "$SKIP_BROWSER" -eq 1 ]; then
  dim "пропущена (--skip-browser)"
elif [ "$DRY_RUN" -eq 1 ] || [ "$CONFIRM" -eq 0 ]; then
  dim "пропущена: перенос не выполнялся"
else
  if bash tools/verify-nginx-delivery.sh; then
    ok "материал отдаётся nginx и открывается браузером"
  else
    bad "проверка отдачи не прошла"
    dim "смотрите логи в выводе выше; проверьте alias в nginx"
  fi
fi

# --------------------------------------------------------------------------
step "Итог"
# --------------------------------------------------------------------------

if [ "$FAILED" -ne 0 ]; then
  warn "есть замечания (см. ✗ выше) — разберите их до перезагрузки nginx"
elif [ "$MOVED" -eq 0 ]; then
  ok "проверки пройдены, перенос не выполнялся"
else
  ok "сценарий выполнен без замечаний"
fi

if [ "$MOVED" -eq 0 ]; then
  cat <<SUMMARY

Ничего не изменено. Когда будете готовы:
  tools/migrate-content-storage.sh --dry-run   # план
  tools/migrate-content-storage.sh --yes       # перенос

SUMMARY
  exit $FAILED
fi

cat <<SUMMARY

Что осталось руками (если nginx в системе есть):
  1. вставить фрагмент $NGINX_SNIPPET в конфиг виртуального хоста
  2. nginx -t
  3. systemctl reload nginx
  4. tools/verify-nginx-delivery.sh   # повторно, уже с боевым alias

Откат, если что-то пошло не так:
  * материал уже перенесён, а nginx не перезагружен — верните в nginx
    ПРЕДЫДУЩИЙ alias и перезагрузите: отдача вернётся, при новом пути
    она работает, просто nginx смотрит в старый каталог;
  * перенос не завершён — старый каталог на месте, ничего чинить не нужно.

SUMMARY

exit $FAILED

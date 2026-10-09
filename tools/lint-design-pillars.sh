#!/usr/bin/env bash
#
# Скрипт-линтер «столпов» дизайн-системы (Фаза 0 плана редизайна).
#
# ЗАЧЕМ. Проект живёт в двух эпохах дизайна: часть страниц сделана на
# системе u-card/PageHeader, а девять ключевых страниц — на наследии
# v2 (elevation-12, синие v-toolbar внутри карточек, фиксированные
# инлайновые ширины, hex-цвета вместо токенов). Пока старые страницы
# не переписаны, новые правила нужны как гард от регрессий: нельзя
# добавлять НОВЫЙ код в старом стиле.
#
# ЧТО ПРОВЕРЯЕТСЯ (только resources/js/Pages/**):
#   1. elevation-12        — материал-тень 2019 года, конфликтует с flat-системой.
#   2. style="width:...    — фиксированная ширина ломает адаптив;
#                           ширина = класс из tokens (--w-form-*).
#   3. hex-цвета (#abc)    — обходят токены, темнеют неправильно.
#   4. <v-toolbar>         — внутри карточек форм; шапка страницы = PageHeader.
#
# ИСКЛЮЧЕНИЯ. Файлы, которые ещё не мигрированы, перечислены в
# LEGACY_FILES ниже: для них нарушения считаются предупреждениями,
# а не ошибками. Когда файл переписан — уберите его из списка.
#
# ЗАПУСК.
#   tools/lint-design-pillars.sh            # проверка, код выхода 0/1

set -uo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT" || exit 1

GREEN=$'\033[32m'; RED=$'\033[31m'; YELLOW=$'\033[33m'; DIM=$'\033[2m'; OFF=$'\033[0m'
FAILED=0
FIX=0

for arg in "$@"; do
  case "$arg" in
    --fix) FIX=1 ;;
    -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
    *) echo "Неизвестный аргумент: $arg" >&2; exit 2 ;;
  esac
done

# Файлы в старом стиле, которые ещё предстоит мигрировать (Фази 2–3).
# Пути относительно resources/js/Pages/.
LEGACY_FILES=(
  # --- Фаза 3.1: Auth-экраны ---
  "Login.vue"
  "Register.vue"
  "User/UserChpass.vue"
  # --- Фаза 2.3.3: Банк вопросов ---
  "Gift/QuestionItem.vue"
  "Gift/ExamineItem.vue"
  # --- Фаза 2.3.2: Конструктор курса ---
  "Course/RegisterCourse.vue"
  "Course/UpdateCourse.vue"
  "Course/AddClass.vue"
  "CourseItem.vue"
  # --- Фаза 3.4: Просмотр курса ---
  "CourseManifest.vue"
  # --- Фаза 3.6: Каталог / Файловый менеджер / Календарь ---
  "EventCalendar.vue"
  "Filemanager/FileManager.vue"
  # --- Прочие ---
  "Group/GroupLearning.vue"
  "User/UserCourse.vue"
)

is_legacy() {
  local file="$1" base
  base="$(basename "$file")"
  local legacy
  for legacy in "${LEGACY_FILES[@]}"; do
    [[ "$base" == "$(basename "$legacy")" ]] && return 0
  done
  return 1
}

PAGES_DIR="resources/js/Pages"
[[ -d "$PAGES_DIR" ]] || { echo "Каталог $PAGES_DIR не найден" >&2; exit 2; }

VIOLATIONS=0
WARNINGS=0

check() {
  local pattern="$1" label="$2" file="$3" line="$4" snippet="$5"
  if is_legacy "$file"; then
    printf '%s  ! %s:%s  %s: %s%s\n' "$YELLOW" "${file#resources/js/Pages/}" "$line" "$label" "$snippet" "$OFF"
    WARNINGS=$((WARNINGS + 1))
  else
    printf '%s  ✗ %s:%s  %s: %s%s\n' "$RED" "${file#resources/js/Pages/}" "$line" "$label" "$snippet" "$OFF"
    VIOLATIONS=$((VIOLATIONS + 1))
  fi
}

step() { printf '\n%s== %s%s\n' "$DIM" "$*" "$OFF"; }
ok()   { printf '%s  ✓ %s%s\n' "$GREEN" "$*" "$OFF"; }
bad()  { printf '%s  ✗ %s%s\n' "$RED" "$*" "$OFF"; FAILED=1; }
warn() { printf '%s  ! %s%s\n' "$YELLOW" "$*" "$OFF"; }

step "Проверка столпов дизайн-системы в $PAGES_DIR"

while IFS= read -r -d '' file; do
  # 1. elevation-12
  while IFS=: read -r line _; do
    [[ -n "$line" ]] && check "" "elevation-12" "$file" "$line" "$(sed -n "${line}p" "$file" | tr -s ' ' | cut -c1-70)"
  done < <(grep -n 'elevation-12' "$file" 2>/dev/null | cut -d: -f1)

  # 2. style="width: (фиксированная ширина)
  while IFS=: read -r line _; do
    [[ -n "$line" ]] && check "" 'style="width:' "$file" "$line" "$(sed -n "${line}p" "$file" | tr -s ' ' | cut -c1-70)"
  done < <(grep -n 'style="width:' "$file" 2>/dev/null | cut -d: -f1)

  # 3. hex-цвета
  while IFS=: read -r line _; do
    [[ -n "$line" ]] && check "" "hex-цвет" "$file" "$line" "$(sed -n "${line}p" "$file" | tr -s ' ' | cut -c1-70)"
  done < <(grep -nE '#[0-9a-fA-F]{3,6}' "$file" 2>/dev/null | cut -d: -f1)

  # 4. <v-toolbar> внутри карточек форм
  while IFS=: read -r line _; do
    [[ -n "$line" ]] && check "" "<v-toolbar" "$file" "$line" "$(sed -n "${line}p" "$file" | tr -s ' ' | cut -c1-70)"
  done < <(grep -n '<v-toolbar' "$file" 2>/dev/null | cut -d: -f1)
done < <(find "$PAGES_DIR" -name '*.vue' -print0)

step "Итог"

if [[ "$VIOLATIONS" -eq 0 && "$WARNINGS" -eq 0 ]]; then
  ok "нарушений нет — столпы соблюдены"
elif [[ "$VIOLATIONS" -eq 0 ]]; then
  ok "новых нарушений нет"
  warn "предупреждений в файлах-наследии: $WARNINGS (список — LEGACY_FILES в этом скрипте)"
else
  bad "нарушений в новых файлах: $VIOLATIONS"
  warn "предупреждений в файлах-наследии: $WARNINGS"
fi

exit "$FAILED"

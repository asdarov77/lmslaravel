#!/usr/bin/env python3
"""
Перевод хардкод-подписей в шаблонах на ключи i18n.

Зачем: в разметке осталось 58 подписей на русском (`label="ФИО"` и
подобные). На английском интерфейсе они остаются русскими, и это не
«мелочь» — подпись поля ввода и есть инструкция пользователю.

Как работает:
  1. Находит атрибуты label/title/placeholder/aria-label с кириллицей.
  2. Заменяет значение на :label="$t('<key>')" — с двоеточием, потому
     что это уже выражение Vue.
  3. Кладёт переводы в ru.json (исходный текст) и en.json (перевод).

Ключ строится из файла и смысла текста: courses.update.labelFullName,
а не из транслитерации — ключи переживают смену языка, а текст нет.
"""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAGES = ROOT / 'resources' / 'js' / 'Pages'
COMPONENTS = ROOT / 'resources' / 'js' / 'components'

# Атрибуты, где текст виден пользователю.
ATTRS = ('label', 'title', 'placeholder', 'aria-label')

# Рус → английский. Не машинный перевод: смысл важнее скорости.
TRANSLATIONS = {
    'ФИО': 'Full name',
    'Группа': 'Group',
    'Пароль': 'Password',
    'Повторите пароль': 'Repeat password',
    'Краткое описание курса': 'Short course description',
    'Полное описание курса': 'Full course description',
    'Категория курса': 'Course category',
    'Путь к курсу': 'Course path',
    'Путь курса': 'Course path',
    'Путь hash курса': 'Course hash path',
    'Выберите класс для добавления': 'Select a class to add',
    'Описание': 'Description',
    'Полное название курса': 'Full course name',
    'Краткое название курса': 'Short course name',
    'выбери файл': 'choose a file',
    'Панель курса': 'Course panel',
    'Поиск': 'Search',
    'Класс': 'Class',
    'Категории': 'Categories',
    'Инструктор': 'Instructor',
    'Вид занятия': 'Lesson type',
    'Загрузка': 'Loading',
    'Телефон': 'Phone',
    'Сфера деятельности': 'Field of activity',
    'Страна': 'Country',
    'Специализация': 'Specialization',
    'Разрешения': 'Permissions',
    'Организация': 'Organization',
    'Должность': 'Position',
    'Воинское звание': 'Military rank',
    'начало': 'start',
    'конец': 'end',
    'полное описание': 'full description',
    'описание курса': 'course description',
    'короткое описание': 'short description',
    'категории': 'categories',
}

# Ключи, которые нельзя генерировать из текста: слишком общие слова
# вроде «Описание» встречаются в десятке разных полей, и общий ключ
# означал бы «Описание» для поля категории.
NAMED = {
    ('Register.vue', 'ФИО'): 'register.fio',
    ('Register.vue', 'Группа'): 'register.group',
    ('Register.vue', 'Пароль'): 'register.password',
    ('Register.vue', 'Повторите пароль'): 'register.passwordRepeat',
    ('Login.vue', 'ФИО'): 'login.fio',
    ('Login.vue', 'Пароль'): 'login.password',
    ('GroupLearning.vue', 'Группа'): 'groupLearning.group',
    ('GroupLearning.vue', 'Класс'): 'groupLearning.class',
    ('GroupLearning.vue', 'Категории'): 'groupLearning.categories',
    ('GroupLearning.vue', 'Инструктор'): 'groupLearning.instructor',
    ('GroupLearning.vue', 'Вид занятия'): 'groupLearning.lessonType',
}


def slug(text: str) -> str:
    """Ключ из текста: латиница + нижний регистр."""
    table = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e',
        'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k',
        'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r',
        'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c',
        'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ъ': '', 'ы': 'y', 'ь': '',
        'э': 'e', 'ю': 'yu', 'я': 'ya',
    }
    ascii_text = str.maketrans(table)
    out = ''.join(c if c.isalnum() else ' ' for c in text.lower().translate(ascii_text))

    return '-'.join(out.split())


def set_path(data: dict, path: str, value: str) -> None:
    parts = path.split('.')
    node = data
    for part in parts[:-1]:
        node = node.setdefault(part, {})
    node[parts[-1]] = value


def main() -> int:
    ru_path = ROOT / 'resources' / 'js' / 'locales' / 'ru.json'
    en_path = ROOT / 'resources' / 'js' / 'locales' / 'en.json'
    ru = json.loads(ru_path.read_text(encoding='utf-8'))
    en = json.loads(en_path.read_text(encoding='utf-8'))

    changed = 0
    added = 0

    for path in sorted(list(PAGES.rglob('*.vue')) + list(COMPONENTS.rglob('*.vue'))):
        source = path.read_text(encoding='utf-8')
        name = path.name
        area = slug(path.parent.name) if path.parent.name not in ('Pages', 'js') else 'common'
        original = source

        for attr in ATTRS:
            pattern = re.compile(rf'\b{re.escape(attr)}="([^"{{}}]*[А-Яа-яЁё][^"{{}}]*)"')

            def replace(match, name=name, area=area):
                nonlocal changed, added
                text = match.group(1).strip()

                # Подстановки переводим целиком: ключ всё равно нужен,
                # а текст внутри скобок остаётся как есть — это
                # выражение Vue, а не строка.
                if '{{' in text:
                    english = TRANSLATIONS.get(text.split('{{')[0].strip()) or text.split('{{')[0].strip()
                    key = f'{area}.{attr}WithValue{slug(text.split("{{")[0].strip()).capitalize()}'
                    set_path(ru, key, text)
                    set_path(en, key, text.split('{{')[0].strip() + ' {{value}}')
                    changed += 1
                    added += 1

                    opening = ':' + attr + '="$t(\'' + key + '\', { value: '
                    closing = '})"'

                    return opening + text + closing
                if '`' in text:
                    return match.group(0)

                key = NAMED.get((name, text))
                english = TRANSLATIONS.get(text)

                if key is None:
                    if english is None:
                        # Без перевода не трогаем: ключ без значения
                        # покажет пользователю 'courses.label.xxx'.
                        return match.group(0)
                    key = f'{area}.{attr}{slug(text).capitalize()}'

                set_path(ru, key, text)
                set_path(en, key, english or text)
                added += 1
                changed += 1

                return f':{attr}="$t(\'{key}\')"'

            source = pattern.sub(replace, source)

        if source != original:
            path.write_text(source, encoding='utf-8')

    ru_path.write_text(json.dumps(ru, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    en_path.write_text(json.dumps(en, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

    print(f'заменено подписей: {changed}, ключей добавлено: {added}')

    return 0


if __name__ == '__main__':
    raise SystemExit(main())
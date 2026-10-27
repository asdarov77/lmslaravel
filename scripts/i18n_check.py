#!/usr/bin/env python3
"""
Проверка полноты i18n: все ключи, которые использует код, есть в
locales/*.json и совпадают в ru и en.

Зачем: скрипт i18n_labels.py переписывает .vue по мере обработки и
пишет локали в конце. Если он падает в середине, файлы страниц уже
переведены на ключи, а ключей в локали нет — на странице появляется
литерал «course.labelOpisanie», и это видно только глазами.

Проверка ловит такое сразу: ключ из кода без ключа в словаре — ошибка.
"""

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
JS_DIRS = [ROOT / 'resources' / 'js' / 'Pages', ROOT / 'resources' / 'js' / 'components']
LOCALES = {
    'ru': ROOT / 'resources' / 'js' / 'locales' / 'ru.json',
    'en': ROOT / 'resources' / 'js' / 'locales' / 'en.json',
}

# $t('key'), $tm('key'), t('key').
#
# Граница слова обязательна: без неё под шаблон попадают get('...'),
# emit('...'), closest('div') — они заканчиваются на «t(» и давали
# ложные «отсутствующие ключи» вида div, run, step.
KEY_RE = re.compile(r"(?<![A-Za-z0-9_$])(?:\$tm?|\bt)\(\s*['\"]([a-zA-Z0-9_.-]+)['\"]")


def load(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def has_key(data: dict, key: str) -> bool:
    node = data

    for part in key.split('.'):
        if not isinstance(node, dict) or part not in node:
            return False
        node = node[part]

    # Пустая строка — это тоже отсутствие перевода: пользователь увидит
    # пустое место вместо текста.
    if isinstance(node, str):
        return node.strip() != ''

    # Значения-массивы (тура Roboto/onboarding) — тоже перевод.
    if isinstance(node, list):
        return len(node) > 0

    return isinstance(node, dict) and bool(node)


def collect_used_keys() -> dict:
    used: dict[str, list[str]] = {}

    for directory in JS_DIRS:
        for path in sorted(directory.rglob('*')):
            if path.suffix not in ('.vue', '.js'):
                continue

            source = path.read_text(encoding='utf-8')

            for match in KEY_RE.finditer(source):
                key = match.group(1)

                # Динамические ключи строятся в рантайме (plan.status.X),
                # их верхний уровень проверяем, а сам суффикс — нет.
                if key.endswith('.'):
                    key = key[:-1]

                used.setdefault(key, [])
                used[key].append(str(path.relative_to(ROOT)))

    return used


def main() -> int:
    locales = {name: load(path) for name, path in LOCALES.items()}
    used = collect_used_keys()

    missing: dict[str, list[str]] = {}
    only_in: dict[str, list[str]] = {}

    for key, files in used.items():
        absent = [name for name, data in locales.items() if not has_key(data, key)]

        if absent:
            missing[key] = files
        elif has_key(locales['ru'], key) != has_key(locales['en'], key):
            only_in[key] = files

    if missing:
        print(f'КЛЮЧИ ОТСУТСТВУЮТ ({len(missing)}):')

        for key, files in sorted(missing.items()):
            print(f'  {key}  ← {files[0]}')

    if only_in:
        print(f'РАСХОЖДЕНИЯ ru/en ({len(only_in)}):')

        for key, files in sorted(only_in.items()):
            print(f'  {key}  ← {files[0]}')

    if not missing and not only_in:
        print(f'OK: все {len(used)} ключей найдены в обоих словарях')

    return 1 if (missing or only_in) else 0


if __name__ == '__main__':
    sys.exit(main())
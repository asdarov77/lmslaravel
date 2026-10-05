#!/usr/bin/env python3
"""
Добавляет поля в `meta` маршрутов файла resources/js/Router/routes.js.

Почему отдельный инструмент, а не правка регулярками: meta встречается
в двух формах — однострочной (`meta: { permission: [...] },`) и, реже,
многострочной. Регулярка по тексту либо теряла запятую, либо выносила
новое поле ЗА пределы meta (тогда Vue Router его молча игнорировал, а
страница оставалась без крошек), либо съедала закрывающую скобку
маршрута. Здесь скобки считаются по балансу, поэтому структура файла
не меняется.

Использование:
    python3 tools/routes_meta.py breadcrumbs
    python3 tools/routes_meta.py permission /my/exams "['exams.take', 'exams.manage']"
"""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

ROUTES = Path("resources/js/Router/routes.js")

# Уровни крошек: маршрут -> список ключей. Последний уровень без ссылки —
# текущая страница (params: true).
CRUMBS = {
    "/reg": [("app.title", "/"), ("app.menu.reguser", None)],
    "/user/list": [("app.title", "/"), ("app.menu.users", None)],
    "/permissions": [("app.title", "/"), ("app.menu.permissions", None)],
    "/groups/list": [("app.title", "/"), ("app.menu.groups", None)],
    "/groups/add": [("app.title", "/"), ("app.menu.groups", "/groups/list"), ("groups.create.title", None)],
    "/categories": [("app.title", "/"), ("app.menu.categories", None)],
    "/courses/list": [("app.title", "/"), ("app.menu.courses", None)],
    "/files/add": [("app.title", "/"), ("app.menu.files", None)],
    "/calendar": [("app.title", "/"), ("app.menu.calendar", None)],
    "/my/learning": [("app.title", "/"), ("app.menu.myLearning", None)],
    "/dashboard": [("app.title", "/"), ("app.menu.dashboard", None)],
    "/my/exams": [("app.title", "/"), ("app.menu.exams", None)],
    "/exams/:idEdit": [("app.title", "/"), ("app.menu.exams", "/my/exams"), ("exams.runner.title", None)],
    "/questions-main": [("app.title", "/"), ("app.menu.questionbank", None)],
    "/user/edit/:idEdit": [("app.title", "/"), ("app.menu.users", "/user/list"), ("users.list.edit", None)],
    "/user/chpass/:idEdit": [("app.title", "/"), ("app.menu.users", "/user/list"), ("users.list.password", None)],
    "/user/chrole/:idEdit": [("app.title", "/"), ("app.menu.users", "/user/list"), ("users.chrole.title", None)],
    "/groups/edit/:idEdit": [("app.title", "/"), ("app.menu.groups", "/groups/list"), ("groups.edit.title", None)],
    "/categories/:idEdit": [("app.title", "/"), ("app.menu.categories", "/categories"), ("categories.edit.title", None)],
}

PERMISSIONS = {
    "/my/exams": "['exams.take', 'exams.manage']",
    "/exams/:idEdit": "['exams.take', 'exams.manage']",
}


def line_of_path(lines: list[str], path: str) -> int | None:
    needle = f"path: '{path}'"
    return next((i for i, l in enumerate(lines) if l.strip().startswith(needle)), None)


def close_brace_of(text: str, open_at: int) -> int:
    """Индекс закрывающей скобки, парной открывающей в позиции open_at."""
    depth = 0
    for i in range(open_at, len(text)):
        if text[i] == "{":
            depth += 1
        elif text[i] == "}":
            depth -= 1
            if depth == 0:
                return i
    raise ValueError("не нашлась закрывающая скобка")


def route_bounds(lines: list[str], idx: int) -> tuple[int, int]:
    """
    Границы блока маршрута: открывающая строка `{` выше `path` и
    закрывающая `},` ниже.

    Раньше поиск meta шёл в окне ±12 строк, и для маршрута без meta
    (например /dashboard, где блок занимает 4 строки) окно
    дотягивалось до СЛЕДУЮЩЕГО маршрута: крошки «Личный кабинет»
    оказывались в meta у /auk, а /dashboard оставался без них.
    """
    start = idx
    while start >= 0 and lines[start].strip() != "{":
        start -= 1
    for end in range(idx, len(lines)):
        if lines[end].strip() in ("},", "}"):
            return (start, end)
    return (start, len(lines) - 1)


def insert_into_meta(lines: list[str], path: str, field: str, value: str) -> bool:
    idx = line_of_path(lines, path)
    if idx is None:
        print(f"  маршрут не найден: {path}")
        return False

    start, end = route_bounds(lines, idx)
    body = range(start + 1, end + 1)

    meta_idx = next((n for n in body if lines[n].lstrip().startswith("meta:")), None)

    if meta_idx is None:
        # Создать meta перед component (или перед props, либо в конец).
        comp_idx = next((n for n in body if lines[n].lstrip().startswith("component:")), None)
        if comp_idx is None:
            comp_idx = next((n for n in body if lines[n].lstrip().startswith("props:")), None)
        if comp_idx is None:
            comp_idx = end
        indent = " " * (len(lines[comp_idx]) - len(lines[comp_idx].lstrip()))
        lines.insert(comp_idx, f"{indent}meta: {{ {field}: {value} }},")
        return True

    line = lines[meta_idx]
    open_at = line.find("meta: {")

    if open_at < 0:
        print(f"  нестандартный meta у {path}: {line.strip()[:60]}")
        return False

    close_at = close_brace_of(line, open_at)

    # Однострочный meta — когда после закрывающей скобки идёт только
    # хвост маршрута (`,` и/или `},`). Проверка по «хвост начинается с
    # запятой или скобки» вместо сравнения с ",}" : в файле встречается
    # `meta: { permission: [...] },    },` — там после meta стоит хвост
    # `,    },`, и точное сравнение уводило в ветку многострочного meta.
    tail = line[close_at + 1:].strip()

    if tail == "" or tail.startswith(",") or tail.startswith("}"):
        # Однострочный meta: дописываем поле ПЕРЕД закрывающей скобкой.
        head = line[:close_at].rstrip()
        if not head.endswith(","):
            head += ","
        lines[meta_idx] = f"{head} {field}: {value} " + line[close_at:]
    else:
        # Многострочный meta: вставляем перед закрывающей строкой.
        close_line = next(n for n in range(meta_idx, len(lines)) if lines[n].lstrip().startswith("}"))
        indent = " " * (len(lines[close_line]) - len(lines[close_line].lstrip()) + 4)
        lines.insert(close_line, f"{indent}{field}: {value},")

    return True


def crumbs_value(levels: list[tuple[str, str | None]]) -> str:
    parts = []
    for key, to in levels:
        piece = f"{{ key: '{key}'"
        if to:
            piece += f", to: '{to}'"
        piece += " }"
        parts.append(piece)
    return "[" + ", ".join(parts) + "]"


def main() -> int:
    mode = sys.argv[1] if len(sys.argv) > 1 else "breadcrumbs"
    lines = ROUTES.read_text(encoding="utf-8").split("\n")

    if mode == "breadcrumbs":
        plan = [(p, "breadcrumbs", crumbs_value(v)) for p, v in CRUMBS.items()]
    elif mode == "permission":
        path, value = sys.argv[2], sys.argv[3]
        plan = [(path, "permission", value)]
    else:
        print(__doc__)
        return 1

    ok = 0
    for path, field, value in plan:
        if insert_into_meta(lines, path, field, value):
            ok += 1

    ROUTES.write_text("\n".join(lines), encoding="utf-8")
    print(f"применено: {ok} из {len(plan)}")

    # Синтаксическая проверка обязательна: инструмент правит файл с
    # вложенными объектами, ошибка тут ломает всё приложение.
    tmp = Path("/tmp/routes.verify.mjs")
    tmp.write_text("\n".join(lines), encoding="utf-8")
    check = subprocess.run(["node", "--check", str(tmp)], capture_output=True, text=True)
    if check.returncode == 0:
        print("синтаксис: OK")
    else:
        print("СИНТАКСИС НАРУШЕН:\n" + check.stderr.splitlines()[0])
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

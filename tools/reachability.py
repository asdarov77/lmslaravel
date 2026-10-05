#!/usr/bin/env python3
"""
Анализ достижимости фронтенд-модулей.

Строит граф импортов от настоящих точек входа и перечисляет файлы, до
которых нельзя добраться ни по одному пути. Только эти файлы считаются
мёртвыми; всё остальное (в том числе то, что выглядит копией) остаётся
на месте без изменений.

Точки входа:
  - resources/js/app.js (его подключает resources/views/app.blade.php);
  - всё, что приходит из resources/js/Router/routes.js (динамические
    import() маршрутов тоже считаются связями).

Использование:
  python3 tools/reachability.py            # список недостижимых файлов
  python3 tools/reachability.py --why FILE # кто импортирует этот файл
"""
from __future__ import annotations

import re
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
JS = ROOT / "resources" / "js"
ENTRY = JS / "app.js"

# import x from '...' / import '...' / import('...') / from '...'
IMPORT_RE = re.compile(
    r"""(?:\bfrom\s*|import\s*\(\s*|^\s*import\s+|require\s*\(\s*)['"]([^'"]+)['"]""",
    re.MULTILINE,
)


def resolve(importer: Path, spec: str) -> Path | None:
    """Разрешает спецификатор импорта в путь файла."""
    if not spec.startswith("."):
        return None  # пакет из node_modules

    base = (importer.parent / spec).resolve()
    candidates = [base]

    # Явные расширения не всегда пишут, а index-файлы — тоже.
    for ext in (".js", ".vue", ".json", ".css"):
        candidates.append(Path(str(base) + ext))
    candidates.append(base / "index.js")
    candidates.append(base / "index.vue")

    for candidate in candidates:
        if candidate.is_file():
            return candidate

    return None


def strip_comments(text: str) -> str:
    """
    Убирает комментарии, сохраняя содержимое строк.

    Без этого анализатор считал живыми файлы, импортируемые из
    ЗАКОММЕНТИРОВАННЫХ блоков: например, `FileManager2.vue` импортировался
    только в закомментированном маршруте, и файл попадал в «живые» —
    то есть в мусор, который мы собирались удалить. Ошибка была в
    безопасную сторону (лишнее осталось бы), но список становился
    недостоверным в обе стороны.

    `//` внутри строки ('http://…') не считается комментарием: смотрим,
    сколько кавычек встретилось в строке до `//`.
    """
    text = re.sub(r"/\*.*?\*/", "", text, flags=re.DOTALL)

    out: list[str] = []

    for line in text.splitlines():
        cut = None

        for match in re.finditer(r"//", line):
            before = line[: match.start()]
            # Нечётное число кавычек до `//` => `//` внутри строки.
            if before.count("'") % 2 == 1 or before.count('"') % 2 == 1:
                continue
            cut = match.start()
            break

        out.append(line if cut is None else line[:cut])

    return "\n".join(out)


def collect_imports(path: Path) -> list[str]:
    try:
        text = path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return []

    return IMPORT_RE.findall(strip_comments(text))


def main() -> int:
    if not ENTRY.is_file():
        print(f"Нет точки входа: {ENTRY}", file=sys.stderr)
        return 1

    # Обход в ширину от точки входа.
    seen: set[Path] = set()
    importers: dict[Path, set[Path]] = defaultdict(set)
    queue: list[Path] = [ENTRY.resolve()]

    while queue:
        current = queue.pop()

        if current in seen:
            continue

        seen.add(current)

        for spec in collect_imports(current):
            target = resolve(current, spec)

            if target is None or target in seen:
                continue

            importers[target].add(current)
            queue.append(target)

    all_files = {
        p.resolve()
        for ext in ("*.vue", "*.js")
        for p in JS.rglob(ext)
        # node_modules и каталог сборки внутри resources не встречаются,
        # но исключим на всякий случай.
        if "node_modules" not in p.parts
    }

    unreachable = sorted(all_files - seen)

    if len(sys.argv) > 2 and sys.argv[1] == "--why":
        needle = sys.argv[2]
        for path in sorted(all_files):
            if needle.lower() in path.name.lower():
                parents = ", ".join(sorted(p.name for p in importers.get(path, ())))
                status = "ДОСТИЖИМ" if path in seen else "НЕДОСТИЖИМ"
                print(f"{status}: {path.relative_to(ROOT)} <- [{parents or 'ниоткуда'}]")
        return 0

    print(f"Достижимых файлов: {len(seen)}")
    print(f"Файлов во resources/js: {len(all_files)}")
    print(f"Недостижимых: {len(unreachable)}\n")

    for path in unreachable:
        print(f"  {path.relative_to(ROOT)}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

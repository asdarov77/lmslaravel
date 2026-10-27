/**
 * Проверка целостности .vue файлов ДО сборки.
 *
 * Зачем: отсутствующий `</script>` или незакрытый <template> ломают
 * сборку одним сообщением «Element is missing end tag», которое
 * указывает на КОНЕЦ файла, а не на настоящее место. В проекте это
 * случилось с MyAccount.vue: пропал `</script>`, и vite падал с
 * сообщением про строку 245, хотя проблема была на 569.
 *
 * Здесь каждая .vue проверяется через @vue/compiler-sfc, и при
 * ошибке печатаются все найденные (обычно их одна) с точным файлом.
 *
 * Запуск: node scripts/check-sfc.mjs
 */
import fs from 'fs';
import path from 'path';
import { parse } from '@vue/compiler-sfc';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const SRC = path.join(ROOT, 'resources/js');

function walk(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p, out);
    else if (e.name.endsWith('.vue')) out.push(p);
  }
  return out;
}

let broken = 0;

for (const file of walk(SRC)) {
  const source = fs.readFileSync(file, 'utf8');
  const { descriptor, errors } = parse(source, { filename: file });

  for (const err of errors) {
    broken += 1;
    const line = err.loc?.start?.line ?? 0;
    console.error(`✗ ${path.relative(ROOT, file)}: ${err.message}`);
    if (line) console.error(`  (указано на строке ${line})`);
  }

  if (!descriptor.template && !descriptor.script && !descriptor.scriptSetup) {
    console.error(`✗ ${path.relative(ROOT, file)}: файл не содержит ни блока <template>, ни <script>`);
    broken += 1;
  }
}

const total = walk(SRC).length;

if (broken > 0) {
  console.error(`\nНайдено проблем: ${broken} (файлов проверено: ${total}). Сборка остановлена.`);
  process.exit(1);
}

console.log(`✓ Все ${total} .vue файлов разбираются корректно.`);

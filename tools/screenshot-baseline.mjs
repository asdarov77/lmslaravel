// Снятие baseline-скриншотов всех маршрутов (Фаза 0 плана редизайна).
// Запуск: node tools/screenshot-baseline.mjs
import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const APP_ROOT = join(__dirname, '..');
const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8080';
const OUT_DIR = join(APP_ROOT, 'docs', 'screenshots-baseline');

// Парсим маршруты из routes.js — только path и name
const routesSrc = readFileSync(join(APP_ROOT, 'resources/js/Router/routes.js'), 'utf-8');
const routeDefs = [...routesSrc.matchAll(/path:\s*'([^']+)'[\s\S]*?name:\s*'([^']+)'/g)]
  .map(m => ({ path: m[1], name: m[2] }));

// Фильтруем: только реальные страницы (не ошибки, не дубли по имени)
const seen = new Set();
const routes = routeDefs.filter(r => {
  if (r.path.startsWith('/40') || r.path.startsWith('/50')) return false;
  if (seen.has(r.name)) return false;
  seen.add(r.name);
  return true;
});

mkdirSync(OUT_DIR, { recursive: true });

const browser = await chromium.launch({
  executablePath: '/usr/bin/google-chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

// Логинимся
await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
await page.fill('#userLogin', 'Администратор');
await page.fill('#password', '123');
await page.click('button:has-text("ВХОД")');
await page.waitForURL('**/dashboard**', { timeout: 15000 });

console.log(`Маршрутов для скриншотов: ${routes.length}`);

let ok = 0, fail = 0;
for (const route of routes) {
  const url = `${BASE_URL}${route.path}`;
  const file = join(OUT_DIR, `${route.name}.png`);
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
    await page.waitForTimeout(800);
    await page.screenshot({ path: file, fullPage: false });
    console.log(`  ✓ ${route.name} (${route.path})`);
    ok++;
  } catch (e) {
    console.log(`  ✗ ${route.name} (${route.path}): ${e.message.split('\n')[0]}`);
    fail++;
  }
}

await browser.close();
console.log(`\nГотово: ${ok} успешно, ${fail} ошибок. Скриншоты: ${OUT_DIR}`);

// @ts-check
/**
 * Регресс-тесты на баги «страница падает / отображается криво».
 *
 * Предыдущие E2E проверяли в основном API напрямую (request fixture) и
 * проверяли лишь факт, что #app видим. Поэтому падения JS в рендере
 * (categories.sort is not a function, Cannot read properties of null,
 * 500 при сохранении) проходили незамеченными.
 *
 * Здесь ключевая идея: ЛЮБАЯ pageerror в консоли и любой 5xx на /api/ — это
 * провал теста. Плюс проверяем, что в таблицах реально есть строки.
 */
import { test, expect } from '@playwright/test';

const ADMIN = { fio: 'Администратор', password: '123' };

/** Собирает ошибки страницы/консоли, игнорируя внешние CDN (их нет в песочнице). */
function collectErrors(page) {
  const errors = [];
  const isExternal = (text) =>
    /avataaars\.io|bulma\.io|jsdelivr|googleapis|gstatic|ERR_(TIMED_OUT|NAME_NOT_RESOLVED|INTERNET_DISCONNECTED|CONNECTION)/i.test(
      text
    );

  page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
  page.on('console', (msg) => {
    if (msg.type() === 'error' && !isExternal(msg.text())) {
      errors.push(`console: ${msg.text()}`);
    }
  });
  page.on('response', (res) => {
    const url = res.url();
    if (url.includes('/api/') && res.status() >= 500) {
      errors.push(`http ${res.status()}: ${res.request().method()} ${url}`);
    }
  });

  return errors;
}

async function login(page, request) {
  const res = await request.post('/api/v1/login', { data: ADMIN });
  expect(res.ok()).toBeTruthy();
  const token = (await res.json()).data.token;

  await page.addInitScript((t) => {
    localStorage.setItem('token', t);
    localStorage.setItem('user', JSON.stringify({ fio: 'Администратор', role: 'Администратор' }));
  }, token);

  return token;
}

test.describe('Регресс: страницы не падают и отображают данные', () => {
  test.beforeEach(async ({ page, request }) => {
    await login(page, request);
  });

  test('список групп открывается без JS-ошибок и содержит строки', async ({ page }) => {
    // Регресс: Categories/Groups падали с "categories.sort is not a function"
    // и "Cannot read properties of null (reading 'id')"
    const errors = collectErrors(page);

    await page.goto('/#/groups/list');
    await page.waitForTimeout(2500);

    const rows = page.locator('table tbody tr');
    expect(await rows.count(), 'в таблице групп должны быть строки').toBeGreaterThan(0);
    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([]);
  });

  test('список пользователей открывается без JS-ошибок и содержит строки', async ({ page }) => {
    const errors = collectErrors(page);

    await page.goto('/#/user/list');
    await page.waitForTimeout(2500);

    expect(await page.locator('table tbody tr').count()).toBeGreaterThan(0);
    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([]);
  });

  test('страница курсов открывается без JS-ошибок (баг tag.id of null)', async ({ page }) => {
    // Регресс: Courses.vue — "Cannot read properties of null (reading 'id')"
    // из-за this.tags = response.data вместо развёрнутого массива
    const errors = collectErrors(page);

    await page.goto('/#/courses/list');
    await page.waitForTimeout(2500);

    await expect(page.locator('#app')).toBeVisible();
    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([]);
  });

  test('страница классов открывается без JS-ошибок', async ({ page }) => {
    // Регресс: /api/classesfs отдаёт конверт, allTags становился объектом
    const errors = collectErrors(page);

    await page.goto('/#/classes');
    await page.waitForTimeout(2500);

    await expect(page.locator('#app')).toBeVisible();
    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([]);
  });

  test('форма создания пользователя открывается и группы подгружаются', async ({ page }) => {
    // Регресс: "новый пользователь падает через сайт"
    const errors = collectErrors(page);

    await page.goto('/#/reg');
    await page.waitForTimeout(2500);

    await expect(page.locator('#app')).toBeVisible();
    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([]);
  });

  test('редактирование пользователя сохраняется без 500 (был регресс PATCH → 500)', async ({ page }) => {
    // Регресс: в store.user попадал конверт, PATCH уходил с fio: null
    // → users.fio NOT NULL → 500. Плюс "previous is not defined" в catch.
    const errors = collectErrors(page);

    await page.goto('/#/user/list');
    await page.waitForTimeout(2500);

    // Действие в строке — иконка, доступное имя задано aria-label.
    // Ищем по aria-label, а не по роли: v-btn с :to рендерится ссылкой
    // (<a>), а не кнопкой — это верная семантика для навигации.
    await page.locator('[aria-label^="Редактировать:"]').first().click();
    await page.waitForTimeout(2000);

    // ФИО должно подставиться из данных пользователя (а не из конверта)
    const fio = await page.locator('input[type="text"]').first().inputValue();
    expect(fio.trim(), 'ФИО должно быть заполнено').not.toBe('');

    const phones = page.locator('input[type="text"]');
    const count = await phones.count();
    if (count > 2) {
      await phones.nth(2).fill('+79990000000');
    }

    const patchPromise = page.waitForResponse(
      (r) => r.url().match(/\/api\/user\/\d+$/) && r.request().method() === 'PATCH',
      { timeout: 15000 }
    );

    await page.locator('.v-card-actions button:visible', { hasText: /save|сохран/i }).last().click();

    const patch = await patchPromise;
    expect(patch.status(), 'PATCH не должен возвращать 5xx').toBeLessThan(500);

    // после сохранения возвращаемся к списку
    await page.waitForTimeout(2000);
    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([]);
  });
});

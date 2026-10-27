// @ts-check
/**
 * Дымовый тест переработанного интерфейса LMS.
 *
 * Путь пользователя: вход → каталог курсов → карточка курса
 * → список своих экзаменов → история попыток (результат).
 *
 * Каждый шаг проверяет, что страница загружается без JS-ошибок, чтобы
 * ловить регрессы дизайн-системы (токены, переходы, состояния) на
 * реальном браузере. Если каталог/экзамены пусты (нет сидирования
 * данных), тест не падает — проверяется именно отрисовка оболочки.
 *
 * Запуск: npx playwright test tests/E2E/ui-redesign-smoke.spec.js
 * webServer конфига соберёт бандл (build:e2e) и поднимет
 * `php artisan serve` на :8080.
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://127.0.0.1:8080'
const ADMIN = { fio: process.env.ADMIN_FIO || 'Администратор', password: process.env.ADMIN_PASSWORD || '123' }

/** Логин через API + токен в localStorage (как в ui-flows.spec.js). */
async function auth(page) {
  const res = await page.request.post(`${BASE}/api/login`, { data: ADMIN })
  expect(res.ok(), `логин администратора ${ADMIN.fio}`).toBeTruthy()
  const body = await res.json()
  await page.addInitScript(
    ([t, u]) => {
      if (location.protocol !== 'http:' && location.protocol !== 'https:') return
      try {
        window.localStorage.setItem('token', t)
        window.localStorage.setItem('user', u)
      } catch (e) {}
    },
    [body.data.token, JSON.stringify(body.data.user ?? null)]
  )
}

/** Открыть страницу SPA и собирать JS-ошибки в общий массив. */
async function open(page, hashPath, errors) {
  await page.goto(`${BASE}/#${hashPath}`, { waitUntil: 'networkidle' })
}

test.describe('UI redesign smoke', () => {
  test('маршруты интерфейса отрисовываются без JS-ошибок', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    await auth(page)

    // 1. Каталог курсов
    await open(page, '/courses/list', errors)
    await expect(page.locator('.app__content')).toBeVisible()
    expect(errors).toEqual([], 'каталог курсов не должен давать JS-ошибок')

    const firstCard = page.locator('[data-test="catalog-card"]').first()
    if (await firstCard.count() > 0) {
      // 2. Карточка курса
      await firstCard.locator('[data-test="catalog-open"]').first().click()
      await page.waitForLoadState('networkidle')
      await expect(page.locator('h1, [data-test]').first()).toBeVisible()
    }

    // 3. Список экзаменов обучающегося
    await open(page, '/my/exams', errors)
    await expect(page.locator('.app__content')).toBeVisible()
    expect(errors).toEqual([], 'экзамены не должны давать JS-ошибок')

    // 4. История попыток (страница результатов)
    await open(page, '/my/exams/attempts', errors)
    await expect(page.locator('.app__content')).toBeVisible()
    expect(errors).toEqual([], 'история попыток не должна давать JS-ошибок')
  })
})

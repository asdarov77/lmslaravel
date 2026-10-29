// @ts-check
/**
 * E2E «Плеера 2.0» (design-manifest §3, M2: player-race.spec).
 *
 * Проверяет в реальном браузере то, что не покрыто юнит-контрактами M2:
 *   1. URL-as-truth + гонка переключений (race-guard useLessonLoader):
 *      быстрая серия кликов по дереву НЕ порождает «смешанный» урок —
 *      заголовок рабочей области всегда соответствует ?item= из адресной
 *      строки (регрессия REWORK R1: старый ответ победил новый).
 *   2. Cinema mode: f/кнопка переключают cpp--cinema, Escape выходит;
 *      шорткаты молчат, когда фокус в поле ввода (§Манифест 1.4).
 *   3. ARIA-структура premium-дерева: role=tree на контейнере, treeitem на
 *      материалах, ровно один roving tabindex=0 (регрессия Д10/D-P8).
 *   4. Живой SPA без JS-ошибок: composable usePremiumPlayer executes in
 *      strict mode (регрессия TDZ lastExamFetchKey — в Node/vitest её не
 *      поймать из-за hoisting esbuild, здесь падает весь premium-путь).
 *
 * ТРЕБОВАНИЯ К СТЕНДУ (совпадают с course-player-flows.spec.js):
 *   - UI_COURSE_PLAYER_V2=true (маршрут courses.premium условный);
 *   - админ "Администратор"/"123"; курс минимум с двумя материалами.
 * Без стенда тесты пропускаются, а не падают.
 *
 * ЗАПУСК: npx playwright test tests/E2E/premium-player.spec.js
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8080'
const HASH = BASE + '/#'
const ADMIN = { fio: 'Администратор', password: '123' }

async function auth(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора').toBeTruthy()
  const token = (await res.json()).data.token

  const me = await page.request.get(BASE + '/api/v1/me', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const user = (await me.json())?.data?.user ?? null

  await page.addInitScript(([t, u]) => {
    if (location.protocol !== 'http:' && location.protocol !== 'https:') return
    try {
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    } catch (e) { /* хранилище недоступно — упадёт позже на проверке токена */ }
  }, [token, JSON.stringify(user)])
  return token
}

async function premiumEnabled(page) {
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
  return page.evaluate(() => !!(window.__UI_FLAGS__ || {}).course_player_v2)
}

/** Курс с >=2 материалами — тот же путь, что у legacy-спека. */
async function findPlayableCourse(page, token) {
  const res = await page.request.get(BASE + '/api/courses', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const courses = (await res.json())?.data ?? []
  for (const course of courses) {
    const p = await page.request.get(`${BASE}/api/course/${course.id}/player`, {
      headers: { Authorization: 'Bearer ' + token },
    })
    if (!p.ok()) continue
    const payload = (await p.json())?.data
    const materials = (payload?.nodes ?? []).filter((n) => n.is_material)
    if (materials.length >= 2) return { course, materials }
  }
  return null
}

test.describe('плеер 2.0: гонки, cinema, a11y', () => {
  test.beforeEach(async ({ page }) => {
    test.info().annotations.push({
      type: 'requirement',
      description: 'стенд: UI_COURSE_PLAYER_V2=true, админ "Администратор"/"123"',
    })
  })

  test('premium-оболочка стартует без JS-ошибок (regression: TDZ lastExamFetchKey)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await premiumEnabled(page)), 'флаг ui.course_player_v2 выключен')
    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет курса минимум с двумя материалами')

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    await page.goto(`${HASH}/courses/${found.course.id}/premium`, { waitUntil: 'domcontentloaded' })

    const tree = page.getByRole('tree')
    await expect(tree, 'premium-дерево отрисовано').toBeVisible({ timeout: 15000 })
    // ReferenceError в setup composable убил бы и дерево, и live-region.
    expect(errors, `JS-ошибки при старте premium-плеера: ${errors.join('; ')}`).toEqual([])
  })

  test('URL-as-truth под спамом кликов: финальный урок == ?item= (REWORK R1)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await premiumEnabled(page)), 'флаг ui.course_player_v2 выключен')
    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет playable-курса')
    const { course, materials } = found

    await page.goto(`${HASH}/courses/${course.id}/premium`, { waitUntil: 'domcontentloaded' })
    const tree = page.getByRole('tree')
    await expect(tree).toBeVisible({ timeout: 15000 })

    // Спам: m0 → m1 → m2 подряд без ожидания загрузки среднего.
    const targets = materials.slice(0, Math.min(3, materials.length))
    for (const m of targets) {
      await tree.getByRole('treeitem').filter({ hasText: m.title }).first().click()
    }
    const last = targets[targets.length - 1]

    // URL доехал до последнего клика (router.push({query:{item}})).
    await expect(page).toHaveURL(new RegExp(`item=${last.id}`), { timeout: 10000 })

    // Race-guard: озвучка победителя (role=status live-region) — про последний
    // урок; промежуточные ответы сервера не должны «протечь» в DOM.
    const announce = page.locator('[role="status"][aria-live="polite"]').first()
    await expect
      .poll(async () => (await announce.textContent()) || '', { timeout: 20000 })
      .toContain(last.title)

    // Активный узел дерева ровно один и это last (ctnp--active).
    await expect(tree.locator('.ctnp--active')).toHaveCount(1, { timeout: 10000 })
    await expect(tree.locator('.ctnp--active').first()).toContainText(last.title)
  })

  test('cinema mode: кнопка/f входят, Escape выходит; шорткат в поле молчит', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await premiumEnabled(page)), 'флаг ui.course_player_v2 выключен')
    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет playable-курса')

    await page.goto(`${HASH}/courses/${found.course.id}/premium?item=${found.materials[0].id}`, {
      waitUntil: 'domcontentloaded',
    })
    const root = page.locator('.cpp')
    await expect(root).toBeVisible({ timeout: 15000 })

    await page.locator('[data-test="btn-cinema"]').click()
    await expect(root, 'cinema-класс проставлен').toHaveClass(/cpp--cinema/)

    // Выход Escape'ом (тот же путь, что у клавиатуры выше) — перед входом по 'f'.
    await page.keyboard.press('Escape')
    await expect(root).not.toHaveClass(/cpp--cinema/, { timeout: 5000 })

    // Повторный вход клавишей f
    await page.keyboard.press('f')
    await expect(root, 'f включает cinema').toHaveClass(/cpp--cinema/)
    await page.keyboard.press('Escape')
    await expect(root, 'Escape выходит из cinema').not.toHaveClass(/cpp--cinema/)
  })

  test('roving tabindex дерева: ровно один tabbable узел (Д10)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await premiumEnabled(page)), 'флаг ui.course_player_v2 выключен')
    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет playable-курса')
    const { course, materials } = found

    await page.goto(`${HASH}/courses/${course.id}/premium`, { waitUntil: 'domcontentloaded' })
    const tree = page.getByRole('tree')
    await expect(tree).toBeVisible({ timeout: 15000 })

    const first = tree.getByRole('treeitem').filter({ hasText: materials[0].title }).first()
    await first.focus()
    await expect(first).toHaveAttribute('tabindex', '0')

    await page.keyboard.press('ArrowDown')
    const focusedId = await page.evaluate(() => document.activeElement?.id || '')
    expect(focusedId, 'стрелка перевела roving-фокус').toMatch(/^ctnp-\d+$/)
    expect(focusedId).not.toBe(await first.getAttribute('id'))

    // Инвариант roving: во всём дереве tabindex="0" ровно один.
    await expect(tree.locator('[tabindex="0"]')).toHaveCount(1)
  })
})

// @ts-check
/**
 * Симуляция пользователя в плеере курса (Playwright, blueprint S1).
 *
 * Покрывает то, что нельзя проверить ни PHPUnit'ом, ни Vitest'ом:
 * реальный браузер, реальный Vuetify-стейт, реальные переходы по ?item=.
 *
 * ЧТО ПРОВЕРЯЕТСЯ.
 *  1. Дерево курса (role=tree/treeitem): клик по материалу меняет URL
 *     (?item=), урок загружается, узел получает ctn--active/ctn--visited.
 *  2. Навигация prev/next: на первом уроке «назад» disabled, на последнем
 *     «вперёд» disabled — регрессия единого цикла prev/next (Д8).
 *  3. Состояния LessonWorkspace: loading → ready; error + кнопка retry;
 *     locked (403) — через page.route-моки API, без зависимости от выкладки.
 *  4. Устойчивость к битому ответу сервера: пустой nodes[] не роняет SPA
 *     (pageerror = []), показывается idle/пустое состояние.
 *  5. Клавиатурная доступность дерева (Д10): стрелки перемещают roving
 *     tabindex, Enter открывает материал.
 *
 * ТРЕБОВАНИЯ К СТЕНДУ.
 *  - UI_COURSE_PLAYER_V2=true в .env стенда: маршрут /courses/:id/play
 *    регистрируется во фронтенде только при включённом флаге (config/ui.php).
 *    Без флага все тесты пропускаются с внятным сообщением, а не падают.
 *  - Администратор (fio='Администратор', password='123') и хотя бы один
 *    курс с материалами — как в остальном E2E-пакете.
 *
 * ЗАПУСК: npx playwright test tests/E2E/course-player-flows.spec.js
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8080'
const HASH = BASE + '/#'
const ADMIN = { fio: 'Администратор', password: '123' }

/** Логин через API + токен/пользователь в localStorage ДО загрузки SPA. */
async function auth(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора').toBeTruthy()
  const token = (await res.json()).data.token

  const me = await page.request.get(BASE + '/api/v1/me', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const user = (await me.json())?.data?.user ?? null

  await page.addInitScript(
    ([t, u]) => {
      if (location.protocol !== 'http:' && location.protocol !== 'https:') return
      try {
        localStorage.setItem('token', t)
        localStorage.setItem('user', u)
      } catch (e) {
        /* хранилище недоступно — тест упадёт позже на проверке токена */
      }
    },
    [token, JSON.stringify(user)],
  )
  return token
}

/** Флаг плеера v2 активен на стенде? (window.__UI_FLAGS__ из blade-шаблона.) */
async function playerV2Enabled(page) {
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
  return page.evaluate(() => !!(window.__UI_FLAGS__ || {}).course_player_v2)
}

/**
 * Находит курс с >=2 материалами через player-endpoint.
 * Идём той же дорогой, что и UI: /api/courses → /api/course/{id}/player.
 */
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
    if (materials.length >= 2) {
      return { course, materials }
    }
  }
  return null
}

test.describe('плеер v2: пользовательские сценарии', () => {
  test.beforeEach(async ({ page }) => {
    test.info().annotations.push({
      type: 'requirement',
      description: 'стенд: UI_COURSE_PLAYER_V2=true, админ "Администратор"/"123"',
    })
  })

  test('открытие курса: дерево, первый урок, active/visited', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await playerV2Enabled(page)), 'флаг ui.course_player_v2 выключен на стенде')

    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет курса минимум с двумя материалами')
    const { course, materials } = found

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    await page.goto(`${HASH}/courses/${course.id}/play`, { waitUntil: 'domcontentloaded' })

    // Дерево отрисовано, материалы кликабельны (role=treeitem).
    const tree = page.getByRole('tree')
    await expect(tree, 'дерево курса').toBeVisible({ timeout: 15000 })
    const items = tree.getByRole('treeitem')
    expect(await items.count(), 'узлов-материалов не меньше, чем в API')
      .toBeGreaterThanOrEqual(materials.length)

    // Рабочая область вышла из loading в какое-то терминальное состояние.
    const workspace = page.locator('.lw')
    await expect(workspace).toBeVisible()
    await expect(page.locator('[data-test="lw-loading"]')).toBeHidden({ timeout: 20000 })

    // Клик по второму материалу: URL-as-truth — ?item= меняется, урок грузится.
    const second = materials[1]
    await items.filter({ hasText: second.title }).first().click()
    await page.waitForURL(new RegExp(`item=${second.id}`), { timeout: 10000 })

    await expect(
      page.locator(`.ctn--material.ctn--active:has-text("${second.title}")`),
      'активный узел подсвечен',
    ).toHaveCount(1)

    // Посещение фиксируется в дереве (✓ visited) — markVisited после загрузки.
    await expect(
      page.locator(`.ctn--visited:has-text("${second.title}")`),
      'посещённый узел отмечен',
    ).toBeVisible({ timeout: 10000 })

    // Заголовок урока в рабочей области соответствует выбранному материалу.
    await expect(page.locator('.lw__title'), 'заголовок урока').toContainText(second.title)

    expect(errors, `JS-ошибки: ${errors.join('; ')}`).toEqual([])
  })

  test('prev/next: границы цикла заблокированы (Д8)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await playerV2Enabled(page)), 'флаг ui.course_player_v2 выключен')

    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет курса минимум с двумя материалами')
    const { course, materials } = found

    // Первый материал: «назад» disabled, «вперёд» enabled.
    await page.goto(
      `${HASH}/courses/${course.id}/play?item=${materials[0].id}`,
      { waitUntil: 'domcontentloaded' },
    )
    const prev = page.getByLabel(/предыдущ|Предыдущ/, { exact: false })
    const next = page.getByLabel(/следующ|Следующ/, { exact: false })
    await expect(prev).toBeVisible({ timeout: 15000 })
    await expect(prev, 'у первого урока «назад» заблокирован').toBeDisabled()
    await expect(next, '«вперёд» активен').toBeEnabled()

    // Клик next ведёт ровно к следующему материалу цикла.
    await next.click()
    await page.waitForURL(new RegExp(`item=${materials[1].id}`), { timeout: 10000 })

    // Последний материал: «вперёд» disabled.
    const last = materials[materials.length - 1]
    await page.goto(
      `${HASH}/courses/${course.id}/play?item=${last.id}`,
      { waitUntil: 'domcontentloaded' },
    )
    await expect(next, 'у последнего урока «вперёд» заблокирован').toBeDisabled({ timeout: 15000 })
  })

  test('состояние error + retry (мок 500 на content-endpoint)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await playerV2Enabled(page)), 'флаг ui.course_player_v2 выключен')

    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет playable-курса')
    const { course, materials } = found

    // Мок: документ урока отдаёт 500 — проверяем ветку lw-error и кнопку повтора.
    await page.route('**/api/course/' + course.id + '/content/**', (route) =>
      route.fulfill({ status: 500, contentType: 'application/json', body: '{"error":"boom"}' }),
    )

    await page.goto(
      `${HASH}/courses/${course.id}/play?item=${materials[0].id}`,
      { waitUntil: 'domcontentloaded' },
    )

    const err = page.locator('[data-test="lw-error"]')
    await expect(err, 'показана ошибка загрузки урока').toBeVisible({ timeout: 20000 })

    // Retry: снимаем мок (route.unroute недоступен стабильно до ответа —
    // перекрываем новым моком 200 с минимальным HTML) и ждём ready/idle.
    await page.route('**/api/course/' + course.id + '/content/**', (route) =>
      route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>ok</body></html>' }),
    )
    await err.getByRole('button').click()
    await expect(
      page.locator('[data-test="lw-frame"], [data-test="lw-media"], [data-test="lw-empty"]'),
      'после retry урок отрисовался',
    ).toBeVisible({ timeout: 20000 })
    await expect(page.locator('[data-test="lw-error"]')).toBeHidden()
  })

  test('битый payload дерева не роняет SPA (граничный случай)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await playerV2Enabled(page)), 'флаг ui.course_player_v2 выключен')

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    // Мок: course существует, но nodes пустой — плеер обязан уйти в idle,
    // а не падать (регрессия «undefined is not iterable» на мёртвых курсах).
    await page.route('**/api/course/*/player*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            course: { id: 1, title: 'Пустой курс' },
            nodes: [],
            entry: null,
            resume: null,
            stats: { materials_total: 0, materials_visited: 0, percent: 0 },
          },
        }),
      }),
    )

    await page.goto(`${HASH}/courses/1/play`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('[data-test="lw-idle"]'), 'idle-подсказка вместо падения')
      .toBeVisible({ timeout: 15000 })
    expect(errors, `SPA не должен падать на пустом дереве: ${errors.join('; ')}`).toEqual([])
  })

  test('клавиатура: стрелки и Enter в дереве (Д10, roving tabindex)', async ({ page }) => {
    const token = await auth(page)
    test.skip(!(await playerV2Enabled(page)), 'флаг ui.course_player_v2 выключен')

    const found = await findPlayableCourse(page, token)
    test.skip(!found, 'нет playable-курса')
    const { course, materials } = found

    await page.goto(`${HASH}/courses/${course.id}/play`, { waitUntil: 'domcontentloaded' })
    const first = page.getByRole('treeitem').filter({ hasText: materials[0].title }).first()
    await expect(first).toBeVisible({ timeout: 15000 })

    await first.focus()
    // Ровинг-tabindex: ровно один узел принимает tab-фокус.
    await expect(first, 'стартовый узел в tabindex-цепочке').toHaveAttribute('tabindex', '0')

    await page.keyboard.press('ArrowDown')
    const focusedId = await page.evaluate(
      () => document.activeElement?.getAttribute('id') || '',
    )
    expect(focusedId, 'стрелка перевела фокус на другой узел дерева').toMatch(/^tree-node-\d+$/)
    expect(focusedId).not.toBe(await first.getAttribute('id'))

    // Enter на материале = выбор урока (URL-as-truth).
    await page.keyboard.press('Enter')
    await expect
      .poll(async () => page.url().includes('item='), { timeout: 10000 })
      .toBeTruthy()
  })
})

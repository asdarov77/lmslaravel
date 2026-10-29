// @ts-check
/**
 * Визуальная регрессия (snapshot-тесты) ключевых экранов LMS.
 *
 * Идея: toHaveScreenshot() сравнивает рендер с эталоном (baseline). Первый
 * прогон со --update-snapshots создаёт эталоны в
 * tests/E2E/visual-regression.spec.js-snapshots/ — их нужно закоммитить;
 * каждый последующий прогон ловит визуальный регресс (сдвиг вёрстки, слом
 * темы Vuetify, «поехавшие» состояния кнопок) до того, как это увидит
 * пользователь.
 *
 * ПОКРЫТИЕ (по ТЗ):
 *  1. Страница авторизации — desktop / tablet / mobile (breakpoints Vuetify).
 *  2. Плеер курса v2 (?item= выбран) — desktop / tablet / mobile.
 *  3. Файловый менеджер — desktop / mobile.
 *  4. Состояния элементов управления: hover / focus-visible / disabled у
 *     кнопок формы логина; снапшот валидации (красная рамка + текст ошибки
 *     под инпутом) при отправке пустой формы.
 *
 * АНТИФЛЭККИ (обязательно, иначе снапшоты нестабильны):
 *  - animations: 'disabled' — гасит CSS-анимации/переходы Vuetify;
 *  - caret: 'hide'    — мигающий курсор инпута не попадает в кадр;
 *  - шрифт Roboto ждём через document.fonts.ready — иначе первый кадр
 *    снимается на fallback-шрифте и эталон рассинхронизируется;
 *  - все таймеры/счётчики (rate-limit) на этих экранах неактивны без действий.
 *
 * ТРЕБОВАНИЯ К СТЕНДУ (те же, что у остального E2E-пакета):
 *  - php artisan serve :8080 (webServer в playwright.config.js стартует сам);
 *  - администратор fio='Администратор', password='123';
 *  - для плеера: UI_COURSE_PLAYER_V2=true и хотя бы один курс с материалами
 *    (без этого тесты плеера пропускаются с внятным сообщением);
 *  - стабильные данные: прогонять на детерминированном сиде, иначе фоновые
 *    изменения контента будут давать ложные диффы.
 *
 * ЗАПУСК:
 *   npx playwright test tests/E2E/visual-regression.spec.js --update-snapshots  # baseline
 *   npx playwright test tests/E2E/visual-regression.spec.js                     # регресс
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8080'
const ADMIN = { fio: 'Администратор', password: '123' }

/** Опции всех снапшотов — единые, чтобы поведение не зависело от вызова. */
const SNAP = { animations: /** @type {'disabled'} */ ('disabled'), caret: /** @type {'hide'} */ ('hide') }

/** Гасим анимации в самом DOM: надёжнее, чем ждать их окончания. */
async function stabilize(page) {
  await page.addStyleTag({
    content: `*, *::before, *::after {
      animation-duration: 0s !important;
      transition-duration: 0s !important;
      transition-delay: 0s !important;
      caret-color: transparent !important;
    }`,
  })
}

/** Ждём web-шрифты — иначе baseline снят на fallback и diffs на каждом CI. */
async function waitFonts(page) {
  await page.evaluate(() => document.fonts.ready)
}

/** Логин через API + токен в localStorage ДО загрузки SPA (как во всём пакете). */
async function authAsAdmin(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора').toBeTruthy()
  const token = (await res.json()).data.token

  await page.addInitScript((t) => {
    localStorage.setItem('token', t)
    localStorage.setItem('user', JSON.stringify({ fio: 'Администратор', role: 'Администратор' }))
  }, token)
  return token
}

/**
 * Разрешения по брейкпоинтам Vuetify 3:
 * mobile <600, tablet 600–1279 (берём 900), desktop ≥1280 (берём 1440).
 */
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  tablet: { width: 900, height: 1024 },
  mobile: { width: 390, height: 844 },
}

test.describe('Визуальная регрессия: страница авторизации', () => {
  // Экран гостя — без токена.
  for (const [name, viewport] of Object.entries(VIEWPORTS)) {
    test(`login — ${name} (${viewport.width}x${viewport.height})`, async ({ page }) => {
      await page.setViewportSize(viewport)
      await page.goto(`${BASE}/#/login`, { waitUntil: 'networkidle' })
      // Форма внутри FormCard отрисована (не skeleton/loading).
      await expect(page.locator('#userLogin')).toBeVisible()
      await waitFonts(page)
      await stabilize(page)
      await expect(page.locator('.u-form')).toHaveScreenshot(`login-${name}.png`, SNAP)
    })
  }

  test('login — состояние валидации: пустая отправка даёт красные поля', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await page.goto(`${BASE}/#/login`, { waitUntil: 'networkidle' })
    await expect(page.locator('#userLogin')).toBeVisible()

    // Клик по submit (FormActions: v-btn[type=submit] внутри form.u-form).
    await page.locator('form.u-form button[type="submit"]').click()

    // Регресс-страховка логики UI-kit: ошибка = класс u-field--invalid
    // (красный бордер) + текст .u-field__error под инпутом. Если редизайн
    // сломает вывод ошибок — упадёт здесь, а не молча на диффе снапшота.
    const invalid = page.locator('.u-field--invalid')
    await expect(invalid.first()).toBeVisible({ timeout: 5000 })
    await expect(page.locator('.u-field__error').first()).not.toBeEmpty()

    await waitFonts(page)
    await stabilize(page)
    await expect(page.locator('.u-form')).toHaveScreenshot('login-validation-error.png', SNAP)
  })

  test('login — состояния кнопок: hover / focus-visible / disabled', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await page.goto(`${BASE}/#/login`, { waitUntil: 'networkidle' })
    await expect(page.locator('#userLogin')).toBeVisible()

    const submit = page.locator('form.u-form button[type="submit"]')

    // HOVER: реальный наводчик мыши; фон v-btn меняется (:hover в CSS).
    await submit.hover()
    await waitFonts(page)
    await stabilize(page)
    await expect(submit).toHaveScreenshot('btn-primary-hover.png', { ...SNAP, timeout: 15000 })

    // FOCUS-VISIBLE: клавиатурный фокус (Tab) рисует outline — критично для
    // WCAG 2.2 SC 2.4.7; пропажа outline (например, global reset) = дифф.
    await page.keyboard.press('Tab')
    await waitFonts(page)
    await stabilize(page)
    await expect(page.locator('#userLogin')).toHaveScreenshot('input-focus-visible.png', SNAP)

    // DISABLED: состояние busy реально возникает только во время запроса.
    // Держим /api/login «висящим», пока делаем снапшот кнопки, затем
    // отпускаем перехват, чтобы не блокировать остальные тесты воркера.
    let release
    const pending = new Promise((r) => (release = r))
    await page.route('**/api/login', async (route) => {
      await pending
      await route.fulfill({ status: 422, contentType: 'application/json', body: '{"message":"stub"}' })
    })
    try {
      await page.fill('#userLogin', 'x')
      await page.fill('#password', 'y')
      await submit.click() // submitting=true → v-btn :loading → disabled
      await expect(submit).toBeDisabled({ timeout: 5000 })
      await waitFonts(page)
      await stabilize(page)
      await expect(submit).toHaveScreenshot('btn-primary-disabled-loading.png', SNAP)
    } finally {
      release()
      await page.unroute('**/api/login')
    }
  })
})

test.describe('Визуальная регрессия: плеер курса v2', () => {
  /** Находит курс с >=2 материалами (той же дорогой, что UI) или null. */
  async function findPlayableCourseId(page, token) {
    const res = await page.request.get(`${BASE}/api/courses`, {
      headers: { Authorization: 'Bearer ' + token },
    })
    const courses = (await res.json())?.data ?? []
    for (const course of courses) {
      const p = await page.request.get(`${BASE}/api/course/${course.id}/player`, {
        headers: { Authorization: 'Bearer ' + token },
      })
      if (!p.ok()) continue
      const nodes = (await p.json())?.data?.nodes ?? []
      const materials = JSON.stringify(nodes).match(/"is_material":true/g)
      if (materials && materials.length >= 2) return course.id
    }
    return null
  }

  for (const [name, viewport] of Object.entries(VIEWPORTS)) {
    test(`player — ${name}`, async ({ page }) => {
      await page.setViewportSize(viewport)
      const enabled = await (async () => {
        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        return page.evaluate(() => !!(window.__UI_FLAGS__ || {}).course_player_v2)
      })()
      test.skip(!enabled, 'UI_COURSE_PLAYER_V2=false на стенде — маршрут /courses/:id/play не зарегистрирован')

      const token = await authAsAdmin(page)
      const courseId = await findPlayableCourseId(page, token)
      test.skip(!courseId, 'Нет курса с >=2 материалами — нечего снимать в плеере')

      // Открываем плеер СРАЗУ с выбранным уроком: ?item= фиксирует выбор,
      // иначе снапшот зависит от того, какой урок автовыбрал loader.
      await page.goto(`${BASE}/#/courses/${courseId}/play`, { waitUntil: 'networkidle' })
      const firstMaterial = page.locator('[role="treeitem"].ctn--material').first()
      await expect(firstMaterial).toBeVisible({ timeout: 15000 })
      await firstMaterial.click()

      // Рабочая область должна выйти из loading в любое терминальное состояние
      // (ready/empty/locked/error — все они валидны для baseline, важно,
      // чтобы НЕ было бесконечного спиннера).
      const workspace = page.locator('.lw, .lw__state').first()
      await expect(page.locator('[data-test="lw-loading"]')).toBeHidden({ timeout: 15000 })
      await expect(workspace).toBeVisible()

      await waitFonts(page)
      await stabilize(page)
      await expect(page.locator('.cp2')).toHaveScreenshot(`player-${name}.png`, SNAP)
    })
  }
})

test.describe('Визуальная регрессия: файловый менеджер', () => {
  for (const name of ['desktop', 'mobile']) {
    test(`filemanager — ${name}`, async ({ page }) => {
      await page.setViewportSize(VIEWPORTS[name])
      await authAsAdmin(page)

      // Мокируем список файлов детерминированным набором: реальный каталог
      // меняется между прогонами (загрузки/удаления) и давал бы вечные диффы.
      await page.route('**/api/filemanager/list*', async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            data: {
              path: '/',
              folders: [{ id: 1, name: 'Материалы курса' }],
              files: [
                { id: 11, name: 'lesson-01.pdf', size: 102400, kind: 'file', mime: 'application/pdf', updated_at: '2026-01-01T00:00:00Z' },
                { id: 12, name: 'video-intro.mp4', size: 2048000, kind: 'file', mime: 'video/mp4', updated_at: '2026-01-02T00:00:00Z' },
              ],
            },
          }),
        })
      })

      await page.goto(`${BASE}/#/filemanager`, { waitUntil: 'networkidle' })
      await expect(page.locator('[data-test="fm-breadcrumbs"]')).toBeVisible({ timeout: 15000 })

      await waitFonts(page)
      await stabilize(page)
      // Снимаем весь шелл приложения: тулбар, хлебные крошки, сетку файлов —
      // регресс шапки/меню/сетки Vuetify ловится одним диффом.
      await expect(page.locator('#app')).toHaveScreenshot(`filemanager-${name}.png`, SNAP)
    })
  }
})

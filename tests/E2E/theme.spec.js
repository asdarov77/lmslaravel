// @ts-check
/**
 * Тема оформления: переключение в интерфейсе (Playwright).
 *
 * Проверяет то, что видно только в браузере:
 *  - кнопка переключает тему БЕЗ перезагрузки (иначе карточки темнели,
 *    а фон и текст оставались светлыми до следующей загрузки);
 *  - выбор переживает перезагрузку и восстанавливается до отрисовки —
 *    иначе страница мигает светлой;
 *  - тёмная тема реально тёмная: фон, карточки и текст меняются, а не
 *    только один слой;
 *  - текст на тёмном фоне остаётся читаемым (контраст по WCAG).
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const ADMIN = { fio: 'Администратор', password: '123' }

/** Коэффициент контраста по WCAG для пары цветов. */
const contrast = (fg, bg) => {
  const lum = (color) => {
    const parts = String(color).match(/[\d.]+/g)
    if (!parts) return null
    const [r, g, b] = parts.slice(0, 3).map((v) => {
      const s = Number(v) / 255
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4)
    })
    return 0.2126 * r + 0.7152 * g + 0.0722 * b
  }
  const a = lum(fg)
  const b = lum(bg)
  if (a === null || b === null) return null
  const hi = Math.max(a, b)
  const lo = Math.min(a, b)
  return (hi + 0.05) / (lo + 0.05)
}

async function auth(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора').toBeTruthy()
  const token = (await res.json()).data.token

  const me = await page.request.get(BASE + '/api/v1/me', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const user = (await me.json())?.data?.user ?? null

  // Только ключи сессии: полная очистка стирала бы и выбор темы.
  await page.addInitScript(
    ([t, u]) => {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )
}

const snapshot = (page) =>
  page.evaluate(() => {
    const app = document.querySelector('.v-application')
    const card = document.querySelector('.u-card, .v-card')
    const cs = (el) => (el ? getComputedStyle(el) : null)
    return {
      html: document.documentElement.getAttribute('data-theme'),
      appTheme: app?.className.match(/v-theme--\w+/)?.[0] ?? null,
      appBg: cs(app)?.backgroundColor ?? null,
      cardBg: cs(card)?.backgroundColor ?? null,
      cardText: cs(card)?.color ?? null,
      stored: localStorage.getItem('ui-theme'),
      icon: document.querySelector('[data-test="theme-toggle"] .v-icon')?.className.match(/mdi-\S+/)?.[0],
      brand: getComputedStyle(document.querySelector('.app__brand')).color,
      // Фон под брендом: он лежит в шапке, а не на фоне приложения.
      // Раньше проверка сравнивала цвет текста с фоном страницы, из-за
      // чего читаемость считалась неверно в обе стороны.
      brandBg: (() => {
        let el = document.querySelector('.app__brand')
        while (el && el !== document.documentElement) {
          const c = getComputedStyle(el).backgroundColor
          const parts = String(c).match(/[\d.]+/g)
          const alpha = parts && parts.length === 4 ? Number(parts[3]) : 1
          if (parts && alpha > 0.85) return c
          el = el.parentElement
        }
        return getComputedStyle(document.body).backgroundColor
      })(),
    }
  })

test.describe('Тема оформления', () => {
  test('переключается без перезагрузки и переживает её', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    await auth(page)
    await page.goto(BASE + '/#/user/list', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)

    // Начинаем со светлой явно, чтобы результат не зависел от
    // системной настройки машины.
    await page.evaluate(() => localStorage.setItem('ui-theme', 'light'))
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)

    const before = await snapshot(page)
    expect(before.html, 'стартуем со светлой').toBe('light')
    expect(before.icon, 'иконка светлой темы').toBe('mdi-white-balance-sunny')

    // Один клик: светлая -> тёмная.
    await page.locator('[data-test="theme-toggle"]').click()
    await page.waitForTimeout(700)

    const dark = await snapshot(page)

    expect(dark.html, 'атрибут темы на <html>').toBe('dark')
    expect(dark.appTheme, 'тема Vuetify переключилась').toBe('v-theme--dark')
    expect(dark.icon, 'иконка тёмной темы').toBe('mdi-weather-night')
    expect(dark.stored, 'выбор сохранён').toBe('dark')

    // Главное: переключилось ВСЁ, а не один слой. Раньше фон
    // приложения оставался светлым до перезагрузки.
    expect(dark.appBg, 'фон приложения стал тёмным').not.toBe(before.appBg)
    expect(dark.cardBg, 'карточка стала тёмной').not.toBe(before.cardBg)
    expect(dark.cardText, 'текст в карточке стал светлым').not.toBe(before.cardText)

    // Читаемость: тёмный фон и светлый текст в карточке.
    const cr = contrast(dark.cardText, dark.cardBg)
    expect(cr, 'контраст текста в карточке в тёмной теме').toBeGreaterThan(3)

    // Бренд в шапке: белый на светло-синем давал 2.46 при норме 4.5.
    const brandCr = contrast(dark.brand, dark.brandBg)
    expect(brandCr, 'контраст названия в шапке').toBeGreaterThanOrEqual(3)

    // Выбор переживает перезагрузку.
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)

    const after = await snapshot(page)
    expect(after.html, 'тема восстановлена после перезагрузки').toBe('dark')
    expect(after.appTheme).toBe('v-theme--dark')
    expect(after.appBg, 'фон остался тёмным').toBe(dark.appBg)

    // Третье состояние: «как в системе».
    await page.locator('[data-test="theme-toggle"]').click()
    await page.waitForTimeout(700)
    const auto = await snapshot(page)
    expect(auto.stored, 'третье состояние — auto').toBe('auto')
    expect(auto.icon, 'иконка авто-режима').toBe('mdi-theme-light-dark')

    expect(errors, 'ошибок JS быть не должно').toEqual([])
  })

  test('тёмная тема читаема на основных разделах', async ({ page }) => {
    await auth(page)
    await page.goto(BASE + '/#/user/list', { waitUntil: 'domcontentloaded' })
    await page.evaluate(() => localStorage.setItem('ui-theme', 'dark'))

    const routes = [
      ['/#/', 'главная'],
      ['/#/groups/list', 'группы'],
      ['/#/courses/list', 'курсы'],
      ['/#/categories', 'категории'],
      ['/#/my/learning', 'моё обучение'],
      ['/#/dashboard', 'кабинет'],
      ['/#/calendar', 'календарь'],
    ]

    for (const [hash, name] of routes) {
      await page.goto(BASE + hash, { waitUntil: 'domcontentloaded' })
      await page.reload({ waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1300)

      const s = await snapshot(page)
      expect(s.html, `${name}: тёмная тема применена`).toBe('dark')

      const cr = contrast(s.cardText, s.cardBg)
      if (cr !== null) {
        expect(cr, `${name}: контраст карточки`).toBeGreaterThan(3)
      }
    }
  })
})

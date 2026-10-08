// @ts-check
/**
 * Брендинг: знак, название и заголовки (Playwright).
 *
 * Проверяет то, что видно только в браузере:
 *  - знак рисуется в шапке и имеет размер (не схлопнут);
 *  - заголовок вкладки соответствует разделу, а не всегда один и тот же;
 *  - смена языка меняет и заголовок, и название системы в шапке;
 *  - иконка вкладки отдаётся и является SVG.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const ADMIN = { fio: 'Администратор', password: '123' }

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
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )
}

test.describe('Брендинг', () => {
  test('знак и название в шапке, заголовок вкладки соответствует разделу', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    await auth(page)
    await page.goto(BASE + '/#/user/list', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)

    // Знак отрисован и не схлопнут.
    const mark = page.locator('.u-brand__mark')
    await expect(mark, 'знак в шапке').toBeVisible()

    const box = await mark.boundingBox()
    expect(box.width, 'ширина знака').toBeGreaterThan(16)
    expect(box.height, 'высота знака').toBeGreaterThan(16)

    // Название и ссылка на главную.
    await expect(page.locator('[data-test="app-brand"]')).toContainText('LMS')
    await expect(page.locator('.app__brand')).toHaveAttribute('href', '#/')

    // Иконка вкладки отдаётся как SVG.
    const icon = await page.request.get(BASE + '/favicon.svg')
    expect(icon.ok(), 'иконка отдаётся').toBeTruthy()
    expect(icon.headers()['content-type']).toContain('svg')

    // Заголовок меняется по разделам.
    const titles = new Map()
    for (const [hash, expected] of [
      ['/#/user/list', 'Пользователи'],
      ['/#/groups/list', 'Группы'],
      ['/#/courses/list', 'Курсы'],
      ['/#/dashboard', 'Личный кабинет'],
      ['/#/my/exams', 'Экзамены'],
    ]) {
      await page.goto(BASE + hash, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(800)

      const title = await page.title()
      titles.set(hash, title)

      expect(title, `заголовок для ${expected}`).toContain(expected)
      expect(title, 'заголовок содержит название системы').toContain('LMS')
      expect(title, 'в заголовке не должно быть сырого шаблона').not.toContain('%(')
      expect(title, 'не должно быть неподставленного {page}').not.toContain('{page}')
    }

    // Раньше у всех страниц было одно и то же название — теперь нет.
    expect(new Set(titles.values()).size, 'заголовки разделов различаются').toBe(titles.size)

    expect(errors, 'ошибок JS быть не должно').toEqual([])
  })

  test('смена языка меняет и заголовок, и название в шапке', async ({ page }) => {
    await auth(page)
    await page.goto(BASE + '/#/groups/list', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)

    expect(await page.title()).toBe('LMS — Группы')

    /*
     * Переключатель языка в футере — кнопки со значками RU/EN и
     * aria-pressed. Раньше это был голый текст «eng/rus» без подписи
     * для скринридера и без отметки текущего языка.
     */
    await page.getByRole('button', { name: 'English' }).click()
    await page.waitForTimeout(1200)

    // Заголовок обязан ехать за языком: хук маршрутизатора при смене
    // языка не срабатывает, нужен отдельный следитель.
    expect(await page.title()).toBe('LMS — Groups')
    await expect(page.locator('[data-test="app-brand"]')).toContainText('Learning management system')

    await page.getByRole('button', { name: 'Русский' }).click()
    await page.waitForTimeout(1200)
    expect(await page.title()).toBe('LMS — Группы')
  })
})

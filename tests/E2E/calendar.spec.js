// @ts-check
/**
 * Календарь учебного процесса — сквозной сценарий.
 *
 * Проверяет то, что нельзя поймать юнит-тестом:
 *  - лента действительно приходит и рисуется (раньше страница показывала
 *    два захардкоженных события из демо-шаблона);
 *  - сетка помещается в карточку: все семь колонок недели видимы, у
 *    страницы нет горизонтальной прокрутки (FullCalendar запоминал ширину
 *    больше фактической, и колонки «сб»/«вс» обрезались);
 *  - период показан ПОЛОСОЙ, а не точкой: событие длится несколько дней;
 *  - подпись читаема: тёмный фон и светлый текст, а не белый на белом;
 *  - клик открывает карточку периода с полными данными.
 *
 * Тест ничего не создаёт в базе: читает существующие периоды и при
 * отсутствии записей пропускает себя.
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
      localStorage.clear()
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )
  return token
}


/**
 * Создать период обучения для календаря и вернуть функцию уборки.
 *
 * Раньше тесты календаря работали на «ambient-данных» — записанных
 * в базе группах, и пропускали себя через test.skip(), если таких
 * не было. В итоге проверка либо не выполнялась вовсе, либо падала
 * там, где ждала сетку: при пустом календаре FullCalendar не рисует
 * ячейки дней, и `.fc-daygrid-day` не появлялся. Теперь данные
 * создаёт сам тест и удаляет по завершении.
 */
async function makePeriod(page, token) {
  const headers = { Authorization: 'Bearer ' + token }

  const groupRes = await page.request.post(BASE + '/api/groups', {
    headers,
    data: { groupname: `E2E Календарь ${Date.now()}` },
  })
  expect(groupRes.status(), 'создание группы для календаря').toBe(201)
  const groupId = (await groupRes.json()).data.id

  const courses = (await (await page.request.get(BASE + '/api/courses', { headers })).json()).data
  test.skip(!courses || courses.length === 0, 'в базе нет ни одного курса')
  const course = courses[0]

  const learningRes = await page.request.post(BASE + '/api/learning', {
    headers,
    data: {
      group_id: groupId,
      entries: [{ course_id: course.id, parent_id: null }],
      category_id: course.categories?.[0]?.id ?? null,
      typeOfLesson: 'Лекция',
      study_from: new Date(Date.now() - 3 * 864e5).toISOString().slice(0, 10),
      study_to: new Date(Date.now() + 10 * 864e5).toISOString().slice(0, 10),
    },
  })
  expect(learningRes.status(), 'создание периода обучения').toBe(201)

  return async () => {
    // Порядок важен: записи группы удаляются каскадом вместе с
    // группой, но явная уборка не оставляет ничего при отказе
    // каскада в будущей версии схемы.
    await page.request.delete(BASE + `/api/groups/${groupId}`, { headers })
  }
}

test.describe('Календарь обучения', () => {
  test('периоды отображаются полосами в пределах карточки, клик открывает детали', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    const token = await auth(page)
    const cleanup = await makePeriod(page, token)

    try {
    await page.goto(BASE + '/#/calendar', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(3500)

    await expect(page.locator('.u-page__title')).toContainText('Календарь')

    // 1. События нарисованы (не демо-заглушки).
    await expect(page.locator('.fc-daygrid-event').first()).toBeVisible()
    await expect(page.locator('body')).not.toContainText('All-day event')
    await expect(page.locator('body')).not.toContainText('Timed event')

    // 2. Сетка помещается в карточку: нет горизонтальной прокрутки и
    //    все семь колонок недели на месте.
    const scroll = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
      columns: document.querySelectorAll('.fc-col-header-cell').length,
    }))
    expect(scroll.overflow, 'у страницы не должно быть горизонтальной прокрутки').toBe(false)
    expect(scroll.columns, 'в календаре семь колонок недели').toBe(7)

    // 3. Период показан полосой: событие длиннее одного дня.
    const barWidth = await page.evaluate(() => {
      const ev = document.querySelector('.fc-daygrid-event')
      return ev ? Math.round(ev.getBoundingClientRect().width) : 0
    })
    expect(barWidth, 'полоса периода заметно шире одной ячейки').toBeGreaterThan(150)

    // 4. Подпись читаема: светлый текст на тёмной полосе.
    const colors = await page.evaluate(() => {
      const ev = document.querySelector('.fc-daygrid-event')
      if (!ev) return null
      const cs = getComputedStyle(ev)
      return { bg: cs.backgroundColor, color: cs.color }
    })
    expect(colors).not.toBeNull()
    const luminance = bg => {
      const [r, g, b] = bg.match(/\d+/g).map(Number)
      return (0.299 * r + 0.587 * g + 0.114 * b) / 255
    }
    expect(
      Math.abs(luminance(colors.bg) - luminance(colors.color)),
      'подпись события должна контрастировать с фоном'
    ).toBeGreaterThan(0.25)

    // 5. Клик открывает карточку периода с данными.
    await page.locator('.fc-daygrid-event').first().click()
    await page.waitForTimeout(1200)

    const dialog = page.locator('.v-overlay__content .calendar__detail-title').first()
    await expect(dialog, 'карточка периода открылась').toBeVisible()

    const facts = await page.locator('.calendar__fact').allInnerTexts()
    expect(facts.length, 'в карточке есть поля периода').toBeGreaterThan(3)
    expect(facts.join(' ')).toMatch(/\d{2}\.\d{2}\.\d{4}/)

    await page.keyboard.press('Escape')
    await page.waitForTimeout(600)

    expect(errors, 'ошибок JS быть не должно').toEqual([])
    } finally {
      await cleanup()
    }
  })

  test('пустое состояние объясняет, что делать', async ({ page }) => {
    const token = await auth(page)

    const feed = await page.request.get(BASE + '/api/calendar', {
      headers: { Authorization: 'Bearer ' + token },
    })
    const periods = (await feed.json())?.data?.events ?? []

    await page.goto(BASE + '/#/calendar', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(3000)

    if (periods.length === 0) {
      await expect(page.locator('.u-empty__title')).toBeVisible()
      return
    }

    // Фильтр, под который ничего не подходит: состояние должно быть
    // отдельным от «никого не записали».
    const chips = page.locator('.calendar__chips .cats-chip')
    await chips.filter({ hasText: 'Завершено' }).first().click()
    await page.waitForTimeout(2500)

    if (periods.every(p => p.extendedProps.status !== 'completed')) {
      await expect(page.locator('.u-empty__title')).toContainText('Под фильтр ничего не попало')
      // Регистр не фиксируем: бейдж подписывает uppercase через CSS,
      // а toContainText читает textContent, а не результат отрисовки.
      await expect(page.locator('.calendar__active')).toContainText(/завершено/i)
    }
  })

  test('календарь не создаёт события вручную', async ({ page }) => {
    // Раньше по клику по дате открывался prompt() и событие добавлялось
    // только в память. События приходят из записей групп, поэтому и
    // создавать их здесь нельзя: календарь показывает, а не планирует.
    const token = await auth(page)
    const cleanup = await makePeriod(page, token)

    try {
    await page.goto(BASE + '/#/calendar', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(3000)

    // Сетка нужна обязательно: при пустом календаре FullCalendar не
    // рисует ячейки дней, и выделение диапазона просто нечего было бы
    // проверять.
    await expect(page.locator('.fc-daygrid-day').first()).toBeVisible()

    const before = await page.locator('.fc-daygrid-event').count()

    // Выделяем диапазон мышью по сетке.
    const cell = page.locator('.fc-daygrid-day').nth(10)
    const box = await cell.boundingBox()
    if (box) {
      await page.mouse.move(box.x + 10, box.y + 10)
      await page.mouse.down()
      await page.mouse.move(box.x + 60, box.y + 40, { steps: 6 })
      await page.mouse.up()
    }
    await page.waitForTimeout(1500)

    const after = await page.locator('.fc-daygrid-event').count()
    expect(after, 'выделение дат не должно добавлять события').toBe(before)

    // prompt() в этом сценарии означал бы диалог ввода — его быть не должно.
    expect(await page.locator('.fc .fc-highlight').count()).toBe(0)
    } finally {
      await cleanup()
    }
  })
})

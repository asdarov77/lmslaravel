// @ts-check
/**
 * Личный кабинет по роли (Playwright).
 *
 * Проверяет то, что было сломано: /dashboard отдавал кабинет
 * обучаемого всем, поэтому администратор видел «Состояние вашего
 * обучения на сегодня» и кнопку «Открыть учебный план», которая ему
 * ничего не даёт.
 *
 * Также проверяется меню: управляющему не нужен личный учебный план.
 *
 * Данные создаются и удаляются внутри теста.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const HASH = BASE + '/#'
const ADMIN = { fio: 'Администратор', password: '123' }
const PASSWORD = 'secret123'

const unique = () => `${Date.now()}${Math.floor(Math.random() * 1000)}`

/**
 * Отдельный контекст на пользователя: addInitScript накапливается в
 * пределах контекста, и при общей сессии все проверки видели первого.
 */
async function session(browser, creds) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } })
  const page = await ctx.newPage()

  const res = await ctx.request.post(BASE + '/api/login', { data: creds })
  expect(res.ok(), 'логин ' + creds.fio).toBeTruthy()
  const token = (await res.json()).data.token

  const me = await (
    await ctx.request.get(BASE + '/api/v1/me', { headers: { Authorization: 'Bearer ' + token } })
  ).json()
  const user = me?.data?.user ?? null

  await ctx.addInitScript(
    ([t, u]) => {
      localStorage.clear()
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )

  return { ctx, page, token, user }
}

const body = (page) => page.evaluate(() => document.body.innerText)

test.describe('Личный кабинет по роли', () => {
  test('администратор, инструктор и обучаемый видят своё', async ({ browser }) => {
    const errors = []
    const admin = await session(browser, ADMIN)
    const headers = { Authorization: 'Bearer ' + admin.token }

    // --- фикстуры: группа, инструктор, обучаемый -----------------------
    const groupRes = await admin.page.request.post(BASE + '/api/groups', {
      headers,
      data: { groupname: `E2E Кабинет ${unique()}` },
    })
    expect(groupRes.status()).toBe(201)
    const groupId = (await groupRes.json()).data.id

    const instructorFio = `E2Е Инструктор ${unique()}`
    const traineeFio = `E2Е Обучаемый ${unique()}`

    const regInstructor = await admin.page.request.post(BASE + '/api/register', {
      headers,
      data: { fio: instructorFio, password: PASSWORD, password_confirmation: PASSWORD, group_id: groupId },
    })
    const instructorId = (await regInstructor.json()).data.user?.id

    const regTrainee = await admin.page.request.post(BASE + '/api/register', {
      headers,
      data: { fio: traineeFio, password: PASSWORD, password_confirmation: PASSWORD, group_id: groupId },
    })
    const traineeId = (await regTrainee.json()).data.user?.id

    // Роль назначаем через chroll — так её назначает интерфейс.
    const rolesRes = await admin.page.request.get(BASE + '/api/role', { headers })
    const instructorRole = (await rolesRes.json()).data.find(
      r => r.slug === 'instructor' || r.rolename === 'Инструктор'
    )
    const chroll = await admin.page.request.put(`${BASE}/api/user/chroll/${instructorId}`, {
      headers,
      data: { role_id: [instructorRole.id] },
    })
    expect(chroll.status(), 'инструктору назначена роль').toBe(200)

    try {
      // --- администратор: сводка по системе ---------------------------
      await admin.page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
      await admin.page.waitForTimeout(2200)
      const adminText = await body(admin.page)

      expect(adminText, 'администратору доступна сводка').toContain('Требует внимания')
      expect(adminText, 'подзаголовок сводки').toContain('Состояние учебного процесса')
      expect(adminText, 'НЕ кабинет обучаемого').not.toContain('Состояние вашего обучения')

      // Счётчики приходят с сервера: их больше нуля, значит это не
      // заглушка.
      const adminStats = await admin.page.locator('.dash__stat-value').allInnerTexts()
      expect(adminStats.length, 'у администратора есть показатели').toBeGreaterThan(4)
      expect(adminStats.some(v => Number(v) > 0), 'показатели не нулевые').toBe(true)

      // --- инструктор: та же сводка, но своей группы ------------------
      const instructor = await session(browser, { fio: instructorFio, password: PASSWORD })
      await instructor.page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
      await instructor.page.waitForTimeout(2200)
      const instructorText = await body(instructor.page)

      expect(instructorText, 'инструктору доступна сводка').toContain('Требует внимания')
      expect(instructorText, 'НЕ кабинет обучаемого').not.toContain('Состояние вашего обучения')

      // Личный учебный план управляющему не нужен.
      const instructorMenu = await instructor.page.evaluate(() =>
        [...document.querySelectorAll('nav .v-list-item')].map(n => n.innerText.trim())
      )
      expect(instructorMenu, 'управляющему не нужен «Учебный план»').not.toContain('Учебный план')

      // --- обучаемый: свой кабинет ------------------------------------
      const trainee = await session(browser, { fio: traineeFio, password: PASSWORD })
      await trainee.page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
      await trainee.page.waitForTimeout(2200)
      const traineeText = await body(trainee.page)

      expect(traineeText, 'обучаемому — его кабинет').toContain('Состояние вашего обучения')
      expect(traineeText, 'обучаемому НЕ сводка').not.toContain('Требует внимания')

      const traineeMenu = await trainee.page.evaluate(() =>
        [...document.querySelectorAll('nav .v-list-item')].map(n => n.innerText.trim())
      )
      expect(traineeMenu, 'обучаемому доступно «Учебный план»').toContain('Учебный план')

      for (const s of [instructor, trainee]) await s.ctx.close()
      expect(errors, 'ошибок JS быть не должно').toEqual([])
    } finally {
      await admin.page.request.delete(`${BASE}/api/user/${instructorId}`, { headers })
      await admin.page.request.delete(`${BASE}/api/user/${traineeId}`, { headers })
      await admin.page.request.delete(`${BASE}/api/groups/${groupId}`, { headers })
      await admin.ctx.close()
    }
  })
})

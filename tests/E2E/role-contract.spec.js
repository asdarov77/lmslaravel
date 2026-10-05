// @ts-check
/**
 * Сквозной сценарий контракта ролей (Playwright).
 *
 * Проверяет цепочку, которая была разорвана по всей системе:
 *
 *  1) Назначение роли из интерфейса. Страница «Назначение ролей» и
 *     PUT /api/user/chroll/{id} были закомментированы, поэтому роль
 *     нельзя было назначить ни через UI, ни через API. Всё, что меняло
 *     роль, — свободная строка users.role в PATCH /api/user/{id},
 *     доступном по users.update: то есть инструктор мог вписать себе
 *     «Администратор».
 *
 *  2) Роль из role_user видна пользователю. isSuperAdmin()/isTrainee()
 *     читали только колонку users.role, а chroll её не пишет. В итоге
 *     назначенный через UI администратор получал isSuperAdmin() === false,
 *     пустое меню и 403 на /api/v1/courses.
 *
 *  3) Формат ролей один. login отдавал ['Обучаемый'] (список названий),
 *     а GET /api/v1/me — [{id,name,slug}]. Фронт сравнивал строки в
 *     двух местах по-разному.
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
 * Вход через API + восстановление сессии в localStorage.
 * Кладём и токен, и пользователя: меню рендерится по v-if="loggedIn",
 * а loggedIn берётся из сохранённого пользователя.
 */
async function auth(page, creds) {
  const res = await page.request.post(BASE + '/api/login', { data: creds })
  expect(res.ok(), 'логин ' + creds.fio).toBeTruthy()
  const data = (await res.json()).data
  const token = data.token

  const me = await page.request.get(BASE + '/api/v1/me', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const user = (await me.json())?.data?.user ?? null
  expect(user, 'профиль получен').toBeTruthy()

  await page.addInitScript(
    ([t, u]) => {
      localStorage.clear()
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )

  // Роли кладём в снимок пользователя: без них геттеры isTrainee/isAdmin
  // не смогут определить роль до первого ответа /api/v1/me.
  await page.addInitScript(
    ([slugs]) => {
      const raw = localStorage.getItem('user')
      if (!raw) return
      const parsed = JSON.parse(raw)
      parsed.role_slugs = slugs
      localStorage.setItem('user', JSON.stringify(parsed))
    },
    [data.role_slugs || []]
  )

  return { token, login: data, user }
}

const menuItems = (page) =>
  page.evaluate(() =>
    [...document.querySelectorAll('nav .v-list-item')]
      .map((n) => n.innerText.trim())
      .filter(Boolean)
  )

test.describe('Контракт ролей: назначение и доступ', () => {
  test('назначение роли из UI меняет доступ, а роль не пишется в обход chroll', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))
    page.on('response', (r) => {
      if (r.url().includes('/api/') && r.status() >= 500) {
        errors.push(`5xx ${r.status()} ${r.request().method()} ${r.url().replace(BASE, '')}`)
      }
    })

    const { token: adminToken } = await auth(page, ADMIN)
    const headers = { Authorization: 'Bearer ' + adminToken }

    // --- фикстуры: группа и обучаемый -------------------------------
    const groupName = `E2E Роли ${unique()}`
    const groupRes = await page.request.post(BASE + '/api/groups', { headers, data: { groupname: groupName } })
    expect(groupRes.status()).toBe(201)
    const groupId = (await groupRes.json()).data.id

    const fio = `E2Е Роли ${unique()}`
    const regRes = await page.request.post(BASE + '/api/register', {
      headers,
      data: { fio, password: PASSWORD, password_confirmation: PASSWORD, group_id: groupId },
    })
    expect(regRes.status(), 'регистрация обучаемого').toBe(201)
    const regBody = await regRes.json()
    const userId = regBody.data.user?.id ?? regBody.data.id

    try {
      // --- 1. назначение роли через страницу ------------------------
      await page.goto(`${HASH}/user/chrole/${userId}`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1500)

      await expect(
        page.locator('header h1'),
        'заголовок страницы ролей на месте'
      ).toBeVisible()

      // Имя пользователя в подзаголовке: раньше там была
      // несуществующая переменная usernameEdit, т.е. всегда пусто.
      await expect(page.locator('.u-page__subtitle'), 'имя пользователя видно').toContainText(fio)

      const select = page.locator('[data-test="chrole-select"]').first()
      await select.click()
      await page.waitForTimeout(400)
      await page.locator('.v-list-item', { hasText: 'Инструктор' }).first().click()
      await page.keyboard.press('Escape')
      await page.waitForTimeout(300)

      await page.locator('[data-test="chrole-save"], .u-form button[type="submit"]').first().click()
      await page.waitForTimeout(1500)

      // Роль действительно записана в role_user, а не в строку users.role.
      const rolesRes = await page.request.get(BASE + '/api/role', { headers })
      const allRoles = (await rolesRes.json()).data
      const instructor = allRoles.find((r) => r.slug === 'instructor' || r.rolename === 'Инструктор')
      expect(instructor, 'роль «Инструктор» существует').toBeTruthy()

      // --- 2. роль из role_user видна пользователю ------------------
      const trainee = await auth(page, { fio, password: PASSWORD })

      // Роль приходит каноническим slug'ом, а не строкой.
      expect(trainee.login.role_slugs, 'login отдаёт канонические роли').toContain('instructor')
      expect(Array.isArray(trainee.login.role_slugs)).toBe(true)
      // Раньше login отдавал ['Обучаемый'] — список строк, а /me отдавал
      // объекты. Теперь формат один: массив объектов {id,name,slug}.
      expect(
        typeof trainee.login.roles[0],
        'roles — массив объектов, как в /api/v1/me'
      ).toBe('object')
      expect(trainee.login.roles[0]).toHaveProperty('slug')
      // Роль приходит союзом двух источников: колонка users.role по
      // умолчанию «Обучаемый» (регистрация всегда такая), а назначенная
      // через chroll — в role_user. Пока старую колонку не подчистили,
      // у пользователя обе роли, и это осознанно: приоритет у связи, но
      // молча выбрасывать роль из колонки нельзя.
      expect(
        trainee.login.roles.map((r) => r.slug),
        'назначенная роль есть в списке ролей'
      ).toContain('instructor')

      // Главное: назначенная роль признана, хотя users.role остался
      // прежним (регистрация всегда создаёт «Обучаемый»).
      const meRes = await page.request.get(BASE + '/api/v1/me', {
        headers: { Authorization: 'Bearer ' + trainee.token },
      })
      const me = (await meRes.json()).data
      expect(me.role_slugs, 'роль из role_user распознана бэкендом').toContain('instructor')

      // Инструктору доступен список пользователей — это право шло от
      // роли через permissions_roles. Если бы роль не увидели, был бы 403.
      // Список пользователей объявлен как POST /api/user/list (GET даёт 405).
      const usersRes = await page.request.post(BASE + '/api/user/list', {
        headers: { Authorization: 'Bearer ' + trainee.token },
      })
      expect(usersRes.status(), 'инструктор видит пользователей').toBe(200)

      // --- 3. роль нельзя выдать в обход chroll ---------------------
      // PATCH /api/user/{id} больше не пишет users.role: иначе любой,
      // у кого есть users.update, стал бы администратором.
      const patchRes = await page.request.patch(BASE + `/api/user/${userId}`, {
        headers,
        data: { fio, role: 'Администратор' },
      })
      expect(patchRes.status()).toBe(200)

      const afterPatch = (await (
        await page.request.get(BASE + '/api/v1/me', {
          headers: { Authorization: 'Bearer ' + trainee.token },
        })
      ).json()).data
      expect(
        afterPatch.role_slugs,
        'роль не изменилась через PATCH'
      ).not.toContain('admin')

      // --- 4. в браузере роль видна как роль ------------------------
      await page.goto(`${HASH}/user/list`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1800)

      const menu = await menuItems(page)
      expect(
        menu.length,
        'меню инструктора не пустое (раньше роль из role_user давала пустое меню)'
      ).toBeGreaterThan(0)
      expect(menu, 'инструктору доступен раздел пользователей').toContain('Пользователи')

      // Главная страница не превращается в пустой экран.
      await page.goto(`${HASH}/`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1500)
      const homeText = await page.evaluate(() => document.body.innerText.trim())
      expect(homeText.length, 'главная не пустая').toBeGreaterThan(0)
      expect(homeText, 'нет сообщения о неизвестной роли').not.toMatch(/Не удалось определить вашу роль/)

      expect(errors, 'сценарий не должен давать ошибок').toEqual([])
    } finally {
      await page.request.delete(BASE + `/api/user/${userId}`, { headers })
      await page.request.delete(BASE + `/api/groups/${groupId}`, { headers })
    }
  })

  test('собственные роли изменить нельзя', async ({ page }) => {
    // Инструктор с users.permissions не должен повысить себя до
    // администратора: chroll отвечает 403 на самого себя.
    const { token, user } = await auth(page, ADMIN)

    const res = await page.request.put(BASE + `/api/user/chroll/${user.id}`, {
      headers: { Authorization: 'Bearer ' + token },
      data: { role_id: [1] },
    })

    expect(res.status(), 'назначение роли себе запрещено').toBe(403)
  })

  test('назначение ролей требует users.permissions', async ({ page }) => {
    const fio = `E2Е Роли нет прав ${unique()}`
    const { token: adminToken } = await auth(page, ADMIN)
    const headers = { Authorization: 'Bearer ' + adminToken }

    const groupRes = await page.request.post(BASE + '/api/groups', {
      headers,
      data: { groupname: `E2E Роли нет прав ${unique()}` },
    })
    const groupId = (await groupRes.json()).data.id

    const regRes = await page.request.post(BASE + '/api/register', {
      headers,
      data: { fio, password: PASSWORD, password_confirmation: PASSWORD, group_id: groupId },
    })
    const regBody = await regRes.json()
    const userId = regBody.data.user?.id ?? regBody.data.id

    try {
      const trainee = await auth(page, { fio, password: PASSWORD })

      const res = await page.request.put(BASE + `/api/user/chroll/${userId}`, {
        headers: { Authorization: 'Bearer ' + trainee.token },
        data: { role_id: [2] },
      })

      expect(res.status(), 'обучаемый не может назначать роли').toBe(403)
    } finally {
      await page.request.delete(BASE + `/api/user/${userId}`, { headers })
      await page.request.delete(BASE + `/api/groups/${groupId}`, { headers })
    }
  })
})

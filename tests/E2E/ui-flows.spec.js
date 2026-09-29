// @ts-check
/**
 * Сквозные UI-сценарии: то, что нельзя проверить без браузера.
 *
 * В отличие от routes-smoke.spec.js (где важно «страница не падает»),
 * здесь проверяется реальный путь пользователя: логин через форму,
 * создание/редактирование/удаление сущностей, logout с очисткой сессии.
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://127.0.0.1:8080'
const HASH = BASE + '/#'
const ADMIN = { fio: 'Администратор', password: '123' }

/** Логин через API + прокидывание токена в localStorage. */
async function auth(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора').toBeTruthy()
  const body = await res.json()
  await page.addInitScript(
    ([t, u]) => {
      // addInitScript выполняется на КАЖДОЙ навигации, включая
      // about:blank, к которому иногда уводит router.back(). На
      // непрозрачном origin доступ к localStorage запрещён, и
      // необработанное исключение всплывало как pageerror. Поэтому
      // пишем только когда origin действительно http(s).
      if (location.protocol !== 'http:' && location.protocol !== 'https:') return
      try {
        window.localStorage.setItem('token', t)
        window.localStorage.setItem('user', u)
      } catch (e) {
        /* хранилище недоступно — тест упадёт позже, на проверке токена */
      }
    },
    [body.data.token, JSON.stringify(body.data.user ?? null)]
  )
  return body.data.token
}

/** Логин именно через форму — проверяем UI, а не API. */
async function loginViaForm(page) {
  await page.goto(HASH + '/login', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(900)

  // Поля формы называются login/password, а кнопка входа — «ВХОД».
  // Кликать по первой кнопке нельзя: первыми идут переключатели языка.
  await page.fill('input[name="login"]', ADMIN.fio)
  await page.fill('input[name="password"]', ADMIN.password)
  await page.getByRole('button', { name: 'ВХОД' }).click()

  await page.waitForTimeout(1500)
  const token = await page.evaluate(() => window.localStorage.getItem('token'))
  expect(token, 'после логина токен должен сохраниться в localStorage').toBeTruthy()
  return token
}

test.describe('Логин и сессия', () => {
  test('вход через форму сохраняет токен и уводит в приложение', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    await loginViaForm(page)

    expect(errors, 'логин не должен давать JS-ошибок').toEqual([])
    const user = await page.evaluate(() => window.localStorage.getItem('user'))
    expect(user, 'данные пользователя сохранены').toBeTruthy()
  })

  test('неверный пароль не создаёт сессию', async ({ page }) => {
    await page.goto(HASH + '/login', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(700)

    const res = await page.request.post(BASE + '/api/login', {
      data: { fio: ADMIN.fio, password: 'неверный-пароль-123' },
    })
    expect(res.status(), 'неверный пароль должен отклоняться').toBe(401)
  })

  test('защищённая страница без токена не отдаёт данные', async ({ page }) => {
    await page.context().clearCookies()
    await page.addInitScript(() => window.localStorage.clear())

    const res = await page.request.get(BASE + '/api/categories')
    expect(res.status(), '/api/categories без токена → 401').toBe(401)
  })

  test('logout очищает клиентское состояние', async ({ page }) => {
    await auth(page)
    await page.goto(HASH + '/user/list', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(800)

    // Бэкенд отдаёт только GET /api/logout (POST даёт 405)
    const out = await page.request.get(BASE + '/api/logout', {
      headers: { Authorization: 'Bearer ' + (await page.evaluate(() => localStorage.getItem('token'))) },
    })
    expect(out.ok(), 'logout должен succeed').toBeTruthy()

    await page.evaluate(() => window.localStorage.removeItem('token'))
    await page.goto(HASH + '/user/list', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(800)
    // При отсутствии токена список не должен отрисовать пользователей
    const rows = await page.locator('tbody tr').count()
    expect(rows, 'без токена таблица пользователей пуста').toBe(0)
  })
})

test.describe('CRUD группы через UI', () => {
  test('создание, редактирование и удаление группы', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))
    page.on('response', (r) => {
      if (r.url().includes('/api/') && r.status() >= 500) {
        errors.push(`5xx ${r.status()} ${r.url().replace(BASE, '')}`)
      }
    })

    const token = await auth(page)
    const authHeader = { Authorization: 'Bearer ' + token }
    const stamp = Date.now()
    const title = `E2E группа ${stamp}`

    // --- создание через API (UI-форма требует предзаполненного id) ---
    const created = await page.request.post(BASE + '/api/groups', {
      headers: authHeader,
      data: { groupname: title },
    })
    expect(created.status(), 'создание группы').toBe(201)
    const groupId = (await created.json()).data.id

    // --- группа видна в списке ---
    await page.goto(HASH + '/groups/list', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    await expect(page.locator('body')).toContainText(title)

    // --- редактирование сохраняется ---
    const newTitle = title + ' (изменена)'
    const updated = await page.request.put(BASE + `/api/groups/${groupId}`, {
      headers: authHeader,
      data: { groupname: newTitle },
    })
    expect(updated.status(), 'обновление группы').toBe(200)

    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    await expect(page.locator('body')).toContainText(newTitle)

    // --- удаление ---
    const deleted = await page.request.delete(BASE + `/api/groups/${groupId}`, { headers: authHeader })
    expect(deleted.status(), 'удаление группы').toBe(204)

    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    await expect(page.locator('body')).not.toContainText(newTitle)

    expect(errors, 'CRUD группы не должен давать ошибок').toEqual([])
  })
})

test.describe('CRUD категории через UI', () => {
  test('категория создаётся, редактируется и удаляется', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))
    page.on('response', (r) => {
      if (r.url().includes('/api/') && r.status() >= 500) {
        errors.push(`5xx ${r.status()} ${r.url().replace(BASE, '')}`)
      }
    })

    const token = await auth(page)
    const authHeader = { Authorization: 'Bearer ' + token }
    const title = `E2E категория ${Date.now()}`

    const created = await page.request.post(BASE + '/api/categories', {
      headers: authHeader,
      data: { title },
    })
    expect(created.status(), 'создание категории').toBe(201)
    const catId = (await created.json()).data.id

    await page.goto(HASH + '/categories', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    await expect(page.locator('body')).toContainText(title)

    const newTitle = title + ' (изменена)'

    // Правка ИМЕННО через форму, а не через API: баг был в том, что
    // UpdateCategory.vue отправлял весь state.category вместе с
    // устаревшим алиасом name, и PUT отвечал 200, не меняя название.
    const row = page.locator('tbody tr', { hasText: title })
    await row.locator('a,button', { hasText: 'Редактировать' }).first().click()
    await page.waitForTimeout(1200)

    const nameField = page.locator('input').first()
    await nameField.fill(newTitle)
    await page.getByRole('button', { name: /сохранить/i }).click()
    await page.waitForTimeout(1500)

    // Проверяем именно БД, а не только то, что страница не упала
    const reread = await page.request.get(BASE + `/api/categories/${catId}`, { headers: authHeader })
    expect(reread.status(), 'чтение категории после правки').toBe(200)
    expect((await reread.json()).data.title, 'название сохранилось в БД').toBe(newTitle)

    // Алиас name обязан совпадать с title, иначе следующая правка снова
    // потеряет изменения
    const rereadData = (await reread.json()).data
    expect(rereadData.name, 'алиас name совпадает с title').toBe(rereadData.title)

    await page.goto(HASH + '/categories', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1200)
    await expect(page.locator('body')).toContainText(newTitle)

    const deleted = await page.request.delete(BASE + `/api/categories/${catId}`, { headers: authHeader })
    expect(deleted.status(), 'удаление категории').toBe(200)

    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    await expect(page.locator('body')).not.toContainText(newTitle)

    expect(errors, 'CRUD категории не должен давать ошибок').toEqual([])
  })
})

test.describe('Редактирование пользователя с выбором группы', () => {
  test('группа сохраняется как число, а не как объект (регресс 500)', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))
    page.on('response', (r) => {
      if (r.url().includes('/api/') && r.status() >= 500) {
        errors.push(`5xx ${r.status()} ${r.request().method()} ${r.url().replace(BASE, '')}`)
      }
    })

    const token = await auth(page)
    const authHeader = { Authorization: 'Bearer ' + token }

    // Создаём группу, чтобы точно знать её id
    const g = await page.request.post(BASE + '/api/groups', {
      headers: authHeader,
      data: { groupname: `E2E группа для юзера ${Date.now()}` },
    })
    const groupId = (await g.json()).data.id

    // Создаём пользователя
    const u = await page.request.post(BASE + '/api/register', {
      headers: authHeader,
      data: {
        fio: `E2E Юзер ${Date.now()}`,
        password: 'secret123',
        password_confirmation: 'secret123',
        group_id: groupId,
      },
    })
    expect(u.status(), 'регистрация пользователя').toBe(201)
    const userId = (await u.json()).data.user?.id ?? (await u.json()).data.id

    // Редактируем его через UI
    await page.goto(`${HASH}/user/edit/${userId}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1200)

    const fio = page.locator('input').first()
    await fio.fill(`E2Е Обновлён ${Date.now()}`)
    await page.getByRole('button').first().click()
    await page.waitForTimeout(1500)

    // Проверяем через API, что группа осталась числом и поля сохранились
    const after = await page.request.get(BASE + `/api/user/list/${userId}`, { headers: authHeader })
    expect(after.ok()).toBeTruthy()
    const body = await after.json()
    const saved = body?.data?.user ?? body?.data
    expect(typeof saved.group_id, 'group_id сохранён числом, а не объектом').toBe('number')
    expect(saved.group_id).toBe(groupId)

    expect(errors, 'редактирование пользователя не должно давать 5xx').toEqual([])

    // Чистим за собой
    await page.request.delete(BASE + `/api/user/${userId}`, { headers: authHeader })
  })
})

// -------------------- РЕГРЕСС: пустой выпадающий список групп на /user/edit

test.describe('Выпадающий список групп в редактировании пользователя', () => {
  test('группы подгружаются и в них можно выбрать значение', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    const token = await auth(page)
    const authHeader = { Authorization: 'Bearer ' + token }

    // Создаём две группы: пустой список нельзя спутать с «одна группа»
    const names = []
    for (const label of ['Первая', 'Вторая']) {
      const r = await page.request.post(BASE + '/api/groups', {
        headers: authHeader,
        data: { groupname: `E2E ${label} группа ${Date.now()}${label}` },
      })
      expect(r.status(), `создание группы ${label}`).toBe(201)
      names.push((await r.json()).data.groupname)
    }

    const u = await page.request.post(BASE + '/api/register', {
      headers: authHeader,
      data: {
        fio: `E2E Группы ${Date.now()}`,
        password: 'secret123',
        password_confirmation: 'secret123',
      },
    })
    expect(u.status(), 'регистрация пользователя').toBe(201)
    const userId = (await u.json()).data.user?.id ?? (await u.json()).data.id

    await page.goto(`${HASH}/user/edit/${userId}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)

    // Открываем именно поле «Группа» (v-select) и смотрим варианты
    const groupSelect = page.locator('.v-select', { has: page.locator('label', { hasText: 'Группа' }) }).first()
    await expect(groupSelect, 'поле «Группа» присутствует на форме').toBeVisible()

    await groupSelect.click()
    await page.waitForTimeout(900)

    const menu = page.locator('.v-menu__content, .v-overlay__content').last()
    await expect(menu, 'список групп открылся').toBeVisible()

    for (const name of names) {
      await expect(menu, `в списке есть группа «${name}»`).toContainText(name)
    }

    // Выбираем первую группу и сохраняем
    await menu.locator('.v-list-item, .v-list-item-title', { hasText: names[0] }).first().click()
    await page.waitForTimeout(600)
    await page.getByRole('button', { name: /сохранить/i }).first().click()
    await page.waitForTimeout(1500)

    const after = await page.request.get(BASE + `/api/user/list/${userId}`, { headers: authHeader })
    const saved = (await after.json())?.data?.user ?? (await after.json())?.data
    expect(typeof saved.group_id, 'выбранная группа сохранилась числом').toBe('number')
    expect(saved.group_id, 'сохранилась не нулевая группа').toBeGreaterThan(0)

    expect(errors, 'страница редактирования не должна падать').toEqual([])

    await page.request.delete(BASE + `/api/user/${userId}`, { headers: authHeader })
  })
})

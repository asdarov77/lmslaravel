// @ts-check
import { test, expect } from '@playwright/test'

/**
 * Отдельный раздел управления правами.
 *
 * Права вынесены из списка пользователей в самостоятельную страницу
 * /permissions. Разрешение на неё есть у администратора и инструктора,
 * а набор доступных действий различается:
 *
 *  - администратор видит всех пользователей и все права;
 *  - инструктор — только свою группу и только те права, которые есть
 *    у него самого (остальные заблокированы и не отправляются);
 *  - обучаемый на страницу не попадает вовсе.
 *
 * Тесты работают с живой dev-базой: набор пользователей и прав задан
 * каталогом config/permissions.php, поэтому проверки идут от фактического
 * ответа API, а не от жёстко заданных id.
 */

const BASE = 'http://127.0.0.1:8080'

/** @param {{fio: string, password: string}} creds */
async function apiLogin(page, creds) {
  const res = await page.request.post(BASE + '/api/login', { data: creds })
  const body = await res.json()
  return body?.data?.token ?? null
}

/** @param {import('@playwright/test').Page} page */
async function auth(page, fio) {
  const token = await apiLogin(page, { fio, password: '123' })
  expect(token, `токен для ${fio}`).toBeTruthy()

  const res = await page.request.get(BASE + '/api/v1/me', {
    headers: { Authorization: 'Bearer ' + token }
  })
  const user = (await res.json())?.data?.user ?? null

  await page.addInitScript(
    ([t, u]) => {
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )
  return { token, user }
}

/** Каталог прав глазами администратора — эталон для сравнения. */
async function catalogAsAdmin(page) {
  const { token } = await auth(page, 'Администратор')
  const res = await page.request.get(BASE + '/api/permissions/catalog', {
    headers: { Authorization: 'Bearer ' + token }
  })
  const groups = (await res.json())?.data ?? []
  return groups.flatMap((g) => g.permissions.map((p) => ({ ...p, group: g.name })))
}

/**
 * Подбирает учётку по роли: у каждой роли свои права, а сравнивать
 * интерфейсы нужно именно между ролями.
 *
 * Список берём с /api/v1/users — он отдаёт все поля пользователя.
 * POST /api/user/list фильтрует по области видимости и для ряда ролей
 * возвращает пустоту, что для этой задачи бесполезно.
 *
 * @param {'Инструктор'|'Обучаемый'} role
 */
async function findUserByRole(page, adminToken, role) {
  const res = await page.request.get(BASE + '/api/v1/users', {
    headers: { Authorization: 'Bearer ' + adminToken }
  })
  const users = (await res.json())?.data ?? []
  const candidates = users.filter((u) => u.role === role)

  for (const user of candidates) {
    if (!user.fio) continue
    const token = await apiLogin(page, { fio: user.fio, password: '123' })
    if (token) return { fio: user.fio, token, id: user.id }
  }

  return null
}

test.describe('раздел управления правами', () => {
  test('администратору доступны все разделы и все пользователи', async ({ page }) => {
    const expected = await catalogAsAdmin(page)
    test.skip(expected.length === 0, 'каталог прав пуст — проверять нечего')

    await auth(page, 'Администратор')
    await page.goto(`${BASE}/#/permissions`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(4000)

    // Слева список пользователей, справа разделы прав.
    const users = page.locator('[data-test="perm-user"]')
    await expect(users.first()).toBeVisible()
    expect(await users.count()).toBeGreaterThan(1)

    await users.first().click()
    await page.waitForTimeout(1200)

    const checkboxes = page.locator('input[type="checkbox"]')
    expect(await checkboxes.count()).toBe(expected.length)

    // Администратору не заблокировано ни одного права.
    const disabled = await page.evaluate(() =>
      [...document.querySelectorAll('input[type="checkbox"]')].filter((c) => c.disabled).length
    )
    expect(disabled, 'администратору доступны все права').toBe(0)
  })

  test('инструктору показан ограниченный набор прав', async ({ page }) => {
    const expected = await catalogAsAdmin(page)
    const { token: adminToken } = await auth(page, 'Администратор')
    const instructor = await findUserByRole(page, adminToken, 'Инструктор')
    test.skip(!instructor, 'нет инструктора с известным паролем')

    await auth(page, instructor.fio)
    await page.goto(`${BASE}/#/permissions`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(4000)

    const users = page.locator('[data-test="perm-user"]')
    await expect(users.first()).toBeVisible()

    await users.first().click()
    await page.waitForTimeout(1200)

    const state = await page.evaluate(() => {
      const boxes = [...document.querySelectorAll('input[type="checkbox"]')]
      return {
        total: boxes.length,
        disabled: boxes.filter((c) => c.disabled).length,
        // Заблокированное право не должно быть включено «из остатка»:
        // иначе инструктор не смог бы сохранить форму вовсе.
        disabledChecked: boxes.filter((c) => c.disabled && c.checked).length,
      }
    })

    expect(state.total, 'инструктор видит те же строки каталога').toBe(expected.length)
    expect(state.disabled, 'у инструктора часть прав заблокирована').toBeGreaterThan(0)
    expect(state.disabledChecked, 'заблокированное право не может быть отмечено').toBe(0)
  })

  test('инструктор не видит администраторов в списке', async ({ page }) => {
    const { token } = await auth(page, 'Администратор')

    const listRes = await page.request.get(BASE + '/api/user/manageable', {
      headers: { Authorization: 'Bearer ' + token }
    })
    const all = (await listRes.json())?.data ?? []
    const instructor = await findUserByRole(page, token, 'Инструктор')
    test.skip(!instructor, 'нет инструктора с известным паролем')

    const insRes = await page.request.get(BASE + '/api/user/manageable', {
      headers: { Authorization: 'Bearer ' + instructor.token }
    })
    const insList = (await insRes.json())?.data ?? []
    test.skip(insList.length === 0, 'у инструктора нет доступных пользователей')

    // Хотя бы один администратор есть в полном списке.
    const admins = all.filter((u) => u.is_admin).map((u) => u.id)
    test.skip(admins.length === 0, 'нет администраторов для проверки фильтра')

    for (const adminId of admins) {
      expect(insList.map((u) => u.id)).not.toContain(adminId)
    }
  })

  test('обучаемый не попадает в раздел прав', async ({ page }) => {
    const { token } = await auth(page, 'Администратор')

    const listRes = await page.request.get(BASE + '/api/user/list', {
      headers: { Authorization: 'Bearer ' + token }
    })
    const trainee = await findUserByRole(page, token, 'Обучаемый')
    test.skip(!trainee, 'нет обучаемого с известным паролем')

    await auth(page, trainee.fio)
    await page.goto(`${BASE}/#/permissions`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(3000)

    await expect(page).toHaveURL(/#\/403/)
    await expect(page.locator('[data-test="perm-user"]')).toHaveCount(0)
  })

  test('пункт меню виден администратору и инструктору, но не обучаемому', async ({ page }) => {
    /**
     * Пункты бокового меню текущего пользователя.
     *
     * reload() обязателен: переход внутри одной страницы меняет только
     * hash, а addInitScript выполняется только на загрузке документа. Без
     * перезагрузки вторая проверка увидела бы меню первой роли.
     */
    const menuFor = async (fio) => {
      await auth(page, fio)
      await page.goto(`${BASE}/#/courses/list`, { waitUntil: 'domcontentloaded' })
      await page.reload({ waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(3500)
      return page.evaluate(() =>
        [...document.querySelectorAll('.v-navigation-drawer .v-list-item')]
          .map((node) => node.textContent.trim())
          .filter(Boolean)
      )
    }

    // Пункт добавляется вместе с i18n-ключом app.menu.permissions, поэтому
    // сравниваем по факту наличия ссылки на /permissions в меню.
    const hasLink = async (fio) => {
      const items = await menuFor(fio)
      const hrefs = await page.evaluate(() =>
        [...document.querySelectorAll('.v-navigation-drawer a')].map((a) => a.getAttribute('href') ?? '')
      )
      return hrefs.some((href) => href.includes('/permissions')) && items.length > 0
    }

    const adminMenu = await menuFor('Администратор')
    expect(adminMenu.some((t) => /прав/i.test(t)), 'у администратора есть пункт раздела прав').toBe(true)
    expect(await hasLink('Администратор')).toBe(true)

    const { token } = await auth(page, 'Администратор')
    const instructor = await findUserByRole(page, token, 'Инструктор')
    test.skip(!instructor, 'нет инструктора с известным паролем')
    expect(await hasLink(instructor.fio), 'у инструктора пункт есть').toBe(true)

    const trainee = await findUserByRole(page, token, 'Обучаемый')
    test.skip(!trainee, 'нет обучаемого с известным паролем')
    expect(await hasLink(trainee.fio), 'у обучаемого пункта быть не должно').toBe(false)
  })

  test('в списке пользователей больше нет колонки с правами', async ({ page }) => {
    await auth(page, 'Администратор')
    await page.goto(`${BASE}/#/user/list`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(3000)

    const headers = await page.evaluate(() =>
      [...document.querySelectorAll('table thead th')].map((th) => th.textContent.trim())
    )
    expect(headers, 'колонка «Разрешения» убрана из списка').not.toContain('Разрешения')

    // Вместо неё — переход в отдельный раздел.
    const link = page.locator('a[href*="permissions"]').first()
    await expect(link, 'есть переход к разделу прав').toBeVisible()
  })
})
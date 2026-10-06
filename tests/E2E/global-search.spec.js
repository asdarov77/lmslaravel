// @ts-check
/**
 * Глобальный поиск: сценарий в браузере (Playwright).
 *
 * Проверяет то, что нельзя увидеть в модульных тестах:
 *  - кнопка появляется в шапке и открывает диалог по Ctrl+K;
 *  - запрос из интерфейса доходит до сервера и приводит к реальному
 *    переходу на страницу курса;
 *  - область видимости соблюдается: обучаемый не находит курс, на
 *    который его группу не записали (поиск не должен быть обходом
 *    скоупа каталога);
 *  - поиск по содержимому приватных файлов закрыт обучаемому: раньше
 *    маршрут висел только на auth:sanctum, и aircraft с path приходили
 *    из тела запроса — материал чужого курса читался перебором.
 *
 * Данные создаются и удаляются внутри теста.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const HASH = BASE + '/#'

const ADMIN = { fio: 'Администратор', password: '123' }
const PASSWORD = 'secret123'

const unique = () => `${Date.now()}${Math.floor(Math.random() * 1000)}`

async function auth(page, creds) {
  const res = await page.request.post(BASE + '/api/login', { data: creds })
  expect(res.ok(), 'логин ' + creds.fio).toBeTruthy()
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

  return { token, user }
}

test.describe('Глобальный поиск', () => {
  test('находит курс и открывает его; чужой курс обучаемому не показывается', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    const { token: adminToken } = await auth(page, ADMIN)
    const headers = { Authorization: 'Bearer ' + adminToken }

    // --- фикстуры: группа, запись на курс, обучаемый -------------------
    const groupRes = await page.request.post(BASE + '/api/groups', {
      headers,
      data: { groupname: `E2E Поиск ${unique()}` },
    })
    expect(groupRes.status()).toBe(201)
    const groupId = (await groupRes.json()).data.id

    const courses = (await (await page.request.get(BASE + '/api/courses', { headers })).json()).data
    test.skip(!courses || courses.length === 0, 'в базе нет ни одного курса')
    const course = courses[0]

    const marker = `Найди${unique()}`
    const learnRes = await page.request.post(BASE + '/api/learning', {
      headers,
      data: {
        group_id: groupId,
        entries: [{ course_id: course.id, parent_id: null }],
        category_id: course.categories?.[0]?.id ?? null,
        typeOfLesson: 'Лекция',
        study_from: new Date().toISOString().slice(0, 10),
        study_to: new Date(Date.now() + 7 * 864e5).toISOString().slice(0, 10),
      },
    })
    expect(learnRes.status()).toBe(201)

    const fio = `E2Е Поиск ${unique()}`
    const regRes = await page.request.post(BASE + '/api/register', {
      headers,
      data: { fio, password: PASSWORD, password_confirmation: PASSWORD, group_id: groupId },
    })
    expect(regRes.status()).toBe(201)
    const userId = (await regRes.json()).data.user?.id ?? (await regRes.json()).data.id

    // Второй набор фикстур тоже нужно убрать: раньше он создавался
    // внутри try, но переменные с id жили только там, и при падении
    // уборка до них не доходила — каждая проверка оставляла в базе
    // группу и пользователя.
    let otherGroupId = null
    let otherUserId = null

    try {
      // --- интерфейс: Ctrl+K, запрос, переход --------------------------
      await page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1800)

      await expect(page.locator('[data-test="search-trigger"]'), 'кнопка поиска в шапке').toBeVisible()

      await page.keyboard.press('Control+k')
      await page.waitForTimeout(700)
      await expect(page.locator('[data-test="search-input"]'), 'диалог открылся по Ctrl+K').toBeVisible()

      // Один символ: сервер такой запрос отклоняет, интерфейс должен
      // объяснить это сам.
      await page.locator('[data-test="search-input"] input').fill('К')
      await page.waitForTimeout(600)
      await expect(page.locator('.u-search__hint-text').first()).toContainText('два символа')

      // Регистр не должен мешать: ищем строчными, в базе — с заглавной.
      const query = course.title.slice(0, 12).toLowerCase()
      await page.locator('[data-test="search-input"] input').fill(query)
      await page.waitForTimeout(1800)

      /*
       * Ищем именно СВОЙ курс среди результатов, а не первый попавшийся.
       *
       * Запрос берётся по первым символам названия, а в базе есть штатные
       * курсы с тем же началом («Конструкция вертолета…»). Порядок выдачи
       * при равном совпадении не гарантирован, и проверка «первый
       * результатор» делала тест нестабильным: он падал не из-за поломки
       * поиска, а из-за чужого курса в выдаче.
       */
      const results = page.locator('[data-test="search-result"]')
      const ours = results.filter({ hasText: course.title.slice(0, 20) })

      await expect(ours.first(), 'курс найден').toBeVisible()
      await ours.first().click()
      await page.waitForTimeout(2000)
      expect(page.url(), 'переход на страницу курса').toContain(`/courses/desc/${course.id}`)

      // --- область видимости ------------------------------------------
      // Обучение на курс второй группы: обучаемый первой группы не
      // должен его видеть ни в каталоге, ни в поиске.
      const otherGroupRes = await page.request.post(BASE + '/api/groups', {
        headers,
        data: { groupname: `E2E Поиск чужая ${unique()}` },
      })
      otherGroupId = (await otherGroupRes.json()).data.id

      const otherCourse = courses.find(c => c.id !== course.id)
      test.skip(!otherCourse, 'нужен второй курс для проверки скоупа')

      const otherFio = `E2Е Поиск чужая ${unique()}`
      const otherReg = await page.request.post(BASE + '/api/register', {
        headers,
        data: { fio: otherFio, password: PASSWORD, password_confirmation: PASSWORD, group_id: otherGroupId },
      })
      expect(otherReg.status()).toBe(201)
      otherUserId = (await otherReg.json()).data.user?.id ?? (await otherReg.json()).data.id

      const trainee = await auth(page, { fio: otherFio, password: PASSWORD })
      const scoped = await page.request.get(`${BASE}/api/search?q=${encodeURIComponent(query)}`, {
        headers: { Authorization: 'Bearer ' + trainee.token, Accept: 'application/json' },
      })
      const scopedBody = await scoped.json()
      const titles = Object.values(scopedBody.data?.groups || {})
        .flat()
        .map(item => item.title)

      expect(
        titles.some(t => String(t).includes(course.title.slice(0, 20))),
        `поиск не должен выдавать чужой курс «${course.title}», получено: ${JSON.stringify(titles)}`
      ).toBe(false)

      // --- приватный контент -------------------------------------------
      const contentSearch = await page.request.post(`${BASE}/api/search-files/`, {
        headers: { Authorization: 'Bearer ' + trainee.token, Accept: 'application/json' },
        data: { query: 'компрессор', aircraft: 1, path: 'theme' },
      })
      expect(contentSearch.status(), 'поиск по содержимому курса обучаемому закрыт').toBe(403)

      expect(errors, 'сценарий не должен давать ошибок').toEqual([])
    } finally {
      // Порядок не важен: удаление группы каскадно снимает её записи.
      // Но null пропускаем: конструкция выше может не дойти до конца.
      if (otherUserId) await page.request.delete(BASE + `/api/user/${otherUserId}`, { headers })
      if (otherGroupId) await page.request.delete(BASE + `/api/groups/${otherGroupId}`, { headers })
      await page.request.delete(BASE + `/api/user/${userId}`, { headers })
      await page.request.delete(BASE + `/api/groups/${groupId}`, { headers })
    }
  })
})

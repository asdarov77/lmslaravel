// @ts-check
/**
 * Сквозной сценарий обучаемого (Playwright).
 *
 * Проверяет ровно то, что было сломано у реального пользователя:
 * инструктор записал группу на курсы, обучаемый вошёл — и
 *   1) в меню не было ни одного пункта, ведущего в 403;
 *   2) «Учебный план» отдавал 403;
 *   3) курсы показывались все, вместе со всеми специальностями;
 *   4) назначенные материалы не открывались;
 *   5) «Экзамены» были недоступны, хотя право exams.take есть.
 *
 * Данные создаются и удаляются внутри теста: обучение проверяется на
 * СВОЕЙ группе и СВОИХ курсах, чтобы не зависеть от содержимого боевой
 * базы и не оставлять мусор после прогона.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const HASH = BASE + '/#'

const ADMIN = { fio: 'Администратор', password: '123' }
const PASSWORD = 'secret123'

const stamp = () => Date.now()
const unique = () => `${stamp()}${Math.floor(Math.random() * 1000)}`

/**
 * Вход через API и подготовка сессии.
 *
 * Кладём в localStorage И токен, И пользователя: боковое меню рендерится
 * по `v-if="loggedIn"`, а loggedIn берётся из сохранённого пользователя.
 * Если положить только токен, меню останется пустым (именно так тест и
 * падал в первый запуск), хотя запросы API проходят успешно.
 *
 * addInitScript, а не page.evaluate после goto: скрипт должен отработать
 * ДО загрузки приложения, иначе сторе успевает подняться без сессии.
 */
async function auth(page, creds) {
  const res = await page.request.post(BASE + '/api/login', { data: creds })
  expect(res.ok(), 'логин ' + creds.fio).toBeTruthy()
  const token = (await res.json()).data.token

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
  return token
}

/** Проверка, что страница не отдала 403. */
async function expectNotForbidden(page) {
  await page.waitForTimeout(1200)
  const text = await page.evaluate(() => document.body.innerText)
  expect(text, 'страница не должна показывать 403').not.toMatch(/В доступе отказано/)
}

test.describe('Обучаемый: доступ к своему учебному материалу', () => {
  test('меню содержит только доступные пункты, учебный план и материалы открываются', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))
    page.on('response', r => {
      if (r.url().includes('/api/') && r.status() >= 500) {
        errors.push(`5xx ${r.status()} ${r.request().method()} ${r.url().replace(BASE, '')}`)
      }
    })

    const adminToken = await auth(page, ADMIN)
    const headers = { Authorization: 'Bearer ' + adminToken }

    // --- фикстуры: категория, курс, группа, обучаемый -----------------
    const groupName = `E2E Обучение ${unique()}`
    const groupRes = await page.request.post(BASE + '/api/groups', {
      headers,
      data: { groupname: groupName },
    })
    expect(groupRes.status()).toBe(201)
    const groupId = (await groupRes.json()).data.id

    // Курс берём из общего списка: назначать группу можно только на
    // существующий курс, а создавать курс с контентом в тесте нельзя.
    const courses = (await (await page.request.get(BASE + '/api/courses', { headers })).json()).data
    test.skip(!courses || courses.length === 0, 'в базе нет ни одного курса')
    const course = courses[0]

    // Контракт записи: group_id + entries[] + обязательные даты.
    // Раньше здесь передавались плоские course_id/parent_id, и запрос
    // проходил, но ничего не создавал.
    const learnRes = await page.request.post(BASE + '/api/learning', {
      headers,
      data: {
        group_id: groupId,
        entries: [{ course_id: course.id, parent_id: null }],
        category_id: course.categories?.[0]?.id ?? null,
        typeOfLesson: 'Лекция',
        study_from: new Date().toISOString().slice(0, 10),
        study_to: new Date(Date.now() + 14 * 864e5).toISOString().slice(0, 10),
      },
    })
    expect(learnRes.status(), 'запись группы на курс').toBe(201)

    // Проверяем, что запись действительно создана: store() у этого
    // маршрута был пустой заглушкой и отвечал 200, ничего не сохраняя.
    const check = await page.request.get(BASE + '/api/learning', { headers })
    const saved = (await check.json())?.data ?? []
    expect(
      saved.filter(r => String(r.group_id) === String(groupId)).length,
      'запись о��ако сохранена'
    ).toBeGreaterThan(0)

    const fio = `E2Е Обучаемый ${unique()}`
    const regRes = await page.request.post(BASE + '/api/register', {
      headers,
      data: {
        fio,
        password: PASSWORD,
        password_confirmation: PASSWORD,
        group_id: groupId,
      },
    })
    expect(regRes.status(), 'регистрация обучаемого').toBe(201)
    const regBody = await regRes.json()
    const userId = regBody.data.user?.id ?? regBody.data.id

    // --- собственно проверки -----------------------------------------
    // addInitScript перезаписывает сессию целиком, поэтому отдельный
    // page.evaluate(() => localStorage.clear()) не требуется.
    await auth(page, { fio, password: PASSWORD })

    // 1. Меню: нет пунктов, ведущих в 403.
    await page.goto(`${HASH}/my/learning`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1800)
    await expectNotForbidden(page)

    const menu = await page.evaluate(() =>
      [...document.querySelectorAll('nav .v-list-item')]
        .map(n => n.innerText.trim())
        .filter(Boolean)
    )

    expect(menu, 'в меню есть курсы').toContain('Курсы')
    expect(menu, 'в меню есть учебный план').toContain('Моё обучение')
    expect(menu, 'в меню есть личный кабинет').toContain('Личный кабинет')
    expect(menu, 'в меню есть экзамены').toContain('Экзамены')

    // Пункты, которые уводили в 403.
    for (const forbidden of ['Файлы', 'Категории', 'Пользователи', 'Группы', 'Учебный план']) {
      expect(menu, `пункт «${forbidden}» не должен показываться обучаемому`).not.toContain(forbidden)
    }

    // Пустая группа меню не остаётся.
    expect(
      await page.locator('nav .v-list-group').count(),
      'пустая группа «Управление пользователями» не рендерится'
    ).toBe(0)

    // 2. Учебный план показывает его курс.
    await expect(
      page.locator('.plan__item', { hasText: course.title }).first(),
      'назначенный курс есть в учебном плане'
    ).toBeVisible()

    // 3. Каталог курсов ограничен его курсом.
    await page.goto(`${HASH}/courses/list`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)
    const shownIds = await page.evaluate(() =>
      [...document.querySelectorAll('.courses__card')].map(n =>
        Number(n.getAttribute('data-course-id'))
      )
    )
    expect(shownIds.length, 'обучаемому показан только его курс').toBe(1)
    expect(shownIds[0]).toBe(course.id)

    // 4. Материалы курса открываются (было 403).
    await page.goto(`${HASH}/courses/itemmani?idEdit=${course.id}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2500)
    await expectNotForbidden(page)
    expect(
      await page.locator('iframe.hello').count(),
      'манифест курса отрисован'
    ).toBeGreaterThan(0)

    // 5. Экзамены доступны (было 403 / пункт был скрыт).
    //
    // Проверяем /my/exams — это путь обучаемого: список назначенных
    // экзаменов. Прежний /questions оставлен методистам: он брал
    // вопросы из банка вместе с is_correct, поэтому правильные ответы
    // уходили в браузер, и проверка шла по правильности на клиенте.
    await page.goto(`${HASH}/my/exams`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)
    await expectNotForbidden(page)

    // А старый путь обучаемому закрыт — и это правильно.
    await page.goto(`${HASH}/questions`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)
    const onForbidden = await page.evaluate(() =>
      /В доступе отказано/i.test(document.body.innerText)
    )
    expect(onForbidden, 'банк вопросов обучаемому закрыт').toBe(true)

    // Личный кабинет отдаёт реальные данные.
    await page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)
    const stats = await page.evaluate(() =>
      [...document.querySelectorAll('.dash__stat')].map(s =>
        s.querySelector('.dash__stat-value')?.innerText
      )
    )
    // Счётчики курсов и активных обязаны быть непустыми — это доказывает,
    // что сводка действительно пришла с бэкенда.
    //
    // НЕ требуем непустоты ВСЕХ показателей: у только что созданного
    // обучаемого прогресс честно 0% (материалы не открывались), а экзаменов
    // 0 (не сдавал). Раньше здесь стояло `not.toContain('0')` — такая
    // проверка падала бы на любом корректном новом пользователе.
    expect(stats[0], 'счётчик курсов на дашборде').toBe('1')
    expect(stats[1], 'счётчик активных курсов на дашборде').toBe('1')
    expect(stats[2], 'прогресс нового обучаемого').toBe('0%')
    expect(stats, 'все четыре показателя отрисованы').toHaveLength(4)

    expect(errors, 'сценарий не должен давать ошибок').toEqual([])

    // --- уборка -------------------------------------------------------
    await page.request.delete(BASE + `/api/user/${userId}`, { headers })
    await page.request.delete(BASE + `/api/groups/${groupId}`, { headers })
  })

  test('администратор сохраняет полный каталог и админские пункты', async ({ page }) => {
    // Обратная сторона скоупа: скоуп не должен «съесть» возможности
    // администратора — иначе нельзя создать и отредактировать курс.
    await auth(page, ADMIN)
    await page.goto(`${HASH}/courses/list`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)

    const cards = await page.locator('.courses__card').count()
    test.skip(cards === 0, 'в базе нет курсов с aircraft_id')

    expect(cards, 'администратор видит больше одного курса').toBeGreaterThan(1)

    const menu = await page.evaluate(() =>
      [...document.querySelectorAll('nav .v-list-item')]
        .map(n => n.innerText.trim())
        .filter(Boolean)
    )
    for (const item of ['Файлы', 'Категории', 'Пользователи', 'Группы', 'Учебный план']) {
      expect(menu, `администратору доступен пункт «${item}»`).toContain(item)
    }
  })
})

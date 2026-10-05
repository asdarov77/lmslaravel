// @ts-check
/**
 * Экзамен: сквозной цикл обучаемого.
 *
 * Закрывает то, что нельзя поймать юнит-тестами:
 *  - правильные ответы НЕ должны попадать в браузер (раньше /api/questions
 *    отдавал is_correct, и результат считался на клиенте);
 *  - сдача записывается на сервер и попадает в учебный кабинет;
 *  - лимит попыток соблюдается на сервере.
 *
 * Фикстуры создаются и удаляются внутри теста.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
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
  return token
}

test.describe('Экзамен', () => {
  test('назначенный экзамен проходится, результат сохраняется', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    const adminToken = await auth(page, ADMIN)
    const headers = { Authorization: 'Bearer ' + adminToken, Accept: 'application/json' }

    // --- фикстуры: группа, модуль с вопросами, экзамен ------------------
    const groupRes = await page.request.post(BASE + '/api/groups', {
      headers,
      data: { groupname: `E2E Экзамен ${unique()}` },
    })
    expect(groupRes.status()).toBe(201)
    const groupId = (await groupRes.json()).data.id

    // Ищем модуль, у которого есть вопросы с ответами — иначе
    // проверять нечего, и тест пропустил бы сам сломанный отбор.
    const coursesRes = await page.request.get(BASE + '/api/courses', { headers })
    const courses = (await coursesRes.json())?.data ?? []
    test.skip(!courses.length, 'в базе нет курсов')

    let picked = null
    for (const course of courses) {
      const qRes = await page.request.get(BASE + '/api/questions?aukstructure_id=' + (await firstModule(course, page, headers)), { headers })
      const questions = (await qRes.json())?.data ?? []
      if (questions.length) {
        picked = { course, questions }
        break
      }
    }
    test.skip(!picked, 'нет модуля с вопросами')

    const questionId = picked.questions[0].id
    const categoryId = picked.questions[0].category_id

    const fio = `E2Е Сдающий ${unique()}`
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

    const examRes = await page.request.post(BASE + '/api/exams', {
      headers,
      data: {
        title: 'E2E Проверка знаний',
        group_id: groupId,
        aukstructure_id: picked.questions[0].aukstructure_id,
        category_id: categoryId,
        max_attempts: 1,
        passing_score: 0.99,
        question_limit: 2,
      },
    })
    expect(examRes.status(), 'создание экзамена').toBe(201)
    const examId = (await examRes.json()).data.id

    // --- проверки под обучаемым ---------------------------------------
    const userToken = await auth(page, { fio, password: PASSWORD })

    const myRes = await page.request.get(BASE + '/api/my/exams', {
      headers: { Authorization: 'Bearer ' + userToken, Accept: 'application/json' },
    })
    const mine = (await myRes.json())?.data ?? []
    expect(mine.some(e => e.id === examId), 'экзамен виден в кабинете').toBe(true)

    const questionsRes = await page.request.get(`${BASE}/api/exams/${examId}/questions`, {
      headers: { Authorization: 'Bearer ' + userToken, Accept: 'application/json' },
    })
    expect(questionsRes.ok()).toBeTruthy()
    const bundle = (await questionsRes.json()).data

    expect(bundle.questions.length, 'вопросов ровно по лимиту').toBe(2)

    // Ключевое: правильных ответов в браузере нет.
    const leaked = bundle.questions.some(q =>
      q.answers.some(a => Object.prototype.hasOwnProperty.call(a, 'is_correct'))
    )
    expect(leaked, 'is_correct не должен покидать сервер до сдачи').toBe(false)

    // Прямой доступ к банку вопросов обучаемому закрыт.
    const bankRes = await page.request.get(BASE + '/api/questions', {
      headers: { Authorization: 'Bearer ' + userToken, Accept: 'application/json' },
    })
    expect(bankRes.status(), 'банк вопросов обучаемому недоступен').toBe(403)

    // --- прохождение в интерфейсе --------------------------------------
    await page.goto(`${BASE}/#/my/exams`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2500)
    await expect(page.locator('.exams__item-title').first()).toBeVisible()

    await page.locator('.exams__item-actions .v-btn').first().click()
    await page.waitForTimeout(2500)

    await expect(page.locator('.runner__question-text')).toBeVisible()
    expect(await page.locator('.runner__answer').count()).toBeGreaterThan(0)

    // Отвечаем на все вопросы: по одному на страницу.
    for (let i = 0; i < 2; i++) {
      await page.locator('.runner__answer input[type=radio]').first().click()
      await page.waitForTimeout(400)
      const next = page.locator('.runner__nav .v-btn').filter({ hasText: /Далее|Закончить/ }).first()
      if (await next.count() && !(await next.isDisabled())) await next.click()
      await page.waitForTimeout(600)
    }

    await page.locator('.runner__verdict, .runner__result').first().waitFor({ timeout: 8000 })
    const verdict = (await page.locator('.runner__verdict').innerText()).trim()
    expect(['Сдано', 'Не сдано']).toContain(verdict)

    // --- результат сохранён на сервере ---------------------------------
    const attemptsRes = await page.request.get(BASE + '/api/exam-attempts', {
      headers: { Authorization: 'Bearer ' + userToken, Accept: 'application/json' },
    })
    const attempts = (await attemptsRes.json())?.data ?? []
    expect(attempts.length, 'попытка записана').toBe(1)
    expect(attempts[0].exam_id).toBe(examId)
    expect(Number(attempts[0].total_count)).toBeGreaterThan(0)

    // И она видна в личном кабинете.
    const dashRes = await page.request.get(BASE + '/api/my/dashboard', {
      headers: { Authorization: 'Bearer ' + userToken, Accept: 'application/json' },
    })
    const exams = (await dashRes.json()).data.exams
    expect(exams.attempts, 'дашборд видит попытку').toBe(1)

    // --- лимит попыток на сервере ---------------------------------------
    const second = await page.request.post(`${BASE}/api/exams/${examId}/attempts`, {
      headers: { Authorization: 'Bearer ' + userToken, Accept: 'application/json' },
      // answer_id берём из вопросов, выданных сервером для этого
      // экзамена. Раньше стояла константа 1, и проверка лимита попыток
      // на самом деле проверяла существование ответа №1: как только
      // таблица ответов пересоздавалась, валидация отвечала 422 вместо
      // ожидаемого 403 — и тест проходил/падал не по существу.
      data: {
        answers: [{ question_id: questionId, answer_id: bundle.questions[0].answers[0].id }],
      },
    })
    expect(second.status(), 'max_attempts=1, вторая попытка запрещена').toBe(403)

    expect(errors, 'ошибок JS быть не должно').toEqual([])

    // --- уборка ---------------------------------------------------------
    await page.request.delete(`${BASE}/api/exams/${examId}`, { headers })
    await page.request.delete(BASE + `/api/user/${userId}`, { headers })
    await page.request.delete(BASE + `/api/groups/${groupId}`, { headers })
  })
})

/** Первый модуль курса — по нему банк и отбирает вопросы. */
async function firstModule(course, page, headers) {
  const res = await page.request.get(BASE + '/api/courses/' + course.id, { headers })
  const body = await res.json().catch(() => null)
  const data = body?.data ?? body
  const modules = data?.aukstructure ?? data?.aukstructures ?? []
  return modules[0]?.id ?? 0
}

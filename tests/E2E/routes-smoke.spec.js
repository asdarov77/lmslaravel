// @ts-check
import { test, expect } from '@playwright/test'

/**
 * Smoke-покрытие всех SPA-маршрутов из resources/js/Router/routes.js.
 *
 * Для каждого маршрута проверяем то, что прежние тесты не ловили:
 *  - нет необработанных JS-ошибок (pageerror),
 *  - нет неожиданных ошибок в console,
 *  - нет ответов API с кодом 5xx,
 *  - страница реально отрисовала контент.
 *
 * Идентификаторы в маршрутах — плейсхолдеры {course}/{category}/... ,
 * которые подставляются реальными id из БД: в dev-базе записи с id=1
 * может не быть, и маршрут падал бы с 404 не из-за бага приложения.
 */

const BASE = 'http://127.0.0.1:8080'
const HASH = BASE + '/#'
const ADMIN = { fio: 'Администратор', password: '123' }

// Внешние CDN нестабильны и не являются частью приложения
const EXTERNAL_NOISE =
  /ERR_(TIMED_OUT|NAME_NOT_RESOLVED|CONNECTION|NETWORK)|avataaars|bulma|jsdelivr|cdnjs|googleapis|fonts\.g/

/** @type {{name: string, hash: string, note?: string}[]} */
const ROUTES = [
  { name: 'главная', hash: '/' },
  { name: 'about', hash: '/about' },
  { name: 'contacts', hash: '/contacts' },
  { name: 'личный кабинет', hash: '/my' },
  { name: 'список пользователей', hash: '/user/list' },
  { name: 'редактирование пользователя', hash: '/user/edit/{user}' },
  { name: 'смена пароля', hash: '/user/chpass/{user}' },
  { name: 'список групп', hash: '/groups/list' },
  { name: 'создание группы', hash: '/groups/add' },
  { name: 'редактирование группы', hash: '/groups/edit/{group}' },
  { name: 'список курсов', hash: '/courses/list' },
  { name: 'карточка курса', hash: '/courses/item/{course}' },
  { name: 'манифест курса', hash: '/courses/itemmani?idEdit={course}&idCategory={category}' },
  { name: 'описание курса', hash: '/courses/desc/{course}' },
  { name: 'курсы (новый раздел)', hash: '/course' },
  { name: 'курс по id', hash: '/course/{course}' },
  { name: 'классы', hash: '/classes' },
  { name: 'категории', hash: '/categories' },
  { name: 'регистрация категорий', hash: '/register-categories' },
  { name: 'редактирование категории', hash: '/categories/{category}' },
  { name: 'загрузка файлов', hash: '/files/add' },
  { name: 'календарь', hash: '/calendar' },
  { name: 'обучение группы', hash: '/group/learning/{group}' },
  { name: 'аук структура', hash: '/auk' },
  { name: 'загрузка подарков', hash: '/upload-gift' },
  { name: 'вопросы', hash: '/questions' },
  { name: 'вопросыmain', hash: '/questions-main' },
  { name: 'вопрос по id', hash: '/questions-main/{question}' },
  { name: 'новый вопрос', hash: '/questions-main/new/{category}/{course}' },
  { name: 'grade-boundary', hash: '/grade-boundary/{course}' },
  { name: 'настройки', hash: '/settings/{course}' },
  { name: 'datepicker', hash: '/datepicker' },
  { name: 'курсы пользователя', hash: '/user-course/{user}' },
  { name: '403', hash: '/403' },
  { name: '404', hash: '/404' },
  // Известный незакрытый дефект: бэкенда файлового дерева (api/tree)
  // в проекте нет. Компонент теперь деградирует до пустого дерева.
  { name: 'filemanager', hash: '/filemanager', note: 'GET /api/tree → 404, функциональность не реализована' },
]

/** Кэш реальных id на воркер, чтобы не дёргать API 5 раз на каждый маршрут. */
let idCache = null

/**
 * @param {import('@playwright/test').APIRequestContext} req
 * @param {string} token
 */
async function resolveIds(req, token) {
  if (idCache) return idCache
  /** @type {Record<string, number>} */
  const found = {}
  /** @type {[string, string][]} */
  const sources = [
    ['/api/course', 'course'],
    ['/api/categories', 'category'],
    ['/api/groups', 'group'],
    ['/api/questions', 'question'],
    ['/api/user/list', 'user'],
  ]
  for (const [url, key] of sources) {
    const r = await req.get(BASE + url, { headers: { Authorization: 'Bearer ' + token } })
    if (!r.ok()) {
      found[key] = 1
      continue
    }
    const body = await r.json()
    const rows = Array.isArray(body?.data) ? body.data : []
    const id = rows.map((x) => x?.id).find((x) => Number.isInteger(x) && x > 0)
    found[key] = id ?? 1
  }
  idCache = found
  return idCache
}

/** @param {import('@playwright/test').Page} page */
async function login(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора должен succeed').toBeTruthy()
  const body = await res.json()
  const token = body?.data?.token
  expect(token, 'ответ логина должен содержать токен').toBeTruthy()
  await page.addInitScript(
    ([t, u]) => {
      window.localStorage.setItem('token', t)
      window.localStorage.setItem('user', u)
    },
    [token, JSON.stringify(body?.data?.user ?? null)]
  )
  return token
}

test.describe('Все SPA-маршруты открываются без ошибок', () => {
  for (const route of ROUTES) {
    test(`${route.name} (${route.hash})`, async ({ page }) => {
      /** @type {string[]} */
      const pageErrors = []
      /** @type {string[]} */
      const consoleErrors = []
      /** @type {string[]} */
      const serverErrors = []

      page.on('pageerror', (e) => pageErrors.push(e.message.split('\n')[0]))
      page.on('console', (m) => {
        const t = m.text()
        if (m.type() === 'error' && !EXTERNAL_NOISE.test(t) && !/Failed to load resource/.test(t)) {
          consoleErrors.push(t.split('\n')[0])
        }
      })
      page.on('response', (r) => {
        if (r.url().includes('/api/') && r.status() >= 500) {
          serverErrors.push(`${r.status()} ${r.url().replace(BASE, '')}`)
        }
      })

      const token = await login(page)
      const ids = await resolveIds(page.request, token)
      const hash = route.hash.replace(/\{(\w+)\}/g, (_, k) => String(ids[k] ?? 1))

      await page.goto(HASH + hash, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(900)

      expect(pageErrors, `pageerror на ${hash}`).toEqual([])
      expect(serverErrors, `5xx от API на ${hash}`).toEqual([])
      expect(consoleErrors, `ошибки console на ${hash}`).toEqual([])

      const text = (await page.textContent('body')) || ''
      expect(text.trim().length, `${hash} отрисовал пустую страницу`).toBeGreaterThan(10)
    })
  }
})

test.describe('Публичные страницы доступны без авторизации', () => {
  for (const hash of ['/login', '/reg', '/500']) {
    test(`${hash}`, async ({ page }) => {
      /** @type {string[]} */
      const pageErrors = []
      page.on('pageerror', (e) => pageErrors.push(e.message.split('\n')[0]))

      await page.goto(HASH + hash, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(700)

      expect(pageErrors, `pageerror на ${hash} без токена`).toEqual([])
      const text = (await page.textContent('body')) || ''
      expect(text.trim().length).toBeGreaterThan(10)
    })
  }
})

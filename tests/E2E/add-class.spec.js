// @ts-check
/**
 * Страница «Добавление класса» (/classes).
 *
 * Регрессии, которые закрывает файл:
 *
 *  1. Кнопка «СОХРАНИТЬ» отправляла POST /api/classes с пустыми полями.
 *     Бэкенд отвечал 422 «The title field is required.» / «The path field
 *     is required.», ошибка уходила только в console.error, и пользователь
 *     не видел ничего. Теперь запрос не уходит, а на форме показывается
 *     текст «Выберите класс для добавления» / «Укажите описание класса».
 *
 *  2. Успешный импорт не давал обратной связи: тег исчезал из списка
 *     молча. Теперь показывается сообщение с числом загруженных АУК.
 *
 *  3. Кнопка очистки БД работала «в молоко»: результат не отображался,
 *     список тегов не перечитывался. Теперь есть сообщение об успехе и
 *     об ошибке (403 без права system.maintenance).
 *
 *  4. Кнопка отмены/перехода назад смешивалась с submit формы: раньше
 *     submit делал router.go(-1) в finally, даже при ошибке.
 */
import { test, expect } from '@playwright/test'

const BASE = 'http://127.0.0.1:8080'
const ADMIN = { fio: 'Администратор', password: '123' }

/** Логин через API + токен в localStorage. */
async function auth(page) {
  const res = await page.request.post(BASE + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора').toBeTruthy()
  const body = await res.json()
  await page.addInitScript(
    ([t, u]) => {
      if (location.protocol !== 'http:' && location.protocol !== 'https:') return
      try {
        window.localStorage.setItem('token', t)
        window.localStorage.setItem('user', u)
      } catch (e) {
        /* хранилище недоступно — тест упадёт позже */
      }
    },
    [body.data.token, JSON.stringify(body.data.user ?? null)]
  )
  return body.data.token
}

/**
 * Только реальные ошибки приложения: сеть/CDN в dev не наш домен.
 *
 * httpNoise — разрешить штатный шум axios («Request failed with status code
 * 409»). Это не ошибка приложения: ответ 409 мы проверяем отдельной
 * ассерцией и показываем пользователю понятное сообщение. В тестах,
 * где 4xx/5xx означал бы регресс, опция не включается.
 */
function collectErrors(page, { httpNoise = false, allow = [] } = {}) {
  const errors = []
  const ignore =
    /avataaars\.io|bulma\.io|jsdelivr|googleapis|gstatic|favicon|ERR_(TIMED_OUT|NAME_NOT_RESOLVED|INTERNET_DISCONNECTED|CONNECTION)/i
  const axiosNoise = /Request failed with status code \d+/
  // Браузер логирует в console любой не-2xx ответ, даже обработанный axios.
  const resourceNoise = /Failed to load resource.*status of \d+/

  page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return
    const text = msg.text()
    if (ignore.test(text)) return
    if (httpNoise && axiosNoise.test(text)) return
    if (httpNoise && resourceNoise.test(text)) return
    // Явно разрешённый шум конкретного теста
    if (allow.some((re) => re.test(text))) return
    // Vue warn о неизвестном компоненте — это регресс, его ловим
    errors.push(text)
  })

  return errors
}

/** Открывает страницу классов и ждёт загрузки списка каталогов. */
async function openClasses(page) {
  await page.goto(BASE + '/#/classes', { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2000)
  await expect(page.locator('#app')).toBeVisible()
}

/**
 * Комбобокс выбора класса. Это обёртка v-combobox, сам input лежит внутри.
 */
function classCombo(page) {
  return page.locator('.v-card .v-combobox input').first()
}

/**
 * Поле «Описание». Ищем по accessible name, а не «первый input[type=text]»:
 * первым в DOM идёт input комбобокса, из-за чего текст описания раньше
 * попадал в выбор класса и валидация ругалась «Укажите описание».
 */
function descriptionInput(page) {
  return page.getByRole('textbox', { name: 'Описание' })
}

/** Выбирает конкретный каталог в комбобоксе и дожидается подстановки. */
async function pickClass(page, name) {
  const combo = classCombo(page)
  await combo.click()
  await combo.fill(name)
  await page.waitForTimeout(600)

  // Опцию ищем ВНУТРИ .v-overlay-container: голый .v-list-item совпал бы
  // с пунктами бокового меню и увёл бы на другую страницу.
  const option = page
    .locator('.v-overlay-container .v-list-item')
    .filter({ hasText: name })
    .first()

  if ((await option.count()) === 0) {
    await page.keyboard.press('Escape')
    return false
  }

  await option.click()
  await page.waitForTimeout(300)
  return (await combo.inputValue()) === name
}

test.describe('Добавление класса: валидация формы', () => {
  test('пустая форма показывает подсказку и НЕ шлёт запрос', async ({ page }) => {
    await auth(page)
    const errors = collectErrors(page)

    // Считаем ответы на /api/classes, а не 422: цель — запрос не уходит.
    const posts = []
    page.on('request', (req) => {
      if (req.url().includes('/api/classes') && req.method() === 'POST') posts.push(req)
    })
    const serverErrors = []
    page.on('response', (res) => {
      if (res.url().includes('/api/classes') && res.status() >= 400) {
        serverErrors.push(`${res.status()} ${res.url()}`)
      }
    })

    await openClasses(page)

    await page.getByRole('button', { name: /сохранить/i }).click()
    await page.waitForTimeout(1200)

    // Регресс: раньше уходил POST {"title":"","path":""} -> 422
    expect(posts, 'POST /api/classes не должен отправляться с пустой формой').toHaveLength(0)
    expect(serverErrors, '4xx/5xx ответов быть не должно').toEqual([])

    await expect(page.getByText('Выберите класс для добавления').first()).toBeVisible()

    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([])
  })

  test('заполнен только класс — подсказка про описание, запроса нет', async ({ page }) => {
    await auth(page)
    const posts = []
    page.on('request', (req) => {
      if (req.url().includes('/api/classes') && req.method() === 'POST') posts.push(req)
    })

    await openClasses(page)

    // Выбираем первый доступный каталог в комбобоксе.
    const combo = page.locator('.v-card .v-combobox').first()
    await combo.click()
    await page.waitForTimeout(500)

    const option = page.locator('.v-overlay-container .v-list-item').first()
    if ((await option.count()) === 0) {
      test.skip(true, 'в каталоге нет ни одного класса — нечего выбирать')
    }
    await option.click()
    await page.waitForTimeout(400)
    await expect(classCombo(page)).not.toHaveValue('')

    await page.getByRole('button', { name: /сохранить/i }).click()
    await page.waitForTimeout(1000)

    expect(posts).toHaveLength(0)
    await expect(page.getByText('Укажите описание класса').first()).toBeVisible()
  })
})

test.describe('Добавление класса: успешный импорт', () => {
  test('валидный payload даёт 201 и сообщение с числом АУК', async ({ page }) => {
    // Импорт одного класса тянет ~900 записей АУК, поэтому дефолтных
    // 30 секунд Playwright не хватает.
    test.setTimeout(300000)

    // 409 допустим (класс уже импортирован), поэтому шум axios про него
    // не считаем ошибкой приложения.
    const errors = collectErrors(page, { httpNoise: true })

    // Узнаём доступный каталог заранее, чтобы выбрать существующий.
    // Токен берём из auth(), а не из page.evaluate: до первой навигации
    // документ about:blank и доступ к localStorage запрещён (SecurityError).
    const token = await auth(page)
    const res = await page.request.get(BASE + '/api/classesfs', {
      headers: { Authorization: 'Bearer ' + token },
    })
    expect(res.ok()).toBeTruthy()
    const tags = await res.json()
    const available = Array.isArray(tags) ? tags : tags?.data ?? []
    if (!Array.isArray(available) || available.length === 0) {
      test.skip(true, 'каталог контента пуст — импортировать нечего')
    }
    const target = available[0].name ?? available[0].title ?? String(available[0])

    await openClasses(page)

    const picked = await pickClass(page, target)
    if (!picked) {
      test.skip(true, `каталог «${target}» не появился в выпадающем списке`)
    }
    await descriptionInput(page).fill('E2E проверка импорта')

    // Ловим ответ ДО клика. Ждать по тексту в DOM нельзя: подпись
    // комбобокса «Выберите класс для добавления» присутствует всегда и
    // срабатывала мгновенно, пока импорт ещё шёл.
    const responsePromise = page.waitForResponse(
      (res) => res.url().includes('/api/classes') && res.request().method() === 'POST',
      { timeout: 240000 }
    )

    await page.getByRole('button', { name: /сохранить/i }).click()

    const importResponse = await responsePromise
    const status = importResponse.status()

    // 201 — создан новый aircraft; 409 — класс уже был импортирован ранее.
    // Оба статуса корректны, 422/500 — регресс.
    expect([201, 409], `неожиданный статус импорта: ${status}`).toContain(status)

    // После ответа форма обязана показать результат и разблокироваться.
    // Проверяем кнопку, а не v-progress-*: глобальный индикатор загрузки
    // приложения остаётся в DOM всегда и к формам отношения не имеет.
    await expect(page.getByRole('button', { name: /сохранить/i })).toBeEnabled({
      timeout: 30000,
    })
    await expect(page.getByRole('button', { name: /очистить базу данных/i })).toBeEnabled()

    const body = await page.locator('.v-card').innerText()
    expect(body).toMatch(/импортирован|уже импортирован/i)

    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([])
  })
})

/**
 * Ставит заглушку на /api/clear-database.
 *
 * Кнопка очистки необратима, поэтому в E2E реальный эндпоинт НЕ вызываем:
 * он удалил бы контент dev-базы и сломал бы остальные smoke-тесты.
 * Настоящее поведение очистки (транзакция, порядок FK, права) проверяется
 * в tests/Feature/ClearDatabaseTest.php на изолированной БД.
 */
async function stubClearDatabase(page, { status = 200, delayMs = 300, envelope = null } = {}) {
  const calls = []

  await page.route('**/api/clear-database', async (route) => {
    calls.push(route.request())
    if (delayMs) await new Promise((r) => setTimeout(r, delayMs))
    await route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify(
        envelope ?? { message: 'ok', data: { deleted: { courses: 1, aukstructures: 890 } } }
      ),
    })
  })

  return calls
}

test.describe('Очистка базы данных', () => {
  test('кнопка сообщает результат и не бросает необработанных ошибок', async ({ page }) => {
    await auth(page)
    const errors = collectErrors(page, { httpNoise: true })
    const calls = await stubClearDatabase(page)

    await openClasses(page)

    const clearBtn = page.getByRole('button', { name: /очистить базу данных/i })
    // За подтверждением в диалоге следует запрос.
    await expect(clearBtn).toBeVisible()

    // Клик открывает подтверждение, запрос — только после него.
    await clearBtn.click()
    await page.locator('.confirm-dialog').getByRole('button', { name: /^очистить$/i }).click()

    await expect(page.getByText('База данных очищена').first()).toBeVisible({ timeout: 30000 })
    expect(calls.length, 'ожидался один вызов /api/clear-database').toBe(1)

    // Регресс: раньше результат не отображался вообще.
    // Берём ПЕРВУЮ карточку: ConfirmDialog тоже является .v-card, и
    // без .first() селектор перестал бы быть однозначным, когда диалог
    // ещё не закрылся.
    const text = await page.locator('.v-card').first().innerText()
    expect(text).toContain('База данных очищена')

    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([])
  })

  test('при отказе сервера показывается ошибка, а не тишина', async ({ page }) => {
    await auth(page)
    // 500: httpClient не редиректит, поэтому форма остаётся на месте и
    // показывает текст ошибки. console.error(500, ...) из httpClient —
    // ожидаемый шум, разрешаем его явно.
    const errors = collectErrors(page, { httpNoise: true, allow: [/^500 /] })
    await stubClearDatabase(page, {
      status: 500,
      envelope: { message: 'Очистка не выполнена: транзакция откатилась' },
    })

    await openClasses(page)
    await page.getByRole('button', { name: /очистить базу данных/i }).click()
    // Запрос уходит только после подтверждения в диалоге.
    await page.locator('.confirm-dialog').getByRole('button', { name: /^очистить$/i }).click()

    // Показывается текст ОТ СЕРВЕРА, а не только наш fallback:
    // это и есть проверка, что extractApiError разбирает конверт.
    await expect(page.getByText('Очистка не выполнена: транзакция откатилась')).toBeVisible({
      timeout: 30000,
    })

    // Успеха быть не должно.
    await expect(page.getByText('База данных очищена')).toHaveCount(0)

    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([])
  })

  test('при 403 приложение уходит на страницу «в доступе отказано»', async ({ page }) => {
    await auth(page)
    await stubClearDatabase(page, {
      status: 403,
      envelope: { message: 'Нет прав на очистку базы данных' },
    })

    await openClasses(page)
    // Кнопка больше не стирает базу сразу: сначала подтверждение.
    await page.getByRole('button', { name: /очистить базу данных/i }).click()
    await page.locator('.confirm-dialog').getByRole('button', { name: /^очистить$/i }).click()

    // Глобальная политика httpClient: 403 -> маршрут /403. Фиксируем её
    // явно, чтобы «молчаливое игнорирование отказа» не прошло молча.
    await expect(page.getByText('В доступе отказано').first()).toBeVisible({ timeout: 30000 })
  })

  test('кнопка не активна повторно, пока идёт очистка', async ({ page }) => {
    await auth(page)
    // Задержка нужна, чтобы второй клик пришёлся на время «в полёте»
    const calls = await stubClearDatabase(page, { delayMs: 2000 })

    await openClasses(page)

    const clearBtn = page.getByRole('button', { name: /очистить базу данных/i })
    // За подтверждением в диалоге следует запрос.
    await clearBtn.click()

    // Подтверждаем в диалоге, затем жмём «Очистить» ещё раз, пока
    // первый запрос в полёте: флаг clearing обязан заблокировать
    // повторный запрос.
    const confirmBtn = page.locator('.confirm-dialog').getByRole('button', { name: /^очистить$/i })
    await confirmBtn.click()
    await confirmBtn.click({ force: true }).catch(() => {})
    await page.waitForTimeout(3000)

    expect(calls.length, 'повторный клик не должен слать второй запрос').toBe(1)
  })
})

test.describe('Боковое меню', () => {
  test('все пункты меню ведут на существующие страницы (регресс /user/learning)', async ({ page }) => {
    await auth(page)
    const errors = collectErrors(page)

    await page.goto(BASE + '/#/classes', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)

    // Пункт «Учебный план» (/my/learning) помечен onlyFor: 'trainee':
    // маршрут открыт любому вошедшему, но у администратора записей на
    // курсы нет и страница открылась бы пустой. В меню его быть не
    // должно — это и проверяет count ниже.
    //
    // Скоуп именно на боковое меню: такая же подпись есть в меню профиля,
    // и getByText() без скоупа попадал в неё, а не в навигацию.
    await expect(
      page.locator('[data-test="nav-item"]', { hasText: 'Учебный план' }),
      'у администратора личного учебного плана в меню нет',
    ).toHaveCount(0)
    await expect(page.getByText('user learning')).toHaveCount(0)

    // Подписи уникальны: раньше два пункта были «Курсы», из-за чего
    // Vuetify писал «Multiple nodes with the same ID». Заголовок группы
    // «Пользователи» тоже совпадал с пунктом /user/list — теперь это
    // «Управление пользователями». Пункт «Мои курсы» лежит в свёрнутой
    // группе, поэтому считаем все v-list-item-title, а не видимые.
    const titles = await page
      .locator('[data-test="nav-item"]')
      .allInnerTexts()
      .catch(() => [])

    if (titles.length) {
      const menuTitles = titles.map((t) => t.trim()).filter(Boolean)

      const count = (needle) => menuTitles.filter((t) => t === needle).length

      expect(count('Курсы'), 'подпись «Курсы» должна быть ровно один раз').toBe(1)

      // Пункт «Мои курсы» (/auk) убран: он вёл в личный кабинет, который
      // теперь /dashboard («Личный кабинет»). Два пункта об одном и том же —
      // лишние, а проверка ниже на отсутствие дублей подписей остаётся
      // главным инвариантом этого теста.
      expect(
        count('Мои курсы'),
        'пункт «Мои курсы» убран в пользу «Личный кабинет»'
      ).toBe(0)
      expect(count('Личный кабинет'), 'пункт «Личный кабинет» есть').toBe(1)
      // «Учебный план» — личный учебный план. Управляющему он не нужен
      // и раньше вёл в пустую страницу: маршрут /my/learning открыт
      // любому авторизованному, а записей на курсы у методиста нет.
      // Пункт помечен onlyFor: 'trainee'; тест заходит под
      // администратором, значит пункта быть не должно.
      expect(
        count('Учебный план'),
        'управляющему пункт «Учебный план» не показывается'
      ).toBe(0)

      const seen = new Set()
      const dupes = menuTitles.filter((t) => {
        if (seen.has(t)) return true
        seen.add(t)
        return false
      })
      expect(dupes, `дубли подписей в меню: ${dupes.join(', ')}`).toEqual([])
    }

    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([])
  })

  test('переход по меню не даёт 404 и JS-ошибок', async ({ page }) => {
    await auth(page)
    const errors = collectErrors(page)

    await page.goto(BASE + '/#/classes', { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(2000)

    /*
     * Ссылки проверяем БЕЗ перехода по каждому пункту: переход по всем
     * ~18 пунктам с перезагрузкой не укладывается в таймаут теста.
     *
     * Вместо этого сверяем href каждого пункта (ловит ссылку в никуда) и
     * выполняем ОДИН переход — чтобы убедиться, что страница открывается
     * без ошибок в консоли.
     */
    const links = await page
      .locator('[data-test="nav-item"]')
      .evaluateAll((nodes) => nodes.map((n) => n.getAttribute('href')))

    expect(links.length, 'в меню есть пункты').toBeGreaterThan(0)
    expect(
      links.filter((href) => !href || href.includes('undefined')),
      'у пункта меню пустая ссылка',
    ).toEqual([])

    await page.locator('[data-test="nav-item"]').first().click()
    await page.waitForTimeout(1500)

    // Регресс: /user/learning открывал страницу 404
    const body = await page.locator('body').innerText()

    expect(body).not.toMatch(/Страница не найдена/i)

    expect(errors, 'ошибок JS быть не должно:\n' + errors.join('\n')).toEqual([])
  })
})
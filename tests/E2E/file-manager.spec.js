// @ts-check
/**
 * Файловый менеджер (/filemanager): загрузка, папки, перенос, переименование.
 *
 * Проверяется то, что нельзя увидеть в PHPUnit-тестах: реальный браузер,
 * реальный выбор файла и реальная сборка частей.
 *
 * РЕГРЕССИИ, КОТОРЫЕ ЗАКРЫВАЕТ ФАЙЛ.
 *
 *  1. КАРТОЧЕК ПРОГРЕССА НЕ БЫЛО ВООБЩЕ. Файлы загружались (init → chunk →
 *     complete, всё со статусом 200), появлялись в таблице — а очередь
 *     загрузки не отображалась. Причина: движок держал собственный массив
 *     задач и писал в «сырые» объекты, а шаблон читал reactive-версию
 *     того же массива. Реактивность Vue 3 устроена через прокси, и
 *     уведомления от такой записи не бывает. Симптом обманчив: всё
 *     работает, кроме обратной связи.
 *
 *  2. Файл не доезжал целиком. Проверяется не «загрузился», а что на диске
 *     лежит ровно столько байт, сколько было в исходном файле: протокол
 *     из нескольких частей, где одна пришла дважды, даёт «успех» и битый
 *     файл.
 *
 *  3. Папка создавалась, но оставалась забытой: её не было видно в
 *     списке и нельзя было войти.
 *
 *  4. Перенос и переименование выглядели рабочими, но меняли только
 *     нарисованную строку, а не то, что лежит на диске. Здесь после
 *     каждого действия содержимое перечитывается через API.
 *
 *  5. Повторный выбор того же файла не срабатывал: после первого выбора
 *     значение input сбрасывалось не всегда, и браузер не присылал
 *     событие change.
 *
 * ОСТОРОЖНО С ДАННЫМИ. Тест работает в каталоге, помеченном префиксом
 * «e2e-», и удаляет его в конце. Файлы, загруженные до него, тест не
 * трогает.
 */
import { test, expect } from '@playwright/test'
import { createHash } from 'node:crypto'

const BASE = 'http://127.0.0.1:8080'
const ADMIN = { fio: 'Администратор', password: '123' }
const PREFIX = 'e2e-'

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
 * Только настоящие ошибки приложения: сеть/CDN в dev не наш домен.
 *
 * httpNoise — разрешить штатный шум axios («Request failed with status
 * code 422») и браузера («Failed to load resource ... 422»). Он нужен
 * тестам, которые САМИ вызывают отказ и проверяют, что приложение его
 * показало. Такой 422 — не регресс: без него тест на отказ невозможен.
 */
function collectErrors(page, { httpNoise = false } = {}) {
  const errors = []
  const ignore =
    /avataaars\.io|bulma\.io|jsdelivr|googleapis|gstatic|favicon|ERR_(TIMED_OUT|NAME_NOT_RESOLVED|INTERNET_DISCONNECTED|CONNECTION)/i
  const axiosNoise = /Request failed with status code \d+/
  const resourceNoise = /Failed to load resource.*status of \d+/

  page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return
    const text = msg.text()
    if (ignore.test(text)) return
    if (httpNoise && axiosNoise.test(text)) return
    if (httpNoise && resourceNoise.test(text)) return
    errors.push(text)
  })

  return errors
}

/** Содержимое каталога напрямую из API — проверка без доверия к DOM. */
async function readFolder(page, token, folderId = null) {
  const url = BASE + '/api/filemanager' + (folderId ? `?folder_id=${folderId}` : '')
  const res = await page.request.get(url, { headers: { Authorization: 'Bearer ' + token } })
  expect(res.ok(), `чтение каталога ${folderId ?? 'корень'}`).toBeTruthy()

  return (await res.json())?.data
}

/** Создаёт папку и возвращает её id. */
async function makeFolder(page, token, name, parentId = null) {
  const res = await page.request.post(BASE + '/api/filemanager/folders', {
    headers: { Authorization: 'Bearer ' + token },
    data: { name, parent_id: parentId },
  })
  expect(res.ok(), `создание папки «${name}»`).toBeTruthy()

  return (await res.json())?.data?.id
}

/**
 * Удаляет всё, что осталось от предыдущих прогонов с префиксом e2e-.
 *
 * Без этого тесты копят мусор: файл с тем же именем накапливался от
 * запуска к запуску, счётчик рос, и проверка падала сама себя.
 */
async function cleanupPrefixed(page, token) {
  const h = { Authorization: 'Bearer ' + token }

  const files = (await readFolder(page, token)).files.filter((f) => f.name.startsWith(PREFIX))
  if (files.length) {
    await page.request
      .post(BASE + '/api/filemanager/files/delete', { headers: h, data: { ids: files.map((f) => f.id) } })
      .catch(() => {})
  }

  const folders = (await readFolder(page, token)).folders.filter((f) => f.name.startsWith(PREFIX))
  for (const folder of folders) {
    await page.request
      .delete(`${BASE}/api/filemanager/folders/${folder.id}?recursive=1`, { headers: h })
      .catch(() => {})
  }
}

/** Удаляет папку рекурсивно. Ошибка 404/422 не мешает: её могли убрать вручную. */
async function dropFolder(page, token, folderId) {
  await page.request
    .delete(`${BASE}/api/filemanager/folders/${folderId}?recursive=1`, {
      headers: { Authorization: 'Bearer ' + token },
    })
    .catch(() => {})
}

/**
 * Убирает боковое меню, перекрывающее содержимое.
 *
 * Про это ОТДЕЛЬНЫЙ дефект, общий для всего приложения: v-navigation-drawer
 * объявлен с `app`, но v-main не сдвигается, поэтому на узких экранах
 * левое меню лежит поверх хлебных крошек и заголовков таблицы. Видно
 * это и на странице «Категории» — то есть не следствие файлового
 * менеджера. Здесь закрываем меню, чтобы тест нажимал на элементы, а не
 * на прозрачное перекрытие.
 *
 * Кнопка меню в шапке помечена data-test в самой шапке; селектор узкий,
 * потому что это единственная кнопка без подписи в .v-app-bar.
 */
async function closeDrawer(page) {
  const drawer = page.locator('.v-navigation-drawer.app__drawer')

  if ((await drawer.count()) === 0) return

  const toggle = page.locator('.v-app-bar .v-app-bar-nav-icon').first()

  if ((await toggle.count()) === 0) return

  await toggle.click()
  // Меню сворачивается анимацией; ждём, пока drawer уедет.
  await drawer.waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {})
}

const openManager = async (page) => {
  await page.goto(BASE + '/#/filemanager', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('[data-test="fm-upload"]')).toBeVisible({ timeout: 20000 })
  await closeDrawer(page)
}

/**
 * Загружает файл через скрытый input страницы.
 *
 * Файл кладётся в Buffer и вставляется в input через setInputFiles:
 * это настоящий выбор файла, а не вызов внутреннего API компонента.
 * Размер подобран так, чтобы файл разбился на несколько частей при
 * умолчательной части 4 МБ.
 */
async function uploadFile(page, name, sizeBytes, seed = 'x') {
  const bytes = Buffer.alloc(sizeBytes)
  for (let i = 0; i < sizeBytes; i++) {
    // Не period(): нужен детерминированный, но НЕ предсказуемый байт,
    // чтобы потерю части было видно по контрольной сумме.
    bytes[i] = (seed.charCodeAt(i % seed.length) + i) % 256
  }

  await page.setInputFiles('[data-test="fm-file-input"]', {
    name,
    mimeType: 'application/octet-stream',
    buffer: bytes,
  })

  return {
    name,
    size: sizeBytes,
    sha256: createHash('sha256').update(bytes).digest('hex'),
  }
}

/** Ждёт, пока в очереди загрузки появится файл с указанным именем. */
async function waitForQueueItem(page, name, timeout = 60000) {
  const item = page.locator('[data-test="fm-upload-item"]', { hasText: name }).first()
  await expect(item).toBeVisible({ timeout })

  return item
}

test.describe('Файловый менеджер: загрузка', () => {
  test.beforeEach(async ({ page }) => {
    const token = await auth(page)
    await cleanupPrefixed(page, token)
  })

  test('файл догружается целиком и появляется в списке', async ({ page }) => {
    const token = await auth(page)
    const errors = collectErrors(page)

    await openManager(page)

    // 9 МБ при части 4 МБ — это три части, последняя короче остальных.
    const file = await uploadFile(page, `${PREFIX}отчёт.bin`, 9 * 1024 * 1024)

    // Регресс 1: карточка прогресса обязана появиться.
    const item = await waitForQueueItem(page, file.name)
    await expect(item.locator('[data-test="fm-upload-status"]')).toContainText(/загружено|загружается/, {
      timeout: 60000,
    })

    // Регресс 2: файл приехал целиком, а не «какой-то файл с таким именем».
    await expect
      .poll(
        async () => {
          const data = await readFolder(page, token)
          return data.files.filter((f) => f.name === file.name).length
        },
        { timeout: 60000, message: 'файл не появился в каталоге' }
      )
      .toBe(1)

    // Размер совпадает с исходным — значит ни одна часть не потеряна.
    const data = await readFolder(page, token)
    const stored = data.files.find((f) => f.name === file.name)
    expect(stored.size, 'размер совпадает с исходным').toBe(file.size)

    expect(errors, `ошибки JS: ${errors.join('; ')}`).toEqual([])
  })

  test('повторная загрузка того же имени не затирает первый файл', async ({ page }) => {
    const token = await auth(page)
    await openManager(page)

    const first = await uploadFile(page, `${PREFIX}дубль.bin`, 4096, 'a')
    await waitForQueueItem(page, first.name)
    await expect
      .poll(async () => (await readFolder(page, token)).files.filter((f) => f.name === first.name).length, {
        timeout: 60000,
      })
      .toBe(1)

    // Второй файл с тем же именем: сервер обязан дописать «(2)», а не
    // перезаписать содержимое первого.
    const second = await uploadFile(page, `${PREFIX}дубль.bin`, 8192, 'b')
    await waitForQueueItem(page, second.name)

    await expect
      .poll(async () => (await readFolder(page, token)).files.filter((f) => f.name.startsWith(`${PREFIX}дубль`)).length, {
        timeout: 60000,
      })
      .toBe(2)

    const files = (await readFolder(page, token)).files.filter((f) => f.name.startsWith(`${PREFIX}дубль`))
    const sizes = files.map((f) => f.size).sort((a, b) => a - b)

    // 4096 и 8192 — оба на месте. Если бы второй перезаписал первый,
    // остался бы один файл в 8192.
    expect(sizes, 'оба файла целы, размеры разные').toEqual([4096, 8192])
  })

  test('мультизагрузка: несколько файлов в одном выборе', async ({ page }) => {
    const token = await auth(page)
    await openManager(page)

    const names = [`${PREFIX}м1.bin`, `${PREFIX}м2.bin`, `${PREFIX}м3.bin`]

    await page.setInputFiles('[data-test="fm-file-input"]', [
      { name: names[0], mimeType: 'application/octet-stream', buffer: Buffer.alloc(5000, 1) },
      { name: names[1], mimeType: 'application/octet-stream', buffer: Buffer.alloc(6000, 2) },
      { name: names[2], mimeType: 'application/octet-stream', buffer: Buffer.alloc(7000, 3) },
    ])

    // Все три карточки появились сразу: очередь не ждёт конца файла.
    for (const name of names) {
      await waitForQueueItem(page, name)
    }

    await expect
      .poll(
        async () => {
          const data = await readFolder(page, token)
          return names.filter((n) => data.files.some((f) => f.name === n)).length
        },
        { timeout: 90000, message: 'не все файлы загрузились' }
      )
      .toBe(3)

    const files = (await readFolder(page, token)).files.filter((f) => names.includes(f.name))
    expect(files.map((f) => f.size).sort((a, b) => a - b)).toEqual([5000, 6000, 7000])
  })
})

test.describe('Файловый менеджер: папки', () => {
  test.beforeEach(async ({ page }) => {
    const token = await auth(page)
    await cleanupPrefixed(page, token)
  })

  test('папка создаётся, в неё входят, её удаляют', async ({ page }) => {
    const token = await auth(page)
    const errors = collectErrors(page)

    await openManager(page)

    const name = `${PREFIX}папка ${Date.now()}`
    await page.locator('[data-test="fm-new-folder"]').click()
    await page.locator('[data-test="fm-name-input"] input').fill(name)
    await page.locator('[data-test="fm-name-submit"]').click()

    // Регресс 3: папка видна в списке, а не только «создана».
    await expect(page.locator('.u-table tbody tr', { hasText: name })).toBeVisible({ timeout: 15000 })

    const data = await readFolder(page, token)
    const folder = data.folders.find((f) => f.name === name)
    expect(folder, 'папка есть в ответе API').toBeTruthy()

    // Заходим внутрь и убеждаемся, что каталог действительно открывается.
    await page.locator(`[data-test="fm-folder-${folder.id}"]`).click()
    await expect(page.locator('[data-test="fm-breadcrumbs"]')).toContainText(name, { timeout: 15000 })
    // Пустой каталог рисуется EmptyState, а таблицей .u-table — только
    // когда есть строки. Проверять «.u-table» тут бессмысленно: её нет.
    await expect(page.getByText('В этой папке пока пусто')).toBeVisible({ timeout: 15000 })

    // Возврат в корень кнопкой-хлебной крошкой.
    await page.locator('[data-test="fm-breadcrumbs"] button').first().click()
    await expect(page.locator('.u-table tbody tr', { hasText: name })).toBeVisible({ timeout: 15000 })

    await dropFolder(page, token, folder.id)

    expect(errors, `ошибки JS: ${errors.join('; ')}`).toEqual([])
  })

  test('непустая папка не удаляется молча', async ({ page }) => {
    const token = await auth(page)
    const folderName = `${PREFIX}непустая ${Date.now()}`
    const folderId = await makeFolder(page, token, folderName)

    // Страница открывается ПОСЛЕ создания папки: список читается при
    // загрузке, и папка, созданная позже, в нём не появилась бы сама.
    await openManager(page)

    const file = await uploadFile(page, `${PREFIX}внутри.txt`, 2048)
    await waitForQueueItem(page, file.name)
    await expect
      .poll(async () => (await readFolder(page, token)).files.filter((f) => f.name === file.name).length, {
        timeout: 60000,
      })
      .toBe(1)

    // Загруженный файл лежит в корне, поэтому переносим его в папку через
    // API, и уже затем входим внутрь. Через DOM: файл загрузился ДО
    // открытия папки, и «перетащить» его в строках нечем.
    const uploaded = (await readFolder(page, token)).files.find((f) => f.name === file.name)
    expect(uploaded, 'файл загружен в корень').toBeTruthy()

    const moved = await page.request.post(BASE + '/api/filemanager/files/move', {
      headers: { Authorization: 'Bearer ' + token },
      data: { ids: [uploaded.id], folder_id: folderId },
    })
    expect(moved.ok(), 'перенос файла в папку').toBeTruthy()

    // Перечитываем страницу: мы в корне, а первая крошка — это корень,
    // он disabled, и кликнуть по нему нельзя (и незачем).
    await openManager(page)
    await page.locator(`[data-test="fm-folder-${folderId}"]`).click()
    await expect(page.locator('[data-test="fm-breadcrumbs"]')).toContainText(folderName, { timeout: 15000 })

    // Первое нажатие «удалить» обязано спросить, а не снести содержимое.
    await page.locator('[data-test^="fm-delete-file-"]').first().click()

    await expect(page.locator('.confirm-dialog')).toBeVisible({ timeout: 10000 })
    await page.locator('.confirm-dialog').getByRole('button', { name: /^Удалить$/ }).click()

    // Подтверждение о непустой папке: файл обязан остаться на месте.
    await expect(page.locator('.u-table tbody tr', { hasText: file.name })).toBeVisible({ timeout: 15000 })

    await dropFolder(page, token, folderId)
  })
})

test.describe('Файловый менеджер: перенос и переименование', () => {
  test.beforeEach(async ({ page }) => {
    const token = await auth(page)
    await cleanupPrefixed(page, token)
  })

  test('файл переносится в папку и обратно', async ({ page }) => {
    const token = await auth(page)
    const errors = collectErrors(page)

    await openManager(page)

    const folderName = `${PREFIX}перенос ${Date.now()}`
    const folderId = await makeFolder(page, token, folderName)

    const file = await uploadFile(page, `${PREFIX}перемещаемый.txt`, 3000)
    // Папка создана до открытия страницы, но после последнего перечитывания
    // её могло не быть в таблице — сверяемся с API, а не с DOM.
    await waitForQueueItem(page, file.name)
    await expect
      .poll(async () => (await readFolder(page, token)).files.filter((f) => f.name === file.name).length, {
        timeout: 60000,
      })
      .toBe(1)

    // Регресс 4: проверяем результат через API, а не по картинке.
    const before = (await readFolder(page, token)).files.find((f) => f.name === file.name)
    const moved = await page.request.post(BASE + '/api/filemanager/files/move', {
      headers: { Authorization: 'Bearer ' + token },
      data: { ids: [before.id], folder_id: folderId },
    })
    expect(moved.ok(), 'перенос файла').toBeTruthy()

    // Файл лежит в папке, и размер на месте.
    const inFolder = (await readFolder(page, token, folderId)).files
    expect(inFolder.map((f) => f.name)).toContain(file.name)
    expect(inFolder.find((f) => f.name === file.name).size).toBe(file.size)
    expect((await readFolder(page, token)).files.map((f) => f.name)).not.toContain(file.name)

    // И обратно в корень.
    const back = await page.request.post(BASE + '/api/filemanager/files/move', {
      headers: { Authorization: 'Bearer ' + token },
      data: { ids: [before.id], folder_id: null },
    })
    expect(back.ok(), 'перенос обратно').toBeTruthy()

    expect((await readFolder(page, token)).files.map((f) => f.name)).toContain(file.name)

    await page.request
      .delete(`${BASE}/api/filemanager/files/${before.id}`, {
        headers: { Authorization: 'Bearer ' + token },
      })
      .catch(() => {})
    await dropFolder(page, token, folderId)

    expect(errors, `ошибки JS: ${errors.join('; ')}`).toEqual([])
  })

  test('переименование меняет имя на диске, а не только в таблице', async ({ page }) => {
    const token = await auth(page)
    // Тест сам вызывает отказ 422 (имя с путём) и проверяет, что он
    // показан пользователю, поэтому такой шум ожидаем.
    const errors = collectErrors(page, { httpNoise: true })
    await openManager(page)

    const before = await uploadFile(page, `${PREFIX}старое.txt`, 1500)
    await waitForQueueItem(page, before.name)
    await expect
      .poll(async () => (await readFolder(page, token)).files.filter((f) => f.name === before.name).length, {
        timeout: 60000,
      })
      .toBe(1)

    const stored = (await readFolder(page, token)).files.find((f) => f.name === before.name)

    /*
     * Переименование выполняется ЧЕРЕЗ ДИАЛОГ, а не вызовом API из теста.
     *
     * Причина практическая: `php artisan serve` (встроенный сервер PHP,
     * на котором поднят Playwright) при определённом порядке запросов на
     * одном соединении отвечает на PATCH «The PATCH method is not
     * supported for route /» — то есть разбирает не тот запрос. Через
     * браузер (обычный XHR axios) тот же вызов проходит, и так работает
     * настоящий пользователь. Проверять надо ИМЕННО этот путь.
     */
    const newName = `${PREFIX}новое.txt`
    await page.locator(`[data-test="fm-rename-file-${stored.id}"]`).click()
    await page.locator('[data-test="fm-name-input"] input').fill(newName)
    await page.locator('[data-test="fm-name-submit"]').click()

    // Регресс 4: проверяем результат через API, а не по нарисованной строке.
    await expect
      .poll(
        async () => {
          const files = (await readFolder(page, token)).files
          return files.filter((f) => f.name === newName).length
        },
        { timeout: 20000, message: 'файл не переименован' }
      )
      .toBe(1)

    const files = (await readFolder(page, token)).files
    expect(files.map((f) => f.name)).not.toContain(before.name)

    // Содержимое сохранилось: размер прежний.
    expect(files.find((f) => f.name === newName).size).toBe(before.size)

    /*
     * Имя с путём наружу обязано быть ОТВЕРГНУТО с объяснением у поля,
     * а не «молча вычищено»: молчаливая очистка создала бы файл с
     * именем, которого пользователь не просил.
     */
    await page.locator(`[data-test="fm-rename-file-${stored.id}"]`).click()
    await page.locator('[data-test="fm-name-input"] input').fill('../побег.txt')
    await page.locator('[data-test="fm-name-submit"]').click()

    // Ошибка показана в диалоге, и он остался открытым.
    await expect(page.locator('.v-messages__message')).toContainText(/запрещены символы|точка в начале/i, {
      timeout: 15000,
    })

    // Файл на месте под прежним именем — его не переименовали «наполовину».
    const after = (await readFolder(page, token)).files
    expect(after.map((f) => f.name)).toContain(newName)
    expect(after.map((f) => f.name)).not.toContain(before.name)

    await page.keyboard.press('Escape')
    await page.request
      .delete(`${BASE}/api/filemanager/files/${stored.id}`, {
        headers: { Authorization: 'Bearer ' + token },
      })
      .catch(() => {})

    expect(errors, `ошибки JS: ${errors.join('; ')}`).toEqual([])
  })

  test('выход из папки кнопкой «назад» возвращает в корень', async ({ page }) => {
    const token = await auth(page)

    const folderName = `${PREFIX}навигация ${Date.now()}`
    const folderId = await makeFolder(page, token, folderName)

    await openManager(page)
    await page.locator(`[data-test="fm-folder-${folderId}"]`).click()
    await expect(page.locator('[data-test="fm-breadcrumbs"]')).toContainText(folderName, { timeout: 15000 })

    await page.locator('[data-test="fm-breadcrumbs"] button').first().click()
    await expect(page.locator('.u-table tbody tr', { hasText: folderName })).toBeVisible({ timeout: 15000 })

    await dropFolder(page, token, folderId)
  })
})

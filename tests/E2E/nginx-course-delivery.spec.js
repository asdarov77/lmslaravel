// @ts-check
/**
 * Раздача материала курсов через nginx (X-Accel-Redirect) — проверка
 * настоящим браузером.
 *
 * ПОЧЕМУ ОТДЕЛЬНЫЙ ФАЙЛ, А НЕ ЕЩЁ ОДИН ТЕСТ В course-material.spec.js.
 * Здесь поднимается не `php artisan serve`, а nginx перед ним: именно
 * nginx решает, отдан ли файл целиком. Проверка «через artisan serve»
 * этого узла не касается — там всегда отдаёт PHP.
 *
 * КАК ЗАПУСКАЕТСЯ. Самостоятельно тест ничего не поднимает: он ждёт,
 * что nginx уже работает (tools/verify-nginx-delivery.sh поднимает его и
 * включает режим). Если nginx не отвечает, тест ПРОПУСКАЕТСЯ с внятным
 * объяснением, а не падает: в обычном `npm run test:e2e` nginx на
 * машине может не быть, и ронять из-за этого весь прогон нельзя.
 *
 *   tools/verify-nginx-delivery.sh     # всё: поднять, проверить, погасить
 *   BASE_URL=http://127.0.0.1:8099 npx playwright test nginx-course-delivery
 *
 * ЧТО ПРОВЕРЯЕТСЯ.
 *  1. Страница материала открывается и содержит текст курса, а не пустое
 *     тело со статусом 200. Пустое тело — ровно тот симптом, который
 *     даёт неверно настроенный X-Accel-Redirect, и без проверки
 *     содержимого он неотличим от успеха.
 *  2. Вложенные ресурсы (стили, скрипты, картинки) приходят с кодом 200:
 *     подпись наследуется относительными ссылками, и обрыв этого
 *     механизма ломает страницу целиком.
 *  3. Материал НЕ отдаётся напрямую: /storage/private/... и
 *     /_protected-content/... отвечают 404. Иначе подпись обходится
 *     обычной ссылкой.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL || process.env.NGINX_BASE_URL || 'http://127.0.0.1:8099'
const API = process.env.API_URL || BASE
const ADMIN = { fio: process.env.ADMIN_FIO || 'Администратор', password: process.env.ADMIN_PASSWORD || '123' }

/** nginx отвечает? Иначе тест не имеет смысла и должен быть пропущен. */
async function nginxIsUp() {
  try {
    const res = await fetch(`${API}/api/health`, { signal: AbortSignal.timeout(3000) })
    return res.ok
  } catch (error) {
    return false
  }
}

/** Вход через API + токен в localStorage. */
async function auth(page) {
  const res = await page.request.post(API + '/api/login', { data: ADMIN })
  expect(res.ok(), 'логин администратора через nginx').toBeTruthy()
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
 * Находит реальную пару (самолёт, АУК) и файл материала.
 *
 * Идёт ровно той дорогой, которой идёт интерфейс:
 *   /api/courses → манифест курса → пункт с материалом → /api/getlink/{id}
 *   → { aircraft, auk, file } → /api/private/signed-url.
 *
 * Почему не «взять первый каталог на диске»: подписывается пара
 * (самолёт, каталог АУК), и она должна совпадать с базой — иначе
 * проверка падала бы с 404 на исправной системе. Именно поэтому в
 * боевой конфиг добавлен инструмент для таких проверок.
 *
 * @returns {Promise<{aircraft: string, auk: string, file: string} | null>}
 */
async function resolveMaterial(page, token) {
  const headers = { Authorization: 'Bearer ' + token }
  const json = async (url) => (await page.request.get(API + url, { headers })).json()

  const courses = (await json('/api/courses'))?.data ?? []
  if (!Array.isArray(courses) || courses.length === 0) return null

  for (const course of courses.slice(0, 5)) {
    const mani = (await json('/api/course?course_id=' + course.id))?.data
    const modules = (Array.isArray(mani) ? mani[0]?.aukstructures : mani?.aukstructures) ?? []

    // type 3 — модуль, у которого есть файл материала.
    for (const item of modules.filter((a) => a.type === 3).slice(0, 8)) {
      const target = (await json('/api/getlink/' + item.id))?.data

      if (target?.aircraft && target?.auk && target?.file) {
        return target
      }
    }
  }

  return null
}

/** Подписанный префикс + имя файла. Префикс приходит без ведущего слеша. */
async function signedMaterial(page, token, target) {
  const res = await page.request.get(
    API + '/api/private/signed-url?aircraft=' +
      encodeURIComponent(target.aircraft) +
      '&auk=' +
      encodeURIComponent(target.auk),
    { headers: { Authorization: 'Bearer ' + token } }
  )
  expect(res.ok(), 'подписанный URL').toBeTruthy()

  const base = (await res.json())?.data?.base ?? ''
  expect(base, 'подписанный префикс непуст').toBeTruthy()

  return (base.startsWith('/') ? base : '/' + base) + encodeURIComponent(target.file).replace(/%2F/g, '/')
}

test.describe('Раздача материала через nginx', () => {
  test.beforeEach(async () => {
    test.skip(
      !(await nginxIsUp()),
      `nginx не отвечает на ${API}. Поднять: tools/verify-nginx-delivery.sh`
    )
  })

  test('материал курса открывается и отдаётся целиком', async ({ page }) => {
    const token = await auth(page)

    const target = await resolveMaterial(page, token)
    test.skip(!target, 'не нашлось курса с материалом — импортируйте курс')

    const signed = await signedMaterial(page, token, target)
    const response = await page.goto(BASE + signed, { waitUntil: 'domcontentloaded' })

    expect(response?.status(), 'статус материала').toBe(200)

    const html = await response.text()

    // Главная проверка. В режиме nginx Laravel отвечает 200 с ПУСТЫМ
    // телом и заголовком X-Accel-Redirect; если nginx его не обработал,
    // страница «успешно» открывается пустой. По статусу это не отличить.
    expect(
      html.trim().length,
      'тело материала пустое — X-Accel-Redirect не обработан nginx'
    ).toBeGreaterThan(200)

    expect(html, 'это должен быть HTML-материал').toMatch(/<html|<!doctype|<body/i)
  })

  test('вложенные ресурсы материала отдаются с кодом 200', async ({ page }) => {
    const token = await auth(page)

    const target = await resolveMaterial(page, token)
    test.skip(!target, 'не нашлось курса с материалом — импортируйте курс')

    const signed = await signedMaterial(page, token, target)
    const response = await page.goto(BASE + signed, { waitUntil: 'load' })

    const html = await response.text()

    // Ресурсы берём из САМОГО материала: у разных курсов разметка разная,
    // и выдуманное имя дало бы 404 на исправной системе.
    const refs = [...html.matchAll(/(?:href|src)=["']([^"']+\.(?:css|js|png|jpe?g|gif|svg|woff2?))["']/gi)]
      .map((m) => m[1])
      // Внешние и абсолютные адреса — не наше дело.
      .filter((href) => !/^([a-z]+:)?\/\//i.test(href) && !href.startsWith('data:'))
      .slice(0, 5)

    test.skip(refs.length === 0, 'в материале нет вложенных ресурсов — нечего проверять')

    // Каталог — всё до имени файла. Имена самой последней части
    // (index.html) в пути нет, поэтому отрезаем последний сегмент.
    const dir = signed.replace(/[^/]*$/, '')

    for (const ref of refs) {
      const url = new URL(ref, BASE + dir).toString()
      const res = await page.request.get(url)

      expect(
        res.status(),
        `вложенный ресурс ${ref} не отдан (подпись должна наследоваться в префиксе)`
      ).toBe(200)

      /*
       * Проверять «тело непустое» здесь нельзя: в SCORM-пакетах курсов
       * встречаются настоящие файлы нулевого размера — например
       * app/bower_components/normalize.css/jquery-ui.min.css лежит на диске
       * пустым. Такой ресурс и должен прийти пустым, и требование
       * «непустое» ловило бы исправную систему.
       *
       * Что действительно важно — ресурс отдан (200), а не 404/403,
       * то есть подпись в пути наследуется вложенными ссылками.
       */
    }
  })

  test('материал недоступен в обход проверки подписи', async ({ page }) => {
    // Три независимых способа обойти защиту. Каждый обязан давать 404.
    const attempts = [
      { name: 'внутренний location', url: `${BASE}/_protected-content/private/%D0%9A%D0%9B%D0%95%D0%9D/02/index.html` },
      { name: 'каталог контента', url: `${BASE}/storage/private/%D0%9A%D0%9B%D0%95%D0%9D/02/index.html` },
      { name: 'приватный путь без подписи', url: `${BASE}/api/private/private/%D0%9A%D0%9B%D0%95%D0%9D/02/index.html` },
    ]

    for (const attempt of attempts) {
      const res = await page.request.get(attempt.url)

      // 404 ИЛИ 403 — оба означают «материал не отдан», и разница здесь
      // не про безопасность: внутренний location nginx отвечает 404, а
      // middleware проверки подписи — 403 (см.
      // ValidatePrivateContentSignature). Важно одно: НЕ 200.
      expect(
        [403, 404],
        `${attempt.name}: получен ${res.status()}, материал отдан в обход проверки подписи`
      ).toContain(res.status())

      const body = await res.body()
      expect(body.length, `${attempt.name}: в ответе есть содержимое`).toBeLessThan(4096)
    }
  })

  test('страница приложения открывается через nginx', async ({ page }) => {
    // nginx должен отдавать не только материал, но и саму оболочку:
    // сломанный try_files/root дал бы 404 на /index.php, и всё
    // приложение выглядело бы нерабочим при полностью исправной
    // раздаче материала.
    const res = await page.goto(BASE + '/api/health')
    expect(res.status()).toBe(200)

    const shell = await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
    expect(shell?.status(), 'оболочка приложения').toBe(200)
    expect(await shell.text()).toMatch(/<div id="app"|<script/i)
  })
})

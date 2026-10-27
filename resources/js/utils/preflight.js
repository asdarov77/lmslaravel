/**
 * Preflight-проверка раздачи приватного контента через nginx.
 *
 * ЗАЧЕМ. Режим `nginx` ломает материал ТИХО: приложение отвечает 200 с
 * ПУСТЫМ телом (X-Accel-Redirect никто не обработал), в логах нет
 * ошибок, страница выглядит как пустая. Об этом сказано в шапке
 * tools/verify-nginx-delivery.sh — там же описано, что полноценно
 * проверяет раздачу только поднятый рядом настоящий nginx.
 *
 * ЧТО МОЖЕТ ПРОВЕРИТЬ БРАУЗЕР. Точный ответ даёт только выдача реального
 * файла, для чего нужен подписанный URL материала. Но кое-что
 * проверяется честно: кто отдал 404 на внутренний location.
 *
 *   - `Server: nginx` → nginx стоит перед приложением, значит
 *     X-Accel-Redirect будет обработан, и останется убедиться, что
 *     location совпадает с accel_internal;
 *   - заголовка Server нет (или это PHP) → запрос ушёл в PHP, и
 *     переключать в nginx бессмысленно: файлы придут пустыми.
 *
 * Различить «nginx настроен верно» и «nginx есть, но location не
 * прописан» из браузера нельзя (в обоих случаях 404 от nginx) — это
 * честно сказано в вердикте, и рядом дан кусок конфига.
 */

const HTTP_SERVERS = ['nginx', 'apache', 'caddy', 'litespeed']

/** Извлечь имя сервера из заголовков (в jsdom/axios это объект). */
const serverFrom = (headers) => {
  const raw = headers?.['server'] ?? headers?.Server ?? ''
  const value = Array.isArray(raw) ? raw.join(',') : String(raw ?? '')

  return value.trim()
}

/** Ответ fetch с теми же полями, что и у axios/браузера. */
const readResponse = async (response) => {
  const headers = {}
  response?.headers?.forEach?.((value, key) => {
    headers[String(key).toLowerCase()] = value
  })

  return { status: response?.status ?? 0, headers }
}

/**
 * Проба внутреннего location.
 *
 * @param {string} internalPath Значение settings.accel_internal.
 * @param {string} baseUrl Корень приложения (для относительного пути).
 * @param {Function} fetchImpl fetch (подменяется в тестах).
 * @returns {Promise<{ok: boolean, status: number, server: string, isNginx: boolean, verdict: string, detail: string}>}
 */
export const probeDelivery = async (internalPath, baseUrl = '', fetchImpl = globalThis.fetch) => {
  const path = String(internalPath || '').startsWith('/')
    ? String(internalPath || '')
    : `/${internalPath || ''}`

  const url = `${String(baseUrl || '').replace(/\/$/, '')}${path}/__preflight__`

  let status = 0
  let server = ''

  try {
    const response = await fetchImpl(url, { method: 'GET', redirect: 'manual', credentials: 'same-origin' })
    const read = await readResponse(response)
    status = read.status
    server = serverFrom(read.headers)
  } catch (error) {
    return {
      ok: false,
      status: 0,
      server: '',
      isNginx: false,
      verdict: 'unreachable',
      detail: error?.message ?? '',
    }
  }

  const isNginx = /nginx/i.test(server)
  const isKnownServer = HTTP_SERVERS.some((name) => new RegExp(name, 'i').test(server))

  if (isNginx) {
    return {
      ok: true,
      status,
      server,
      isNginx: true,
      verdict: 'nginx-in-front',
      detail: 'nginx отдал 404 на внутренний location — это ожидаемо: он помечен internal. Осталось убедиться, что location совпадает со значением из таблицы.',
    }
  }

  if (isKnownServer) {
    return {
      ok: false,
      status,
      server,
      isNginx: false,
      verdict: 'other-server',
      detail: `Запрос обработал ${server}. X-Accel-Redirect поддерживает только nginx: в режиме nginx материалы будут приходить пустыми.`,
    }
  }

  return {
    ok: false,
    status,
    server,
    isNginx: false,
    verdict: 'php-direct',
    detail: 'Ответ пришёл без заголовка Server — запрос обработал PHP (например, artisan serve или php-fpm без прокси). Переключать в nginx бессмысленно: файлы будут пустыми.',
  }
}

/**
 * Кусок конфига nginx, который администратор должен вписать.
 * Значение internal берётся из настроек — иначе «угадать» его нельзя.
 */
export const nginxSnippet = (internalPath) => `location ${internalPath || '/_protected-content'} {
    internal;
    alias ${internalPath || '/_protected-content'};
}`

export default { probeDelivery, nginxSnippet }

/**
 * M3: контракт SSE-клиента TutorDock (resources/js/api/tutor.api.js).
 *
 * Проверяем РОВНО то, что ломается в реальном продакшене и что нельзя
 * проверить PHP-тестами: разбор кадров text/event-stream поверх POST.
 * Формат сервера зафиксирован в TutorController::chat — тест зеркалит его
 * 1-в-1 (event: meta → data {"t":…} токенами → event: done/error), иначе
 * док молча перестанет печатать ответ при смене формата на сервере.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { streamTutorChat } from '../../resources/js/api/tutor.api'
import { TokenService } from '../../resources/js/services/storage.service'

const encoder = new TextEncoder()

/** Нормализуем кусок стрима в Uint8Array (тесты шлют и строки, и байты). */
function asBytes(c) {
  return typeof c === 'string' ? encoder.encode(c) : c
}

/** Response-подобный объект со стримом из перечисленных кусков. */
function sseResponse(chunks, { ok = true, status = 200, headers = {} } = {}) {
  return {
    ok,
    status,
    headers: { get: (k) => headers[k] ?? headers[k.toLowerCase()] ?? null },
    body: {
      getReader() {
        let i = 0
        return {
          read: async () =>
            i < chunks.length
              ? { value: asBytes(chunks[i++]), done: false }
              : { value: undefined, done: true },
          cancel: async () => {},
        }
      },
    },
  }
}

/** JSON-ответ «до начала стрима» (503 движок / 429 лимит / 403 права). */
function jsonResponse(body, { ok = false, status = 500, headers = {} } = {}) {
  return {
    ok,
    status,
    headers: { get: (k) => headers[k] ?? headers[k.toLowerCase()] ?? null },
    json: async () => body,
    body: null,
  }
}

describe('streamTutorChat: запрос', () => {
  let fetchMock

  beforeEach(() => {
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    TokenService.getToken = vi.fn(() => 'tok-123')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('POST /api/v1/tutor/chat с Bearer-токеном и Accept: text/event-stream', async () => {
    fetchMock.mockResolvedValue(sseResponse(['event: done\ndata: {"truncated":false}\n\n']))

    await streamTutorChat({ course_id: 5, question: 'Почему?' }, {})

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/tutor/chat')
    expect(init.method).toBe('POST')
    expect(init.headers.Authorization).toBe('Bearer tok-123')
    expect(init.headers.Accept).toBe('text/event-stream')
    expect(JSON.parse(init.body)).toMatchObject({ course_id: 5, question: 'Почему?' })
  })

  it('без токена заголовок Authorization не ставится (гость не получит 403-шум)', async () => {
    TokenService.getToken = vi.fn(() => null)
    fetchMock.mockResolvedValue(sseResponse(['data: {"t":"ok"}\n\n']))

    await streamTutorChat({ course_id: 1, question: 'q' }, {})

    const [, init] = fetchMock.mock.calls[0]
    expect(init.headers.Authorization).toBeUndefined()
  })

  it('сетевая отмена (AbortError при fetch) — штатное завершение, не ошибка', async () => {
    const err = new Error('aborted')
    err.name = 'AbortError'
    fetchMock.mockRejectedValue(err)

    await expect(
      streamTutorChat({ course_id: 1, question: 'q' }, { onError: vi.fn() }),
    ).resolves.toBeUndefined()
  })
})

describe('streamTutorChat: разбор SSE-кадров', () => {
  let fetchMock

  beforeEach(() => {
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    TokenService.getToken = vi.fn(() => 'tok')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('meta → токены → done: порядок и содержимое колбэков', async () => {
    fetchMock.mockResolvedValue(
      sseResponse([
        'event: meta\ndata: {"model":"qwen","grounded":true,"truncated":false}\n\n',
        'data: {"t":"При"}\n\ndata: {"t":"вет"}\n\n',
        'event: done\ndata: {"truncated":false}\n\n',
      ]),
    )

    const onMeta = vi.fn(), onToken = vi.fn(), onDone = vi.fn(), onError = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onMeta, onToken, onDone, onError })

    expect(onMeta).toHaveBeenCalledWith({ model: 'qwen', grounded: true, truncated: false })
    expect(onToken.mock.calls.map((c) => c[0]).join('')).toBe('Привет')
    expect(onDone).toHaveBeenCalledWith({ truncated: false })
    expect(onError).not.toHaveBeenCalled()
  })

  it('кадр разрезан между чанками на середине слова — склейка по \\n\\n обязательна', async () => {
    // Классический облом SSE-парсеров: TCP режет кадры как хочет.
    fetchMock.mockResolvedValue(
      sseResponse(['data: {"t":"аб', 'вг"}\n\nevent: done\ndata: {"truncated":false}\n\n']),
    )

    const onToken = vi.fn(), onDone = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onToken, onDone })

    expect(onToken).toHaveBeenCalledTimes(1)
    expect(onToken).toHaveBeenCalledWith('абвг')
    expect(onDone).toHaveBeenCalled()
  })

  it('кириллица не ломается на границе чанка внутри UTF-8 символа', async () => {
    // «д» (U+0434) кодируется байтами D0 B4. Режем строго между ними,
    // чтобы ни один чанк не кончался целым кадром и decode({stream:true})
    // обязан был дождаться второго куска. Оффсеты фиксированы по шаблону
    // `data: {"t":"` (11 ASCII-байт) — без поиска по байтам, который
    // зависит от реализации TextEncoder в окружении теста.
    const bytes = encoder.encode('data: {"t":"д"}\n\n')
    const cut = 13 // D0 на позиции 12, B4 на 13: режем между байтами символа
    expect(bytes[cut]).toBe(0xb4)
    const chunks = [bytes.slice(0, cut), bytes.slice(cut)]

    fetchMock.mockResolvedValue(sseResponse(chunks))

    const onToken = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onToken })

    expect(onToken).toHaveBeenCalledWith('д')
  })

  it('повреждённый JSON-кадр игнорируется, поток продолжается', async () => {
    fetchMock.mockResolvedValue(
      sseResponse([
        'data: {битый\n\n',
        'data: {"t":"целое"}\n\n',
        'event: done\ndata: {"truncated":true}\n\n',
      ]),
    )

    const onToken = vi.fn(), onDone = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onToken, onDone })

    expect(onToken).toHaveBeenCalledTimes(1)
    expect(onToken).toHaveBeenCalledWith('целое')
    expect(onDone).toHaveBeenCalledWith({ truncated: true })
  })

  it('event: error после начала стрима уходит в onError без status', async () => {
    fetchMock.mockResolvedValue(
      sseResponse([
        'event: meta\ndata: {"grounded":false}\n\n',
        'event: error\ndata: {"message":"Движок упал"}\n\n',
      ]),
    )

    const onToken = vi.fn(), onError = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onToken, onError })

    expect(onError).toHaveBeenCalledWith('Движок упал', {})
    expect(onToken).not.toHaveBeenCalled()
  })

  it('хвост буфера без завершающего пустого кадра дочитывается', async () => {
    // Сервер оборвался на done без финального \n\n — клиент обязан разобрать.
    fetchMock.mockResolvedValue(sseResponse(['event: done\ndata: {"truncated":false}\n']))

    const onDone = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onDone })

    expect(onDone).toHaveBeenCalledWith({ truncated: false })
  })
})

describe('streamTutorChat: ошибки до начала стрима (envelope)', () => {
  let fetchMock

  beforeEach(() => {
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    TokenService.getToken = vi.fn(() => 'tok')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('429 throttle:chat: retryAfter из envelope уходит в onError (реальный кулдаун дока)', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(
        { success: false, data: null, error: 'Слишком много вопросов тьютору, подождите', meta: { engineState: null, retryAfter: 17 } },
        { status: 429 },
      ),
    )

    const onError = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onError })

    expect(onError).toHaveBeenCalledWith(
      'Слишком много вопросов тьютору, подождите',
      { status: 429, engineState: null, retryAfter: 17 },
    )
  })

  it('429 без meta.retryAfter: секунды берутся из заголовка Retry-After', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ error: 'Лимит' }, { status: 429, headers: { 'Retry-After': '33' } }),
    )

    const onError = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onError })

    expect(onError.mock.calls[0][1].retryAfter).toBe(33)
  })

  it('503 недоступного движка: engineState прокидывается для подписи в доке', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(
        { success: false, data: null, error: 'Нейродвижок недоступен', meta: { engineState: 'connection refused' } },
        { status: 503 },
      ),
    )

    const onError = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onError })

    expect(onError).toHaveBeenCalledWith(
      'Нейродвижок недоступен',
      { status: 503, engineState: 'connection refused', retryAfter: null },
    )
  })

  it('не-JSON тело ошибки: остаётся код HTTP N, onError не кидает исключение', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 502,
      headers: { get: () => null },
      json: async () => { throw new SyntaxError('<html>') },
      body: null,
    })

    const onError = vi.fn()
    await streamTutorChat({ course_id: 1, question: 'q' }, { onError })

    expect(onError).toHaveBeenCalledWith('HTTP 502', { status: 502, engineState: null, retryAfter: null })
  })

  it('без onError обработчика ошибка бросается наружу (вызывающий решит сам)', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ error: 'Запрещено' }, { status: 403 }))

    await expect(streamTutorChat({ course_id: 1, question: 'q' }, {})).rejects.toThrow('Запрещено')
  })
})

// @vitest-environment jsdom
/**
 * Юнит-тесты useLessonLoader — плеер v2, S1 (docs/course-player-blueprint.md §4/§5).
 * Критерий приёмки S1: «гонка — 5 кликов подряд → рендер последнего».
 *
 * Покрывают механику race-guard из комментария модуля:
 *  1) loadToken — проигравшие ответы отбраковываются логически;
 *  2) AbortController — предыдущий запрос отменяется физически;
 *  3) docCache — повторное открытие без сети, force — с сетью;
 *  4) signed-TTL — подпись живёт до exp−60с и не перевыпускается на перематке;
 *  5) prefetch соседа через 1.5с и его отмена при смене урока;
 *  6) onScopeDispose — контроллер и таймер умирают вместе со scope.
 *
 * api подменяется целиком (useLessonLoader принимает api инъекцией), поэтому
 * мокировать player.api / axios здесь не нужно.
 */
import { describe, it, expect, vi, afterEach } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { useLessonLoader } from '../../resources/js/composables/useLessonLoader'

const COURSE = 7

/* ─────────────── управляемые промисы (детерминированная гонка) ─────────────── */

let pending // Map<key, promise-with-resolve/reject> — создаётся в makeApi

function deferred(key, build) {
  let resolve, reject
  const p = new Promise((res, rej) => { resolve = res; reject = rej })
  p.__resolve = (extra) => resolve(build(extra || {}))
  p.__reject = (e) => reject(e)
  pending.set(key, p)
  return p
}

/** Разрешить все промисы, чей ключ начинается с префикса (в порядке создания). */
async function settleAll(prefix, extra) {
  for (const [key, p] of [...pending.entries()]) {
    if (key.startsWith(prefix)) {
      p.__resolve(typeof extra === 'function' ? undefined : extra)
      pending.delete(key)
    }
  }
  await flush()
}

async function flush(times = 8) {
  for (let i = 0; i < times; i++) await nextTick()
}

function failWith(key, err) {
  const p = pending.get(key)
  expect(p, `нет ожидающего промиса ${key}`).toBeTruthy()
  p.__reject(err)
  pending.delete(key)
}

function makeApi(overrides = {}) {
  pending = new Map()
  const calls = { fetchLesson: [], fetchLessonDocument: [], fetchSignedDelivery: [], prefetchLesson: [] }
  const api = {
    fetchLesson: vi.fn(async (courseId, lessonId, { signal } = {}) => {
      calls.fetchLesson.push({ lessonId, signal })
      return deferred(`lesson:${lessonId}`, (r) => ({
        id: Number(lessonId),
        title: `Урок ${lessonId}`,
        content_type: 'html',
        access: { allowed: true },
        ...r,
      }))
    }),
    fetchLessonDocument: vi.fn(async (courseId, lessonId, { signal } = {}) => {
      calls.fetchLessonDocument.push({ lessonId, signal })
      return deferred(`doc:${lessonId}`, () => `<html>doc-${lessonId}</html>`)
    }),
    fetchSignedDelivery: vi.fn(async (courseId, lessonId) => {
      calls.fetchSignedDelivery.push({ lessonId })
      return deferred(`signed:${lessonId}`, () => ({ url: `/signed/${lessonId}`, mode: 'nginx', expires_in: 900 }))
    }),
    prefetchLesson: vi.fn(async (courseId, lessonId) => {
      calls.prefetchLesson.push({ lessonId })
    }),
    ...overrides,
  }
  return { api, calls }
}

let scope
function withScope(loaderOpts) {
  scope = effectScope()
  return scope.run(() => useLessonLoader(loaderOpts))
}

afterEach(() => {
  scope?.stop()
  scope = null
  pending = undefined
  vi.useRealTimers()
})

describe('useLessonLoader — гонка (race-guard)', () => {
  it('5 кликов подряд: готов только последний урок, проигравшие отбракованы', async () => {
    const { api } = makeApi()
    const loader = withScope({ courseId: COURSE, api })

    // Пять load() без ожидания — классическая быстрая навигация по дереву.
    for (const id of [1, 2, 3, 4, 5]) loader.load(id)
    await flush()

    // Ответы приходят в «плохом» порядке: detail старых раньше новых,
    // документы — в обратном.
    await settleAll('lesson:')
    await settleAll('doc:', undefined)

    await vi.waitFor(() => expect(loader.status.value).toBe('ready'))
    expect(loader.lesson.value.id).toBe(5)
    expect(loader.lesson.value.documentHtml).toBe('<html>doc-5</html>')
    expect(loader.epoch.value).toBe(1) // epoch bump'ит только победитель
  })

  it('опоздавший ответ предыдущего урока не затирает состояние нового', async () => {
    const { api } = makeApi()
    const loader = withScope({ courseId: COURSE, api })

    loader.load(1)
    await flush()
    loader.load(2) // урок 1 ещё в полёте
    await flush()

    await settleAll('lesson:') // detail обоих уроков приходит сейчас
    await settleAll('doc:')    // и оба документа

    await vi.waitFor(() => expect(loader.status.value).toBe('ready'))
    expect(loader.lesson.value.id).toBe(2)
    expect(loader.epoch.value).toBe(1)
  })

  it('предыдущий запрос отменяется физически (AbortController.signal.aborted)', async () => {
    const { api, calls } = makeApi()
    const loader = withScope({ courseId: COURSE, api })

    loader.load(1)
    await flush()
    const firstSignal = calls.fetchLesson[0].signal
    expect(firstSignal.aborted).toBe(false)

    loader.load(2)
    expect(firstSignal.aborted).toBe(true)
    expect(calls.fetchLesson[1].signal.aborted).toBe(false)

    // Отменённый ответ не считается ошибкой и не трогает статус.
    failWith('lesson:1', Object.assign(new Error('canceled'), { name: 'CanceledError', code: 'ERR_CANCELED' }))
    await flush()
    expect(loader.status.value).toBe('loading')
    expect(loader.errorKey.value).toBeNull()
  })
})

describe('useLessonLoader — контент по типу', () => {
  it('повторное открытие урока берёт документ из кэша без сети, force — снова в сеть', async () => {
    const { api, calls } = makeApi()
    const loader = withScope({ courseId: COURSE, api })

    loader.load(1); await flush(); await settleAll('lesson:'); await settleAll('doc:')
    await vi.waitFor(() => expect(loader.status.value).toBe('ready'))
    expect(calls.fetchLesson).toHaveLength(1)
    expect(calls.fetchLessonDocument).toHaveLength(1)

    loader.load(2); await flush(); await settleAll('lesson:'); await settleAll('doc:')
    await vi.waitFor(() => expect(loader.lesson.value.id).toBe(2))

    // Возврат к уроку 1: detail из памяти (fetchLesson НЕ зовётся),
    // документ мгновенно из docCache.
    loader.load(1)
    await vi.waitFor(() => expect(loader.lesson.value.id).toBe(1))
    expect(calls.fetchLesson).toHaveLength(2)
    expect(calls.fetchLessonDocument).toHaveLength(2) // второй вызов — это урок 2

    // force — сброс кэша, сеть снова.
    loader.load(1, { force: true }); await flush(); await settleAll('lesson:'); await settleAll('doc:')
    await vi.waitFor(() => expect(calls.fetchLessonDocument).toHaveLength(3))
  })

  it('locked-узел: статус locked без запроса документа, epoch bump для remount', async () => {
    const { api, calls } = makeApi()
    const loader = withScope({ courseId: COURSE, api })

    loader.load(9); await flush()
    await settleAll('lesson:', { access: { allowed: false, reason: 'prerequisite' } })

    await vi.waitFor(() => expect(loader.status.value).toBe('locked'))
    expect(calls.fetchLessonDocument).toHaveLength(0)
    expect(loader.epoch.value).toBe(1)
  })

  it('video-урок: подпись не перевыпускается до exp−60с и перевыпускается после', async () => {
    const realNow = Date.now
    const { api, calls } = makeApi({
      fetchLesson: async (c, id) => ({ id: Number(id), content_type: 'video', access: { allowed: true } }),
    })
    const loader = withScope({ courseId: COURSE, api })

    loader.load(3); await flush(); await settleAll('lesson:'); await settleAll('signed:')
    await vi.waitFor(() => expect(loader.status.value).toBe('ready'))
    expect(loader.lesson.value.delivery.url).toBe('/signed/3')
    expect(loader.lesson.value.delivery.mode).toBe('nginx')
    expect(calls.fetchSignedDelivery).toHaveLength(1)

    // Повторное открытие того же урока — подпись свежая, сети нет.
    loader.load(3)
    await flush()
    expect(calls.fetchSignedDelivery).toHaveLength(1)

    // Протухание: время за TTL (900с минус запас 60с) — перевыпуск.
    Date.now = () => realNow() + 900_000
    loader.load(3); await flush(); await settleAll('signed:')
    await vi.waitFor(() => expect(calls.fetchSignedDelivery).toHaveLength(2))
    Date.now = realNow
  })
})

describe('useLessonLoader — ошибки', () => {
  it.each([
    [403, 'locked', 'coursePlayer.locked'],
    [401, 'error', 'coursePlayer.loginRequired'],
    [500, 'error', 'coursePlayer.loadError'],
  ])('HTTP %i → статус %s с i18n-ключом %s', async (code, status, key) => {
    const { api } = makeApi()
    const loader = withScope({ courseId: COURSE, api })
    loader.load(1); await flush()
    failWith('lesson:1', Object.assign(new Error(`HTTP ${code}`), { response: { status: code } }))
    await vi.waitFor(() => expect(loader.status.value).toBe(status))
    expect(loader.errorKey.value).toBe(key)
    expect(loader.epoch.value).toBe(1) // экран ошибки тоже обязан перемонтироваться
  })

  it('retry() перезагружает текущий урок с force', async () => {
    const { api, calls } = makeApi()
    const loader = withScope({ courseId: COURSE, api })
    loader.load(1); await flush(); await settleAll('lesson:'); await settleAll('doc:')
    await vi.waitFor(() => expect(loader.status.value).toBe('ready'))

    loader.retry(); await flush(); await settleAll('lesson:'); await settleAll('doc:')
    await vi.waitFor(() => expect(calls.fetchLessonDocument).toHaveLength(2))
    expect(loader.status.value).toBe('ready')
  })
})

describe('useLessonLoader — prefetch и утечки', () => {
  it('prefetch соседа стартует через 1.5с покоя; смена урока сбрасывает старый таймер', async () => {
    vi.useFakeTimers()
    const { api, calls } = makeApi({
      fetchLesson: async (c, id) => ({
        id: Number(id), content_type: 'html', access: { allowed: true },
        next_id: Number(id) + 1,
      }),
      fetchLessonDocument: async (c, id) => `<html>doc-${id}</html>`,
    })
    const loader = withScope({ courseId: COURSE, api })

    loader.load(1)
    await vi.advanceTimersByTimeAsync(0)
    expect(calls.prefetchLesson).toHaveLength(0) // сразу — нет

    await vi.advanceTimersByTimeAsync(1500)
    expect(calls.prefetchLesson).toEqual([{ lessonId: 2 }]) // через 1.5с — да

    // Смена урока до истечения паузы: таймер на prefetch(3) ставится заново,
    // вторичного prefetch(2) не происходит.
    loader.load(2)
    await vi.advanceTimersByTimeAsync(0)
    loader.load(3)
    await vi.advanceTimersByTimeAsync(1500)
    expect(calls.prefetchLesson.map((p) => p.lessonId)).toEqual([2, 4])
    vi.useRealTimers()
  })

  it('onScopeDispose: inflight-запрос отменён, prefetch-таймер снят', async () => {
    vi.useFakeTimers()
    const { api, calls } = makeApi({
      fetchLesson: async (c, id) => ({ id: Number(id), content_type: 'html', access: { allowed: true }, next_id: 2 }),
      fetchLessonDocument: async (c, id) => `<html>doc-${id}</html>`,
    })
    const loader = withScope({ courseId: COURSE, api })

    loader.load(1)
    await vi.advanceTimersByTimeAsync(0)
    const signal = calls.fetchLesson[0].signal

    scope.stop()
    scope = null
    expect(signal.aborted).toBe(true)

    // Если бы таймер не снялся, prefetch случился бы после unmount.
    await vi.advanceTimersByTimeAsync(5000)
    expect(calls.prefetchLesson).toHaveLength(0)
    vi.useRealTimers()
  })

  it('load(null) — no-op', async () => {
    const { api, calls } = makeApi()
    const loader = withScope({ courseId: COURSE, api })
    await loader.load(null)
    expect(calls.fetchLesson).toHaveLength(0)
    expect(loader.status.value).toBe('idle')
  })
})

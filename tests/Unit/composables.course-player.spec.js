// @vitest-environment jsdom
/**
 * Контрактные юнит-тесты composables нового плеера (CourseManifestNew).
 *
 * Главная цель — не допустить повторения Д2: фронт читал entry.signed_base,
 * сервер клал signed_path, и ни один мок этого не ловил, потому что моки
 * эмулировали СТАРЫЙ формат. Здесь моки повторяют контракт
 * tests/Feature/CoursePlayerEndpointTest.php 1-в-1:
 *   payload = { course, nodes[], tree[], entry{item_id,title,content_url},
 *               resume{last_item_id,last_anchor,percent}|null, stats{...} }
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref, nextTick } from 'vue'

/* ---------------------- mock httpClient (общий для api) ---------------------- */

/* vi.mock hoisted наверх файла — переменная внутри фабрики недоступна
   ("Cannot access 'api' before initialization"). Единственный способ
   получить общий мок — vi.hoisted. */
const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: api }))

import { fetchPlayerPayload, contentFrameUrl } from '../../resources/js/api/player.api'
import { useCoursePlayer } from '../../resources/js/composables/useCoursePlayer'
import { useContentFrame } from '../../resources/js/composables/useContentFrame'
import { useInDocumentSearch } from '../../resources/js/composables/useInDocumentSearch'

/* ------------------------------- фикстуры ---------------------------------- */

const node = (id, extra = {}) => ({
  id,
  parent_id: 0,
  title: `Узел ${id}`,
  type: 3,
  is_material: true,
  has_link: true,
  content_url: `/api/course/7/content/${id}`,
  visited: false,
  ...extra,
})

const playerPayload = (overrides = {}) => ({
  data: {
    course: { id: 7, title: 'Курс', path: 'c', aircraft_id: 3, aircraft_path: 'a' },
    nodes: [
      node(1),
      node(2),
      node(10, { type: 2, is_material: false, has_link: false, content_url: null, title: 'Раздел' }),
    ],
    tree: [{ id: 10, children: [node(1), node(2)] }],
    entry: { item_id: 1, title: 'Узел 1', content_url: '/api/course/7/content/1' },
    resume: null,
    stats: { materials_total: 2, materials_visited: 0, percent: 0 },
    ...overrides,
  },
})

beforeEach(() => {
  api.get.mockReset()
  api.post.mockReset()
  api.delete.mockReset()
  localStorage.clear()
})

/* ============================ useCoursePlayer ============================== */

describe('useCoursePlayer — контракт player-endpoint (Д2, Д3, Д4)', () => {
  it('читает entry.content_url из payload без дополнительных RTT', async () => {
    api.get.mockResolvedValue(playerPayload())
    const { state, load } = useCoursePlayer(ref(7))

    await load()

    expect(api.get).toHaveBeenCalledTimes(1)
    expect(api.get).toHaveBeenCalledWith('/api/course/7/player')
    // Д2: фронт берёт ровно те ключи, которые кладёт сервер
    expect(state.entry).toEqual({
      item_id: 1,
      title: 'Узел 1',
      content_url: '/api/course/7/content/1',
    })
    expect(state.entry.signed_base).toBeUndefined()
    expect(state.entry.signed_path).toBeUndefined()
  })

  it('materials фильтруются по серверному is_material, а не по level (Д3)', async () => {
    api.get.mockResolvedValue(playerPayload())
    const { materials, materialIds, load } = useCoursePlayer(ref(7))

    await load()

    expect(materialIds.value).toEqual([1, 2])
    expect(materials.value.every((m) => m.is_material)).toBe(true)
  })

  it('resume приходит с сервера и сохраняется как есть (Д6)', async () => {
    api.get.mockResolvedValue(
      playerPayload({ resume: { last_item_id: 2, last_anchor: 'p-77', percent: 50 } })
    )
    const { state, load } = useCoursePlayer(ref(7))

    await load()

    expect(state.resume).toEqual({ last_item_id: 2, last_anchor: 'p-77', percent: 50 })
  })

  it('401 даёт понятную ошибку, loading сбрасывается', async () => {
    api.get.mockRejectedValue({ response: { status: 401 } })
    const { state, load } = useCoursePlayer(ref(7))

    await expect(load()).rejects.toBeTruthy()
    expect(state.loading).toBe(false)
    expect(state.error).toBe('Требуется вход в систему')
  })

  it('markVisited обновляет узел локально без перезагрузки', async () => {
    api.get.mockResolvedValue(playerPayload())
    const { load, nodeById, markVisited } = useCoursePlayer(ref(7))

    await load()
    markVisited(1)

    expect(nodeById.value.get(1).visited).toBe(true)
    expect(api.get).toHaveBeenCalledTimes(1) // без лишнего reload
  })
})

/* ============================ contentFrameUrl ============================== */

describe('contentFrameUrl — единый auth-контракт кадра (Д1)', () => {
  afterEach(() => vi.unstubAllEnvs())

  it('кладёт sanctum-токен в query, iframe не умеет заголовки', () => {
    vi.stubEnv('VITE_APP_URL', 'https://lms.test')
    localStorage.setItem('token', 'abc123')

    const url = contentFrameUrl(7, 42)

    expect(url).toBe('https://lms.test/api/course/7/content/42?t=abc123')
  })

  it('добавляет highlight только от 3 символов', () => {
    localStorage.setItem('token', 't')
    expect(contentFrameUrl(7, 1, { highlight: 'аб' })).not.toContain('highlight')
    expect(contentFrameUrl(7, 1, { highlight: 'авб' })).toContain('highlight=%D0%B0%D0%B2%D0%B1')
  })
})

/* ============================ useContentFrame ============================== */

describe('useContentFrame — высота и resume-скролл (Д6, Д9)', () => {
  function mountFrame() {
    const iframeRef = ref(null)
    const frame = useContentFrame(iframeRef)
    return { iframeRef, frame }
  }

  it('navigate ставит src и включает loading', () => {
    const el = document.createElement('iframe')
    document.body.appendChild(el)
    const { iframeRef, frame } = mountFrame()
    iframeRef.value = el

    frame.navigate('/api/course/7/content/1?t=x')

    expect(el.getAttribute('src')).toBe('/api/course/7/content/1?t=x')
    expect(frame.loading.value).toBe(true)
    el.remove()
  })

  it('onLoad пересчитывает высоту по body документа и подписывается на ResizeObserver (Д9)', () => {
    class RO { constructor(cb) { this.cb = cb } observe() {} disconnect() {} }
    const observed = []
    RO.prototype.observe = function (target) { observed.push(target) }
    vi.stubGlobal('ResizeObserver', RO)

    const el = document.createElement('iframe')
    Object.defineProperty(el, 'contentDocument', {
      value: { body: { scrollHeight: 1234, querySelectorAll: () => [] } },
      configurable: true,
    })
    const { iframeRef, frame } = mountFrame()
    iframeRef.value = el

    frame.onLoad()

    expect(frame.frameHeight.value).toBe(1234 + 24)
    expect(observed).toHaveLength(1) // следим за ростом body — картинки доезжают позже
    vi.unstubAllGlobals()
  })

  it('scrollToAnchor прокручивает к элементу по id или к численной позиции (Д6)', () => {
    const el = document.createElement('iframe')
    const target = document.createElement('div')
    const scrollIntoView = vi.fn()
    target.scrollIntoView = scrollIntoView
    const scrollTo = vi.fn()
    Object.defineProperty(el, 'contentDocument', {
      value: { getElementById: (id) => (id === 'sec-2' ? target : null) },
      configurable: true,
    })
    Object.defineProperty(el, 'contentWindow', {
      value: { scrollTo },
      configurable: true,
    })
    const { iframeRef, frame } = mountFrame()
    iframeRef.value = el

    frame.scrollToAnchor('#sec-2')
    expect(scrollIntoView).toHaveBeenCalled()

    frame.scrollToAnchor('450') // числовой якорь = позиция скролла
    expect(scrollTo).toHaveBeenCalledWith({ top: 450 })
  })
})

/* ========================= useInDocumentSearch ============================= */

describe('useInDocumentSearch — debounce и marks вместо XPath (Д5, Д10)', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  const setup = () => {
    const iframeRef = ref(null)
    const search = useInDocumentSearch({
      courseIdRef: ref(7),
      aircraftRef: ref(3),
      pathRef: ref('c'),
      iframeRef,
    })
    return { iframeRef, search }
  }

  it('не короче 3 символов не стреляет запросом', () => {
    const { search } = setup()
    search.query.value = 'аб'
    search.onQueryInput()
    vi.advanceTimersByTime(1000)

    expect(api.post).not.toHaveBeenCalled()
    expect(search.results.value).toEqual([])
  })

  it('debounce 300мс: три быстрых ввода = один запрос (Д10)', () => {
    api.post.mockResolvedValue({ data: [] })
    const { search } = setup()

    search.query.value = 'двигатель'
    search.onQueryInput()
    search.query.value = 'двигател'
    search.onQueryInput()
    search.query.value = 'двигатель'
    search.onQueryInput()

    vi.advanceTimersByTime(400)
    expect(api.post).toHaveBeenCalledTimes(1)
    expect(api.post).toHaveBeenCalledWith('/api/search-files/', {
      query: 'двигатель',
      aircraft: 3,
      path: 'c',
    })
  })

  it('collectMarks собирает серверные span.cp-hl и ведёт счётчик N/M (Д5)', () => {
    const { iframeRef, search } = setup()
    const el = document.createElement('iframe')
    const marks = [document.createElement('span'), document.createElement('span'), document.createElement('span')]
    marks.forEach((m) => m.classList.add('cp-hl'))
    Object.defineProperty(el, 'contentDocument', {
      value: {
        querySelectorAll: (sel) =>
          sel === 'span.cp-hl' ? marks : sel === 'span.cp-hl--active' ? [] : [],
      },
      configurable: true,
    })
    iframeRef.value = el

    search.collectMarks()
    expect(search.counterLabel.value).toBe('1/3')

    search.nextMark()
    expect(search.counterLabel.value).toBe('2/3')
    search.prevMark()
    search.prevMark() // wrap-around: 2 → 1 → 3
    expect(search.counterLabel.value).toBe('3/3')
  })
})

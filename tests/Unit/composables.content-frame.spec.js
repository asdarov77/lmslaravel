// @vitest-environment jsdom
/**
 * Юнит-тесты useContentFrame — управление iframe материала.
 *
 * Покрывают: сброс высоты при navigate (регрессия «прыжка кадра»),
 * clamp высоты [320..20000], режимы heightMode с персистом в localStorage,
 * scrollToAnchor (id / числовая позиция / кросс-доменное исключение),
 * состояние loading/failed и ResizeObserver-пересчёт (Д9).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref, nextTick } from 'vue'

// onBeforeUnmount вне компонента печатает предупреждение Vue — глушим хук.
vi.mock('vue', async (importOriginal) => {
  const actual = await importOriginal()
  return { ...actual, onBeforeUnmount: vi.fn() }
})

import { useContentFrame } from '../../resources/js/composables/useContentFrame'

/** Минимальная подделка iframe-элемента с contentDocument.
 *  getElementById возвращает ОДИН и тот же объект для одного id — иначе
 *  тест не сможет получить spy, который видил реальный код (стабильный
 *  mock-узел вместо нового объекта на каждый вызов). */
function fakeFrame(scrollHeight = 1500) {
  const body = { scrollHeight }
  const nodesById = {}
  const doc = {
    body,
    querySelectorAll: () => [],
    getElementById: (id) => {
      if (id !== 'known') return null
      if (!nodesById[id]) nodesById[id] = { scrollIntoView: vi.fn() }
      return nodesById[id]
    },
  }
  const win = { scrollTo: vi.fn() }
  const el = { src: '', contentDocument: doc, contentWindow: win }
  return { el, body, doc, win }
}

beforeEach(() => {
  localStorage.clear()
  // jsdom не даёт ResizeObserver — код ветку отключает сам; проверим это.
  delete window.ResizeObserver
})

it('initial state: idle frame at MIN_HEIGHT', () => {
  const iframeRef = ref(null)
  const cf = useContentFrame(iframeRef)

  expect(cf.frameHeight.value).toBe(320)
  expect(cf.loading.value).toBe(false)
  expect(cf.failed.value).toBe(false)
})

it('navigate sets loading, resets height and assigns src', () => {
  const { el } = fakeFrame(9000)
  const iframeRef = ref(el)
  const cf = useContentFrame(iframeRef)

  // Раздуваем высоту вручную, имитируя длинный предыдущий документ.
  cf.onLoad()
  expect(cf.frameHeight.value).toBe(9024) // 9000 + 24

  cf.navigate('/api/course/7/content/2')

  expect(cf.loading.value).toBe(true)
  // КЛЮЧЕВАЯ регрессия Д9-UX: высота сброшена до MIN, а не осталась 9024.
  expect(cf.frameHeight.value).toBe(320)
  expect(el.src).toBe('/api/course/7/content/2')
})

it('onLoad clears loading/failed and marks docReady', () => {
  const { el } = fakeFrame(800)
  const iframeRef = ref(el)
  const cf = useContentFrame(iframeRef)

  cf.navigate('x')
  expect(cf.loading.value).toBe(true)

  cf.onLoad()
  expect(cf.loading.value).toBe(false)
  expect(cf.docReady.value).toBe(true)
  expect(cf.frameHeight.value).toBe(824)
})

it('clamps pathological document heights to MAX_HEIGHT', () => {
  const { el, body } = fakeFrame(50_000)
  const iframeRef = ref(el)
  const cf = useContentFrame(iframeRef)

  cf.onLoad()
  expect(cf.frameHeight.value).toBe(20000)

  body.scrollHeight = 10 // крошечный документ — нижняя граница
  cf.recalcHeight(el.contentDocument)
  expect(cf.frameHeight.value).toBe(320)
})

it('cross-origin frame (no contentDocument access) degrades without crash', () => {
  const iframeRef = ref({ src: '', get contentDocument() { throw new Error('cross-origin') } })
  const cf = useContentFrame(iframeRef)

  cf.navigate('https://other.example/doc')
  expect(() => cf.onLoad()).not.toThrow()
  expect(cf.docReady.value).toBe(false)
  expect(cf.frameHeight.value).toBe(320)
})

/* ─────────────────────────── режимы высоты ─────────────────────────── */

it('heightMode defaults to fit and persists setHeightMode to localStorage', () => {
  const iframeRef = ref(null)
  const cf = useContentFrame(iframeRef)

  expect(cf.heightMode.value).toBe('fit')
  cf.setHeightMode('fixed')
  expect(localStorage.getItem('cp.heightMode')).toBe('fixed')

  // Новый инстанс читает сохранённый режим.
  const cf2 = useContentFrame(ref(null))
  expect(cf2.heightMode.value).toBe('fixed')
})

it('fixed mode clamps slider value into [320..2000] and persists it', () => {
  const cf = useContentFrame(ref(null))

  cf.setFixedHeight(50) // ниже минимума
  expect(cf.fixedHeight.value).toBe(320)

  cf.setFixedHeight(9999) // выше максимума
  expect(cf.fixedHeight.value).toBe(2000)

  cf.setFixedHeight('abc') // мусор → минимум
  expect(cf.fixedHeight.value).toBe(320)

  cf.setHeightMode('fixed')
  cf.setFixedHeight(700)
  expect(cf.effectiveHeight()).toBe(700)
  expect(localStorage.getItem('cp.fixedHeight')).toBe('700')
})

it('window mode derives height from viewport minus chrome', () => {
  const cf = useContentFrame(ref(null))
  Object.defineProperty(window, 'innerHeight', { value: 1000, configurable: true, writable: true })

  cf.setHeightMode('window')
  expect(cf.effectiveHeight()).toBe(1000 - 260)

  // Очень низкое окно — нижняя граница MIN_HEIGHT.
  Object.defineProperty(window, 'innerHeight', { value: 200, configurable: true, writable: true })
  expect(cf.effectiveHeight()).toBe(320)
})

/* ─────────────────────────── scrollToAnchor ─────────────────────────── */

it('scrollToAnchor finds element by id inside frame document', () => {
  const { el } = fakeFrame()
  const iframeRef = ref(el)
  const cf = useContentFrame(iframeRef)

  cf.scrollToAnchor('#known')
  // getElementById вернул mock-узел с spy scrollIntoView:
  const targetSpy = el.contentDocument.getElementById('known')
  expect(targetSpy.scrollIntoView).toHaveBeenCalledWith({ block: 'start' })
})

it('scrollToAnchor treats numeric anchor as pixel offset via contentWindow', () => {
  const { el, win } = fakeFrame()
  const iframeRef = ref(el)
  const cf = useContentFrame(iframeRef)

  cf.scrollToAnchor('1200')
  expect(win.scrollTo).toHaveBeenCalledWith({ top: 1200 })
})

it('scrollToAnchor is a silent no-op for empty anchor or missing frame', () => {
  const cf = useContentFrame(ref(null))
  expect(() => cf.scrollToAnchor(null)).not.toThrow()
  expect(() => cf.scrollToAnchor('missing-id')).not.toThrow()
})

it('scrollToAnchor survives cross-origin SecurityError without crashing player', () => {
  const iframeRef = ref({
    src: '',
    contentDocument: null,
    get contentWindow() { return null },
  })
  const cf = useContentFrame(iframeRef)
  // Нет документа — ранний выход, без исключения.
  expect(() => cf.scrollToAnchor('#any')).not.toThrow()
})

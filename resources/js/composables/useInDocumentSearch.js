/**
 * useInDocumentSearch — поиск по курсу и подсветка в кадре (Д5).
 *
 * Новая модель: подсветку ставит СЕРВЕР (CourseContentController::highlightInHtml)
 * в тот же HTML, который отдаётся в iframe. Фронт только:
 *  - собирает выдачу (с серверным snippet вместо XPath-мутаций),
 *  - находит span.cp-hl внутри документа кадра и переключает «активное»
 *    вхождение кнопками ▲▼ со счётчиком N/M.
 *
 * Раньше фронт применял XPath к srcdoc после sanitize — структура расходилась,
 * подсветка молча пропадала, счётчик врал. Теперь источник один — сервер.
 */
import { computed, ref } from 'vue'
import { searchFiles } from '../api/player.api'

const MIN_QUERY = 3
const DEBOUNCE_MS = 300

export function useInDocumentSearch({ courseIdRef, aircraftRef, pathRef, iframeRef }) {
  const query = ref('')
  const results = ref([])
  const searching = ref(false)
  const searchedOnce = ref(false)
  const error = ref(null)

  // позиции подсветки внутри текущего кадра
  const marks = ref([])
  const markIndex = ref(-1)
  const hasMarks = computed(() => marks.value.length > 0)
  const counterLabel = computed(() =>
    marks.value.length ? `${markIndex.value + 1}/${marks.value.length}` : ''
  )

  let debounceTimer = null

  async function runSearch() {
    const q = (query.value || '').trim()
    if (q.length < MIN_QUERY) {
      results.value = []
      searchedOnce.value = false
      return
    }
    searching.value = true
    error.value = null
    try {
      const { data } = await searchFiles({
        query: q,
        aircraft: aircraftRef.value,
        path: pathRef.value,
      })
      results.value = Array.isArray(data) ? data : []
    } catch (e) {
      error.value = 'Поиск недоступен'
      results.value = []
    } finally {
      searching.value = false
      searchedOnce.value = true
    }
  }

  /** Ввод с debounce: не долбим бэкенд на каждый символ (Д10). */
  function onQueryInput() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(runSearch, DEBOUNCE_MS)
  }

  function clearSearch() {
    clearTimeout(debounceTimer)
    query.value = ''
    results.value = []
    searchedOnce.value = false
    resetMarks()
  }

  // ---------- работа с подсветкой внутри кадра ----------

  function frameDoc() {
    const el = iframeRef.value
    try {
      return el && el.contentDocument ? el.contentDocument : null
    } catch (e) {
      return null // кросс-домен — недоступно
    }
  }

  function resetMarks() {
    marks.value = []
    markIndex.value = -1
  }

  /** Вызывать после загрузки кадра с ?highlight=term. */
  function collectMarks() {
    const doc = frameDoc()
    if (!doc) {
      resetMarks()
      return
    }
    marks.value = Array.from(doc.querySelectorAll('span.cp-hl'))
    markIndex.value = marks.value.length ? 0 : -1
    focusMark()
  }

  function focusMark() {
    const doc = frameDoc()
    if (!doc) return
    doc.querySelectorAll('span.cp-hl--active').forEach((n) => n.classList.remove('cp-hl--active'))
    const node = marks.value[markIndex.value]
    if (!node) return
    node.classList.add('cp-hl--active')
    // jsdom не реализует scrollIntoView — в реальном браузере метод есть,
    // поэтому вызов защищён feature-check вместо падения юнит-теста.
    if (typeof node.scrollIntoView === 'function') {
      node.scrollIntoView({ block: 'center', behavior: 'smooth' })
    }
  }

  function nextMark() {
    if (!marks.value.length) return collectMarks()
    markIndex.value = (markIndex.value + 1) % marks.value.length
    focusMark()
  }

  function prevMark() {
    if (!marks.value.length) return collectMarks()
    markIndex.value = (markIndex.value - 1 + marks.value.length) % marks.value.length
    focusMark()
  }

  return {
    query,
    results,
    searching,
    searchedOnce,
    error,
    runSearch,
    onQueryInput,
    clearSearch,
    // marks API
    marks,
    markIndex,
    hasMarks,
    counterLabel,
    collectMarks,
    resetMarks,
    nextMark,
    prevMark,
    MIN_QUERY,
  }
}

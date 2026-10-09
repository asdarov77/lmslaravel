import { onBeforeUnmount, ref } from 'vue'

/**
 * useRemoteOptions — асинхронный поиск опций для autocomplete.
 *
 * Зачем: remote-search (пользователи, курсы) вызывался на каждый
 * keystroke без debounce и без защиты от гонки. При быстром вводе
 * ответ на старую букву мог прийти позже нового и переписать список
 * нерелевантными вариантами; индикатор загрузки и «ничего не найдено»
 * вообще не показывались.
 *
 * Здесь: debounce, отмена устаревших ответов по счётчику запросов и
 * состояния loading/error. `fetcher` — async (query) => array.
 */
export function useRemoteOptions({
  fetcher,
  minChars = 0,
  debounce = 300,
  initial = [],
  immediate = false,
} = {}) {
  const options = ref(Array.isArray(initial) ? initial : [])
  const loading = ref(false)
  const error = ref(null)

  let timer = null
  let seq = 0

  const load = async (query) => {
    const text = String(query ?? '')
    if (text.length < minChars) {
      seq += 1
      options.value = Array.isArray(initial) ? initial : []
      loading.value = false
      error.value = null

      return
    }

    const current = ++seq
    loading.value = true
    error.value = null

    try {
      const result = await fetcher(text, current)
      // Устаревший ответ (пришёл после более нового запроса) — молча
      // отбрасываем: иначе список «прыгал» на предыдущее состояние.
      if (current !== seq) return

      options.value = Array.isArray(result) ? result : []
    } catch (err) {
      if (current !== seq) return
      error.value = err
      options.value = []
    } finally {
      if (current === seq) loading.value = false
    }
  }

  const search = (query) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => load(query), debounce)
  }

  const cancel = () => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    seq += 1
  }

  if (immediate) load('')

  onBeforeUnmount(cancel)

  return { options, loading, error, load, search, cancel }
}

export default useRemoteOptions

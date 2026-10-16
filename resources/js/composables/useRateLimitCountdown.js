import { computed, onBeforeUnmount, ref } from 'vue'

/**
 * Обратный отсчёт до разрешения повторной попытки (HTTP 429).
 *
 * ЗАЧЕМ. Ответ «Слишком много запросов» без времени ожидания бесполезен:
 * пользователь не знает, когда можно повторить, и либо жмёт снова
 * (и снова получает 429), уходит пить чай наугад. Число секунд на
 * экране превращает паузу в понятное ожидание.
 *
 * ГДЕ ВЗЯТЬ ВРЕМЯ. Laravel (ThrottleRequests) на 429 отдаёт Retry-After
 * в секундах и X-RateLimit-Reset с абсолютным временем. Если заголовков
 * нет — берём запасной интервал: сообщение должно показывать что-то
 * осмысленное даже когда сервер молчит.
 */

const FALLBACK_SECONDS = 60

/**
 * Сколько секунд ждать до повторной попытки.
 *
 * @param {object} error Ошибка axios.
 * @param {number} fallback Запасной интервал, если сервер не сказал.
 * @returns {number} Секунды; 0 — ограничения нет.
 */
export const retryAfterSeconds = (error, fallback = FALLBACK_SECONDS) => {
  const headers = error?.response?.headers

  if (!headers) return fallback

  const read = (name) => {
    if (typeof headers.get === 'function') return headers.get(name)
    return headers[name] ?? headers[name.toLowerCase()] ?? null
  }

  const retryAfter = Number(read('Retry-After'))
  if (Number.isFinite(retryAfter) && retryAfter > 0) return Math.ceil(retryAfter)

  // X-RateLimit-Reset приходит как unix-время. Единицы в разных версиях
  // Laravel различаются (секунды или миллисекунды), поэтому порог
  // выбран так: 1e11 секунд — это 5138 год, а 1e11 миллисекунд — 1973-й.
  const reset = Number(read('X-RateLimit-Reset'))
  if (Number.isFinite(reset) && reset > 0) {
    const resetAtMs = reset > 1e11 ? reset : reset * 1000
    const seconds = Math.ceil((resetAtMs - Date.now()) / 1000)

    // Часы на сервере могут спешить: отрицательное значение показывать
    // нельзя, иначе вместо ожидания выйдет «0 с».
    if (seconds > 0) return seconds
  }

  return fallback
}

/**
 * Обратный отсчёт, тикающий раз в секунду.
 *
 * @param {number} initialSeconds Начальное ожидание (0 — не тикает).
 * @returns {{ seconds: import('vue').Ref<number>, active: import('vue').ComputedRef<boolean>, start: (seconds: number) => void, stop: () => void }}
 */
export const useRateLimitCountdown = (fallback = FALLBACK_SECONDS) => {
  const seconds = ref(0)
  let timer = null

  const stop = () => {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  const start = (value) => {
    const total = Number(value) > 0 ? Math.ceil(Number(value)) : retryAfterSeconds(null, fallback)

    stop()
    seconds.value = total

    if (total <= 0) return

    timer = setInterval(() => {
      seconds.value -= 1
      if (seconds.value <= 0) stop()
    }, 1000)
  }

  onBeforeUnmount(stop)

  return {
    seconds,
    active: computed(() => seconds.value > 0),
    start,
    stop,
  }
}

export default useRateLimitCountdown

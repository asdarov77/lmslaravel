// @vitest-environment jsdom
/**
 * Обратный отсчёт при 429 (Фаза 3.1).
 *
 * Ключевое: время ожидания берётся из заголовков ответа, а не
 * выдумывается. Если сервер молчит — показывается запасной интервал,
 * но НИКОГДА не «0 с»: это выглядело бы как «можно повторить сейчас»
 * и человек тут же жал бы снова.
 */
import { describe, it, expect, vi, afterEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import {
  retryAfterSeconds,
  useRateLimitCountdown,
} from '../../resources/js/composables/useRateLimitCountdown'

const headers = (map) => ({ get: (name) => map[name] ?? null })

describe('retryAfterSeconds: источник времени ожидания', () => {
  it('берёт Retry-After в секундах', () => {
    const error = { response: { headers: headers({ 'Retry-After': '42' }) } }

    expect(retryAfterSeconds(error)).toBe(42)
  })

  it('берёт абсолютное время из X-RateLimit-Reset в секундах', () => {
    const resetAt = Math.floor(Date.now() / 1000) + 90
    const error = { response: { headers: headers({ 'X-RateLimit-Reset': String(resetAt) }) } }

    const seconds = retryAfterSeconds(error)

    expect(seconds).toBeGreaterThan(80)
    expect(seconds).toBeLessThanOrEqual(91)
  })

  it('понимает X-RateLimit-Reset в миллисекундах', () => {
    // В этой версии Laravel заголовок приходит в мс. Если бы единицы
    // не определялись, ожидание вышло бы на 50 порядков больше.
    const resetAt = Date.now() + 90_000
    const error = { response: { headers: headers({ 'X-RateLimit-Reset': String(resetAt) }) } }

    const seconds = retryAfterSeconds(error)

    expect(seconds).toBeGreaterThan(80)
    expect(seconds).toBeLessThanOrEqual(91)
  })

  it('не показывает «0 с», когда часы сервера спешат', () => {
    // Разница часов даёт отрицательное время до сброса. Показывать
    // «0 с» нельзя: человек решит, что можно повторить немедленно.
    const resetAt = Math.floor(Date.now() / 1000) - 600
    const error = { response: { headers: headers({ 'X-RateLimit-Reset': String(resetAt) }) } }

    expect(retryAfterSeconds(error, 30)).toBe(30)
  })

  it('без заголовков берёт запасной интервал', () => {
    expect(retryAfterSeconds({ response: {} }, 30)).toBe(30)
    expect(retryAfterSeconds(null, 45)).toBe(45)
  })

  it('понимает обычный объект заголовков (axios v1)', () => {
    const error = { response: { headers: { 'retry-after': '15' } } }

    expect(retryAfterSeconds(error)).toBe(15)
  })
})

describe('useRateLimitCountdown: тиканье', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  const harness = () => {
    let api = null
    const wrapper = mount(
      defineComponent({
        setup() {
          api = useRateLimitCountdown()

          return () => h('div')
        },
      })
    )

    return { api: () => api, wrapper }
  }

  it('не активен до 429', () => {
    const { api, wrapper } = harness()

    expect(api().active.value).toBe(false)
    expect(api().seconds.value).toBe(0)

    wrapper.unmount()
  })

  it('тикает раз в секунду и сам останавливается', () => {
    vi.useFakeTimers()
    const { api, wrapper } = harness()

    api().start(3)
    expect(api().seconds.value).toBe(3)

    vi.advanceTimersByTime(1000)
    expect(api().seconds.value).toBe(2)

    vi.advanceTimersByTime(2000)
    expect(api().seconds.value).toBe(0)
    expect(api().active.value).toBe(false)

    // Таймер остановлен: дальше счётчик не уходит в минус.
    vi.advanceTimersByTime(3000)
    expect(api().seconds.value).toBe(0)

    wrapper.unmount()
  })

  it('повторный запуск перезапускает отсчёт, а не добавляет таймер', () => {
    vi.useFakeTimers()
    const { api, wrapper } = harness()

    api().start(5)
    vi.advanceTimersByTime(1000)
    api().start(10)

    vi.advanceTimersByTime(1000)
    expect(api().seconds.value).toBe(9)

    wrapper.unmount()
  })

  it('останавливается при размонтировании компонента', () => {
    vi.useFakeTimers()
    const { api, wrapper } = harness()

    api().start(30)
    wrapper.unmount()

    // После размонтирования интервал не должен тикать: иначе он живёт
    // до перезагрузки страницы.
    expect(() => vi.advanceTimersByTime(5000)).not.toThrow()
    expect(vi.getTimerCount()).toBe(0)
  })
})

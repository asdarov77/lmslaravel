// @vitest-environment jsdom
/**
 * LRU-кэш документов плеера (P0.3).
 */
import { describe, it, expect } from 'vitest'
import { createDocCache } from '../../resources/js/utils/docCache'

describe('docCache', () => {
  it('отдаёт записанное и поднимает свежий доступ в конец очереди', () => {
    const cache = createDocCache()
    cache.put(1, 10, '<p>первый</p>')
    cache.put(1, 20, '<p>второй</p>')

    expect(cache.get(1, 10)).toBe('<p>первый</p>')
    expect(cache.get(1, 999)).toBeNull()
  })

  it('выбрасывает самое старое при переполнении (15 документов)', () => {
    const cache = createDocCache()
    for (let i = 1; i <= 16; i++) cache.put(1, i, `<p>${i}</p>`)

    expect(cache.get(1, 1)).toBeNull()
    expect(cache.get(1, 16)).toBe('<p>16</p>')
  })

  it('LRU-доступ спасает документ от выброса', () => {
    const cache = createDocCache()
    for (let i = 1; i <= 15; i++) cache.put(1, i, `<p>${i}</p>`)
    // перечитали единичку late: она уходит в конец очереди
    expect(cache.get(1, 1)).toBe('<p>1</p>')
    cache.put(1, 16, '<p>16</p>')

    // выбросился 2-й, а не «спасённый» первый
    expect(cache.get(1, 2)).toBeNull()
    expect(cache.get(1, 1)).toBe('<p>1</p>')
  })

  it('кэш разделён по курсам и не принимает пустое', () => {
    const cache = createDocCache()
    cache.put(1, 1, '<p>а</p>')
    cache.put(2, 1, '<p>б</p>')
    expect(cache.get(1, 1)).toBe('<p>а</p>')
    expect(cache.get(2, 1)).toBe('<p>б</p>')

    cache.put(1, 5, '')
    expect(cache.get(1, 5)).toBeNull()
  })
})

// @vitest-environment jsdom
/**
 * Утилиты массивов (repeatable-поля) и асинхронный поиск опций.
 */
import { describe, it, expect, vi } from 'vitest'
import { moveItem, removeAt, insertAt, cloneItem } from '../../resources/js/utils/array'
import useRemoteOptions from '../../resources/js/composables/useRemoteOptions'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'

describe('array: moveItem', () => {
  it('переставляет и не мутирует исходный', () => {
    const source = ['a', 'b', 'c']
    const result = moveItem(source, 0, 2)
    expect(result).toEqual(['b', 'c', 'a'])
    expect(source).toEqual(['a', 'b', 'c'])
  })

  it('некорректные индексы возвращают копию', () => {
    const source = ['a', 'b']
    expect(moveItem(source, 0, 5)).toEqual(['a', 'b'])
    expect(moveItem(source, -1, 1)).toEqual(['a', 'b'])
    expect(moveItem(source, 1, 1)).toEqual(['a', 'b'])
  })
})

describe('array: removeAt / insertAt / cloneItem', () => {
  it('removeAt', () => {
    expect(removeAt(['a', 'b', 'c'], 1)).toEqual(['a', 'c'])
  })

  it('insertAt с ограничением границ', () => {
    expect(insertAt(['a', 'b'], 1, 'x')).toEqual(['a', 'x', 'b'])
    expect(insertAt(['a', 'b'], 99, 'x')).toEqual(['a', 'b', 'x'])
  })

  it('cloneItem делает независимую копию', () => {
    const item = { answers: [{ text: 'a' }] }
    const copy = cloneItem(item)
    copy.answers[0].text = 'b'
    expect(item.answers[0].text).toBe('a')
  })
})

const harness = (options) => {
  let api = null
  const Comp = defineComponent({
    setup() {
      api = useRemoteOptions(options)

      return () => h('div')
    },
  })
  const wrapper = mount(Comp)

  return { wrapper, api: () => api }
}

describe('useRemoteOptions', () => {
  it('дебаунсит поиск и отдаёт результат', async () => {
    vi.useFakeTimers()
    const fetcher = vi.fn(async (q) => [`${q}-1`, `${q}-2`])
    const { api } = harness({ fetcher, debounce: 300 })

    api().search('ив')
    api().search('ива')
    expect(fetcher).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(300)
    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(fetcher).toHaveBeenCalledWith('ива', expect.any(Number))
    expect(api().options.value).toEqual(['ива-1', 'ива-2'])

    vi.useRealTimers()
  })

  it('уважает minChars и не грузит короткий запрос', async () => {
    vi.useFakeTimers()
    const fetcher = vi.fn(async () => ['x'])
    const { api } = harness({ fetcher, minChars: 2, debounce: 100 })

    api().search('и')
    await vi.advanceTimersByTimeAsync(150)
    expect(fetcher).not.toHaveBeenCalled()
    expect(api().options.value).toEqual([])

    vi.useRealTimers()
  })

  it('отбрасывает устаревший ответ (гонка)', async () => {
    const resolvers = {}
    const fetcher = vi.fn((q) => new Promise((resolve) => { resolvers[q] = resolve }))
    const { api } = harness({ fetcher, debounce: 0 })

    const slow = api().load('old')
    const fast = api().load('new')
    resolvers.new(['new-result'])
    await fast
    resolvers.old(['old-result'])
    await slow

    expect(api().options.value).toEqual(['new-result'])
  })

  it('ошибка поиска не роняет список', async () => {
    vi.useFakeTimers()
    const fetcher = vi.fn(async () => {
      throw new Error('boom')
    })
    const { api } = harness({ fetcher, debounce: 50 })

    api().search('x')
    await vi.advanceTimersByTimeAsync(60)

    expect(api().error.value).toBeInstanceOf(Error)
    expect(api().options.value).toEqual([])
    expect(api().loading.value).toBe(false)

    vi.useRealTimers()
  })
})

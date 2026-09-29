import { describe, it, expect } from 'vitest'
import {
  unwrapResponse,
  unwrapArray,
  unwrapField,
  asArray,
} from '../../resources/js/api/envelope'

// Ровно та форма, которую отдаёт axios + middleware ApiResponseEnvelope
const envelope = (data, extra = {}) => ({
  data: { success: true, data, error: null, meta: null, ...extra },
})

const errorEnvelope = (data) => ({
  data: { success: false, data, error: { code: '500', message: 'Error' } },
})

describe('envelope: unwrapResponse', () => {
  it('разворачивает конверт ApiResponseEnvelope', () => {
    expect(unwrapResponse(envelope([{ id: 1 }]))).toEqual([{ id: 1 }])
    expect(unwrapResponse(envelope({ id: 7 }))).toEqual({ id: 7 })
  })

  it('разворачивает конверт с ошибкой, отдавая data как есть', () => {
    expect(unwrapResponse(errorEnvelope({ message: 'boom' }))).toEqual({ message: 'boom' })
  })

  it('не ломается на пустой/нулевой полезной нагрузке', () => {
    // Регресс: `response.data.data || response.data` возвращал сам конверт,
    // когда data === null/[] — и в state попадал объект вместо данных.
    expect(unwrapResponse(envelope(null))).toBeNull()
    expect(unwrapResponse(envelope([]))).toEqual([])
    expect(unwrapResponse(envelope(0))).toBe(0)
    expect(unwrapResponse(envelope(false))).toBe(false)
    expect(unwrapResponse(envelope(''))).toBe('')
  })

  it('поддерживает «голый» ответ без конверта (обратная совместимость)', () => {
    expect(unwrapResponse({ data: [{ id: 1 }] })).toEqual([{ id: 1 }])
    expect(unwrapResponse({ data: { id: 3 } })).toEqual({ id: 3 })
    expect(unwrapResponse({ data: '<html></html>' })).toBe('<html></html>')
  })

  it('не падает на null/undefined и на «голом» значении', () => {
    expect(unwrapResponse(null)).toBeNull()
    expect(unwrapResponse(undefined)).toBeNull()
    expect(unwrapResponse({ data: null })).toBeNull()
  })
})

describe('envelope: asArray', () => {
  it('возвращает массив как есть', () => {
    const arr = [{ id: 1 }]
    expect(asArray(arr)).toBe(arr)
  })

  it('превращает конверт и мусор в пустой массив (защита .sort/.map в шаблонах)', () => {
    // Именно этот случай ронял Categories/Groups: categories.sort is not a function
    expect(asArray({ success: true, data: [], error: null, meta: null })).toEqual([])
    expect(asArray(null)).toEqual([])
    expect(asArray(undefined)).toEqual([])
    expect(asArray(0)).toEqual([])
    expect(asArray({ id: 1 })).toEqual([])
  })

  it('гарантирует, что результат всегда можно сортировать и итерировать', () => {
    // asArray не разворачивает конверт — он его отбрасывает,
    // поэтому .sort/.map никогда не упадут с "is not a function"
    const value = asArray({ success: true, data: [{ id: 2 }, { id: 1 }], error: null })
    expect(() => value.sort((a, b) => a.id - b.id)).not.toThrow()
    expect(value).toEqual([])

    // а unwrapArray разворачивает и сортирует данные как надо
    const unwrapped = unwrapArray(envelope([{ id: 2 }, { id: 1 }]))
    expect(unwrapped.sort((a, b) => a.id - b.id)).toEqual([{ id: 1 }, { id: 2 }])
  })
})

describe('envelope: unwrapArray', () => {
  it('разворачивает конверт и сразу гарантирует массив', () => {
    expect(unwrapArray(envelope([{ id: 1 }]))).toEqual([{ id: 1 }])
  })

  it('не возвращает конверт, даже когда полезной нагрузки нет', () => {
    expect(unwrapArray(envelope(null))).toEqual([])
    expect(unwrapArray(errorEnvelope({ message: 'err' }))).toEqual([])
  })
})

describe('envelope: unwrapField', () => {
  it('достаёт поле из полезной нагрузки', () => {
    expect(unwrapField(envelope({ favorites: [1, 2] }), 'favorites')).toEqual([1, 2])
    expect(unwrapField(envelope({ auks: [3] }), 'auks')).toEqual([3])
  })

  it('возвращает undefined вместо падения, если поля или payload нет', () => {
    expect(unwrapField(envelope({}), 'nope')).toBeUndefined()
    expect(unwrapField(envelope(null), 'favorites')).toBeUndefined()
    expect(unwrapField(envelope([1, 2]), 'favorites')).toBeUndefined()
  })
})

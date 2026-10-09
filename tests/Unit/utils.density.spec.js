// @vitest-environment jsdom
/**
 * Плотность интерфейса.
 *
 * Переключатель в шапке долгое время менял только флаг в Vuex, и
 * плотность не применялась: кнопка «не работала». Тест фиксирует
 * контракт: выбор сохраняется, на <html> появляется data-density, и
 * повторное переключение возвращает обычный режим.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import density from '../../resources/js/utils/density'

describe('utils/density', () => {
  beforeEach(() => {
    window.localStorage.clear()
    document.documentElement.removeAttribute('data-density')
  })

  it('по умолчанию — default', () => {
    expect(density.read()).toBe('default')
  })

  it('toggle переключает default -> compact и обратно', () => {
    expect(density.toggle()).toBe('compact')
    expect(density.read()).toBe('compact')
    expect(document.documentElement.getAttribute('data-density')).toBe('compact')

    expect(density.toggle()).toBe('default')
    expect(document.documentElement.getAttribute('data-density')).toBe('default')
  })

  it('init восстанавливает сохранённый выбор', () => {
    window.localStorage.setItem('ui-density', 'compact')
    density.init()
    expect(document.documentElement.getAttribute('data-density')).toBe('compact')
  })

  it('некорректное сохранённое значение трактуется как default', () => {
    window.localStorage.setItem('ui-density', 'huge')
    expect(density.read()).toBe('default')
  })
})

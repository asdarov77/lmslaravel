import { describe, it, expect, beforeEach, vi } from 'vitest'

// Стор мокается ДО импорта директивы: она тянет singleton ./Store.
const hasPermission = vi.fn()
const can = vi.fn()

vi.mock('../../resources/js/Store', () => ({
  default: { getters: { 'Auth/can': (...a) => can(...a), 'Auth/hasPermission': (...a) => hasPermission(...a) } },
}))

const { canDirective } = await import('../../resources/js/plugins/can.directive')

/** Минимальный DOM-элемент, достаточный для директивы. */
const makeEl = () => {
  const el = document.createElement('button')
  document.body.appendChild(el)
  return el
}

const isHidden = el => el.getAttribute('data-v-can-hidden') === 'true'

describe('директива v-can', () => {
  beforeEach(() => {
    can.mockReset()
    document.body.innerHTML = ''
  })

  it('скрывает элемент, когда права нет', () => {
    can.mockReturnValue(false)
    const el = makeEl()

    canDirective.mounted(el, { value: 'users.view', modifiers: {} })

    expect(isHidden(el)).toBe(true)
    expect(el.style.display).toBe('none')
  })

  it('показывает элемент, когда право есть', () => {
    can.mockReturnValue(true)
    const el = makeEl()

    canDirective.mounted(el, { value: 'users.view', modifiers: {} })

    expect(isHidden(el)).toBe(false)
    expect(el.style.display).toBe('')
  })

  // Регресс: раньше директива УДАЛЯЛА элемент из DOM в mounted. Права приходят
  // асинхронно (GET /api/v1/me), поэтому на первой отрисовке элемент исчезал
  // навсегда и админ-панель выглядела пустой до перезагрузки страницы.
  it('регрессия: элемент не удаляется из DOM, а возвращается после прихода прав', () => {
    can.mockReturnValue(false)
    const el = makeEl()

    canDirective.mounted(el, { value: 'users.view', modifiers: {} })
    expect(el.isConnected, 'элемент обязан остаться в DOM').toBe(true)

    // Права подгрузились — директива пересчитывается в updated.
    can.mockReturnValue(true)
    canDirective.updated(el, { value: 'users.view', modifiers: {} })

    expect(el.isConnected).toBe(true)
    expect(isHidden(el)).toBe(false)
    expect(el.style.display).toBe('')
  })

  it('OR-семантика по умолчанию: при false по первому праву проверяется второе', () => {
    const el = makeEl()
    can.mockImplementation(p => p === 'manage-users')

    canDirective.mounted(el, { value: ['users.view', 'manage-users'], modifiers: {} })

    expect(can).toHaveBeenCalledWith('users.view')
    expect(can).toHaveBeenCalledWith('manage-users')
    expect(isHidden(el)).toBe(false)
  })

  it('OR скрывает элемент, только если не подошло ни одно право', () => {
    const el = makeEl()
    can.mockReturnValue(false)

    canDirective.mounted(el, { value: ['users.view', 'manage-users'], modifiers: {} })

    expect(can).toHaveBeenCalledWith('users.view')
    expect(can).toHaveBeenCalledWith('manage-users')
    expect(isHidden(el)).toBe(true)
  })

  it('модификатор .all требует ВСЕ права (как CheckAllPermissions)', () => {
    can.mockReturnValue(true)
    const el = makeEl()

    canDirective.mounted(el, {
      value: ['courses.view', 'categories.manage'],
      modifiers: { all: true },
    })

    expect(can).toHaveBeenCalledTimes(2)
    expect(can).toHaveBeenCalledWith('courses.view')
    expect(can).toHaveBeenCalledWith('categories.manage')
  })

  it('без требований элемент виден всем (как пункт меню без contentType)', () => {
    const el = makeEl()

    canDirective.mounted(el, { value: [], modifiers: {} })

    expect(isHidden(el)).toBe(false)
    expect(can).not.toHaveBeenCalled()
  })

  it('unmount сбрасывает стиль, чтобы он не протекал на переиспользуемый DOM', () => {
    can.mockReturnValue(false)
    const el = makeEl()

    canDirective.mounted(el, { value: 'users.view', modifiers: {} })
    canDirective.unmounted(el)

    expect(isHidden(el)).toBe(false)
    expect(el.style.display).toBe('')
  })
})

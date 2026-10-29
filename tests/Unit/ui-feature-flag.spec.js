/**
 * REWORK R3: feature-flag ui.course_player_v2.
 *
 * Проверяет два инварианта:
 *  1) useUiFlags — безопасный дефолт «выключено» при отсутствии window/флага;
 *  2) routes.js — маршрут courses.play физически отсутствует, когда флаг
 *     выключен (в т.ч. по умолчанию в тестовой среде без __UI_FLAGS__), и
 *     появляется с правильным путём/правами, когда флаг включён до импорта
 *     модуля роутера (import-reset через vi.resetModules).
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

describe('useUiFlags (R3)', () => {
  beforeEach(() => {
    delete window.__UI_FLAGS__
  })

  it('missing window.__UI_FLAGS__ => every flag is off (safe legacy default)', async () => {
    const { getUiFlag, coursePlayerV2Enabled } = await import(
      '../../resources/js/composables/useUiFlags.js'
    )
    expect(coursePlayerV2Enabled()).toBe(false)
    expect(getUiFlag('course_player_v2')).toBe(false)
    expect(getUiFlag('whatever', true)).toBe(true) // fallback работает
  })

  it('non-object __UI_FLAGS__ is treated as no flags', async () => {
    window.__UI_FLAGS__ = null
    const { getUiFlag } = await import('../../resources/js/composables/useUiFlags.js')
    expect(getUiFlag('course_player_v2', false)).toBe(false)
  })

  it('truthy/falsy normalization of the flag value', async () => {
    window.__UI_FLAGS__ = { course_player_v2: 1 }
    const mod = await import('../../resources/js/composables/useUiFlags.js')
    expect(mod.coursePlayerV2Enabled()).toBe(true)

    window.__UI_FLAGS__ = { course_player_v2: '' }
    vi.resetModules()
    const mod2 = await import('../../resources/js/composables/useUiFlags.js')
    expect(mod2.coursePlayerV2Enabled()).toBe(false)
  })
})

describe('routes.js conditional registration of courses.play (R3)', () => {
  // routes.js вычисляет список маршрутов ОДИН РАЗ при импорте модуля, а
  // Vite-кэш резолвит «routes.js?x=1» в тот же инстанс, что уже импортирован
  // другими спеками. Поэтому состояние флага подменяем моком зависимого
  // useUiFlags — он гарантированно свежий после vi.resetModules().
  async function loadRoutesWithFlag(enabled) {
    vi.resetModules()
    vi.doMock('../../resources/js/composables/useUiFlags.js', () => ({
      getUiFlag: (name, fallback = false) =>
        name === 'course_player_v2' ? enabled : fallback,
      coursePlayerV2Enabled: () => enabled,
    }))
    const mod = await import('../../resources/js/Router/routes.js')
    return mod.default || mod.routes
  }

  afterEach(() => {
    delete window.__UI_FLAGS__
    vi.doUnmock('../../resources/js/composables/useUiFlags.js')
    vi.resetModules()
  })

  it('flag OFF (default, no __UI_FLAGS__) => no /courses/:id/play route at all', async () => {
    delete window.__UI_FLAGS__
    const routes = await loadRoutesWithFlag(false)
    const play = routes.find((r) => r.name === 'courses.play')
    expect(play).toBeUndefined()
    // legacy-путь обязан остаться (инвариант AGENTS §4 — аварийный откат)
    const legacy = routes.find((r) => r.name === 'courses.itemmani')
    expect(legacy).toBeTruthy()
    expect(legacy.path).toBe('/courses/itemmani')
  })

  it('flag ON => courses.play registered with path, RBAC meta and CoursePlayer component', async () => {
    const routes = await loadRoutesWithFlag(true)
    const play = routes.find((r) => r.name === 'courses.play')
    expect(play).toBeTruthy()
    expect(play.path).toBe('/courses/:id/play')
    expect(play.meta.permission).toEqual(['content.view', 'content.manage'])
    // Ленивая загрузка страницы v2
    expect(typeof play.component).toBe('function')
    const page = await play.component()
    expect(page.default?.name ?? page.name).toBe('CoursePlayer')
    // props прокидывает числовой id из параметра маршрута
    expect(play.props({ params: { id: '42' } })).toEqual({ id: 42 })
  })

  it('flag ON does not disturb the legacy route (both coexist during rollout)', async () => {
    const routes = await loadRoutesWithFlag(true)
    expect(routes.filter((r) => r.name === 'courses.itemmani').length).toBe(1)
    expect(routes.filter((r) => r.name === 'courses.play').length).toBe(1)
  })
})

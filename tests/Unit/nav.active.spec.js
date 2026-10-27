// @vitest-environment jsdom
/**
 * Активный пункт бокового меню.
 *
 * Регресс на две вещи:
 *  1) активный пункт не выделялся ничем, кроме синего текста;
 *  2) подсветка терялась при переходе ВГЛУБЬ раздела: адрес
 *     /user/edit/7 не начинается с /user/list, и пункт «Пользователи»
 *     гас ровно тогда, когда пользователь в раздел ушёл.
 */
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { navigationSections } from '../../resources/js/navigation'

const menuSource = readFileSync(
  'resources/js/Pages/Navigation/LeftSideMenu.vue',
  'utf8'
)

const item = (link) =>
  navigationSections.flatMap((s) => s.items).find((i) => i.link === link)

describe('Меню: активный пункт', () => {
  it('разделы с поддеревом помечены activeMatch', () => {
    expect(item('/user/list').activeMatch).toBe('/user')
    expect(item('/groups/list').activeMatch).toBe('/groups')
  })

  it('индикатор сделан тенью, а не рамкой', () => {
    // border-left у v-list-item перебивается стилями Vuetify: полоса не
    // появлялась, вычисленная ширина была 0px. Поэтому активный пункт
    // помечается тенью и цветом рамки, а не левой полосой.
    const activeRule = menuSource.slice(
      menuSource.indexOf('.u-nav__item--active {')
    )
    expect(activeRule).toMatch(/box-shadow:/)
    expect(activeRule).not.toMatch(/border-left:/)
    expect(menuSource).not.toMatch(/border-left:\s*\d/)
  })

  it('фон наведения и активного пункта — из токенов', () => {
    expect(menuSource).toMatch(/--c-primary-soft/)
    expect(menuSource).toMatch(/--c-primary/)
  })

  it('активность считается по маршруту, а не по точному адресу', () => {
    // Пункты меню рендерит сама LeftSideMenu (RenderNavItem удалён:
    // его scoped-разметка не ложилась на v-list-item, и пункты выпадали
    // из оформления — «артефакты»).
    expect(menuSource).toMatch(/:active="isActive\(item\)"/)
    expect(menuSource).toMatch(/:active="isActive\(child\)"/)

    // Активным считается и вложенный адрес: /user/edit/7 начинается с
    // activeMatch '/user', поэтому пункт не гаснет при уходе вглубь.
    expect(menuSource).toMatch(/current\.startsWith\(item\.activeMatch \+ '\/'\)/)
  })

  it('сворачивание сделано на кнопках секций, без v-list-group и RenderNavItem', () => {
    // Регресс: раньше у меню было два механизма сворачивания
    // (v-list-group и вынесенный RenderNavItem), из-за чего панель
    // «прыгала» сама и scoped-стили родителя не применялись к пунктам.
    // Теперь секции сворачиваются родными кнопками с состоянием в
    // localStorage (см. LeftSideMenu, NAV_COLLAPSED_KEY).
    expect(menuSource).not.toMatch(/<v-list-group/)
    expect(menuSource).not.toMatch(/import\s+.*RenderNavItem/)
    expect(menuSource).toMatch(/u-nav__section--toggle/)
    expect(menuSource).toMatch(/toggleSection/)
  })
})

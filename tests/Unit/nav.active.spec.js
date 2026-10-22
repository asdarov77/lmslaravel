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

  it('меню не сворачивается', () => {
    // Регресс: у меню было два механизма сворачивания (флаг разделов в
    // localStorage и флаг collapsed), из-за чего панель «прыгала» сама.
    // Теперь меню плоское и всегда развёрнутое.
    expect(menuSource).not.toMatch(/nav-collapsed/)
    expect(menuSource).not.toMatch(/nav-expanded-sections/)
    expect(menuSource).not.toMatch(/<v-list-group/)
  })
})

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
    // border у v-list-item перебивается стилями Vuetify: полоса не
    // появлялась, вычисленная ширина была 0px.
    expect(menuSource).toMatch(/\.u-nav__item\s*\{[^}]*box-shadow: inset/)
    expect(menuSource).not.toMatch(/\.u-nav__item\s*\{[^}]*border-left:/)
  })

  it('фон наведения и активного пункта — из токенов', () => {
    expect(menuSource).toMatch(/--c-primary-soft/)
    expect(menuSource).toMatch(/--c-primary/)
  })

  it('активность считается по маршруту, а не по точному адресу', () => {
    expect(menuSource).toMatch(/:active="isActive\(item\)"/)
    expect(menuSource).toMatch(/isActive\(item\)/)
  })
})

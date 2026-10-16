// @vitest-environment jsdom
/**
 * Закреплённые курсы (Фаза 3.7) — боковая навигация пользователя.
 *
 * Проверяем два недостающих куска: закрепление из каталога (кнопка на
 * карточке) и секцию «Закреплённые» в меню. Раньше в меню секции
 * сворачивались, но пункты превращались в безымянные иконки без
 * подписей, а закреплённых курсов не было вовсе.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { readFileSync } from 'node:fs'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createStore } from 'vuex'

import { pinnedCourses } from '../../resources/js/utils/pinnedCourses.js'
import ru from '../../resources/js/locales/ru.json'

// Компоненты меню и каталога читают Vuex (Auth.can, Ui, Course) — без
// стора монтирование падает на mapGetters/mapState.
const store = createStore({
  modules: {
    Auth: {
      namespaced: true,
      getters: {
        can: () => () => true,
        loggedIn: () => true,
        isTrainee: () => false,
      },
    },
    Ui: {
      namespaced: true,
      state: { language: 'ru', density: 'comfortable' },
    },
    Course: {
      namespaced: true,
      actions: {
        fetchCourses: async () => {},
        fetchCoursesFilter: async () => {},
        // Courses.vue догружает справочники в created; без них падает
        // с «unknown action type».
        fetchAircrafts: async () => {},
        fetchCategories: async () => {},
      },
    },
  },
})

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: { template: '<div />' } },
    { path: '/course', name: 'course.store', component: { template: '<div />' } },
    { path: '/course/:idEdit', name: 'course.update', component: { template: '<div />' } },
    { path: '/courses/wizard', name: 'course.wizard', component: { template: '<div />' } },
    { path: '/courses/wizard/:idEdit', name: 'course.wizard.edit', component: { template: '<div />' } },
    { path: '/courses/itemmani', name: 'courses.itemmani', component: { template: '<div />' } },
    { path: '/courses/list', name: 'courses.list', component: { template: '<div />' } },
    { path: '/courses/desc/:idEdit', name: 'courses.desc', component: { template: '<div />' } },
  ],
})

const COURSE = { id: 65, title: 'Конструкция самолёта' }

const mountPage = (component) =>
  mount(component, {
    global: { plugins: [vuetify, i18n, router, store] },
  })

describe('pinnedCourses: хранение', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('добавляет курс и читает его обратно', () => {
    pinnedCourses.add({ id: 65, title: 'Курс', link: { name: 'courses.itemmani', query: { idEdit: 65 } } })

    const list = pinnedCourses.list()
    expect(list).toHaveLength(1)
    expect(list[0]).toMatchObject({ id: 65, title: 'Курс' })
  })

  it('не дублирует один курс', () => {
    pinnedCourses.add({ id: 65, title: 'Курс' })
    pinnedCourses.add({ id: 65, title: 'Курс' })

    expect(pinnedCourses.list()).toHaveLength(1)
  })

  it('переставляет повторно закреплённый курс в начало', () => {
    pinnedCourses.add({ id: 1, title: 'Первый' })
    pinnedCourses.add({ id: 2, title: 'Второй' })
    pinnedCourses.add({ id: 1, title: 'Первый' })

    expect(pinnedCourses.list().map((c) => c.id)).toEqual([1, 2])
  })

  it('снимает закрепление', () => {
    pinnedCourses.add({ id: 65, title: 'Курс' })
    expect(pinnedCourses.isPinned(65)).toBe(true)

    pinnedCourses.remove(65)
    expect(pinnedCourses.isPinned(65)).toBe(false)
    expect(pinnedCourses.list()).toHaveLength(0)
  })

  it('toggle добавляет и снимает', () => {
    pinnedCourses.toggle({ id: 65, title: 'Курс' })
    expect(pinnedCourses.isPinned(65)).toBe(true)

    pinnedCourses.toggle({ id: 65, title: 'Курс' })
    expect(pinnedCourses.isPinned(65)).toBe(false)
  })

  it('ограничивает число закреплённых курсов', () => {
    for (let i = 1; i <= 12; i++) {
      pinnedCourses.add({ id: i, title: `Курс ${i}` })
    }

    // Меню не должно вытеснять навигацию: потолок задан осознанно.
    const list = pinnedCourses.list()
    expect(list.length).toBeLessThanOrEqual(8)
    // Уходят самые старые.
    expect(list.map((c) => c.id)).not.toContain(1)
    expect(list.map((c) => c.id)).toContain(12)
  })

  it('не роняет при битом JSON в хранилище', () => {
    localStorage.setItem('pinned-courses', '{битый json')
    expect(pinnedCourses.list()).toEqual([])
  })

  it('не роняет при не-массиве в хранилище', () => {
    localStorage.setItem('pinned-courses', '{"id":1}')
    expect(pinnedCourses.list()).toEqual([])
  })

  it('отбрасывает записи без названия', () => {
    localStorage.setItem('pinned-courses', JSON.stringify([{ id: 1, title: '' }, { id: 2, title: 'Ок' }]))
    expect(pinnedCourses.list().map((c) => c.id)).toEqual([2])
  })
})

describe('LeftSideMenu: секция закреплённых', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('не показывает секцию, когда закреплений нет', async () => {
    const LeftSideMenu = (await import('../../resources/js/Pages/Navigation/LeftSideMenu.vue')).default
    const wrapper = mountPage(LeftSideMenu)

    expect(wrapper.find('.u-nav__pinned-shell').exists()).toBe(false)
    wrapper.unmount()
  })

  it('показывает закреплённые курсы со ссылками', async () => {
    pinnedCourses.add({ id: 65, title: 'Конструкция самолёта', link: { name: 'courses.itemmani', query: { idEdit: 65 } } })

    const LeftSideMenu = (await import('../../resources/js/Pages/Navigation/LeftSideMenu.vue')).default
    const wrapper = mountPage(LeftSideMenu)

    const items = wrapper.findAll('[data-test="pinned-course"]')
    expect(items.length).toBe(1)
    expect(items[0].text()).toContain('Конструкция самолёта')

    wrapper.unmount()
  })

  it('перечитывает список по событию из каталога', async () => {
    const LeftSideMenu = (await import('../../resources/js/Pages/Navigation/LeftSideMenu.vue')).default
    const wrapper = mountPage(LeftSideMenu)

    expect(wrapper.find('.u-nav__pinned-shell').exists()).toBe(false)

    pinnedCourses.add({ id: 65, title: COURSE.title })
    window.dispatchEvent(new CustomEvent('pinned-courses-changed'))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.u-nav__pinned-shell').exists()).toBe(true)

    wrapper.unmount()
  })

  it('снимает закрепление кнопкой в меню', async () => {
    pinnedCourses.add({ id: 65, title: 'Конструкция самолёта' })

    const LeftSideMenu = (await import('../../resources/js/Pages/Navigation/LeftSideMenu.vue')).default
    const wrapper = mountPage(LeftSideMenu)

    await wrapper.find('.u-nav__unpin').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.u-nav__pinned-shell').exists()).toBe(false)
    expect(pinnedCourses.isPinned(65)).toBe(false)

    wrapper.unmount()
  })
})

describe('LeftSideMenu: меню не сворачивается', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('пункты видны подписями, без тултипов и без флага свёртки', async () => {
    const LeftSideMenu = (await import('../../resources/js/Pages/Navigation/LeftSideMenu.vue')).default
    const wrapper = mountPage(LeftSideMenu)

    // Пункты отрисовывает сама LeftSideMenu. Раньше их рендерила
    // отдельная компонента, где пункт в свёрнутом виде превращался в
    // безымянную иконку и подсказку показывал только тултип; теперь меню
    // всегда развёрнуто, подпись есть у каждого пункта.
    const items = wrapper.findAll('[data-test="nav-item"]')
    expect(items.length).toBeGreaterThan(0)

    for (const item of items) {
      expect(item.text().trim()).not.toEqual('')
    }

    expect(wrapper.findAllComponents({ name: 'v-tooltip' })).toHaveLength(0)
    expect(wrapper.vm.collapsed).toBeUndefined()

    wrapper.unmount()
  })

  it('в компоненте нет ни флагов свёртки, ни сворачиваемых групп', async () => {
    const source = readFileSync(
      'resources/js/Pages/Navigation/LeftSideMenu.vue',
      'utf8'
    )

    // Регресс: два механизма сворачивания (localStorage-флаги разделов и
    // collapsed) заставляли меню закрываться само, а scoped-стили
    // родителя не применялись к вынесенным пунктам — это и были артефакты.
    expect(source).not.toContain('nav-collapsed')
    expect(source).not.toContain('nav-expanded-sections')
    expect(source).not.toContain('<v-list-group')
    // Компонент удалён, меню не должно его импортировать. Именно импорт,
    // а не любое упоминание в комментарии: объяснение «почему разметка
    // плоская» в коде остаться должно.
    expect(source).not.toMatch(/import\s+.*RenderNavItem/)
  })
})

describe('Courses: кнопка закрепления', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('закрепляет курс по клику', async () => {
    const Courses = (await import('../../resources/js/Pages/Courses.vue')).default
    const wrapper = mountPage(Courses)

    const course = { id: 65, title: COURSE.title }
    wrapper.vm.togglePin(course)
    await wrapper.vm.$nextTick()

    expect(pinnedCourses.isPinned(65)).toBe(true)
    expect(pinnedCourses.list()[0]).toMatchObject({ id: 65, title: COURSE.title })

    wrapper.unmount()
  })

  it('снимает закрепление повторным кликом', async () => {
    const Courses = (await import('../../resources/js/Pages/Courses.vue')).default
    const wrapper = mountPage(Courses)

    const course = { id: 65, title: COURSE.title }
    wrapper.vm.togglePin(course)
    wrapper.vm.togglePin(course)
    await wrapper.vm.$nextTick()

    expect(pinnedCourses.isPinned(65)).toBe(false)

    wrapper.unmount()
  })
})

describe('Стили: переменные дизайн-системы', () => {
  it('LeftSideMenu не использует несуществующие токены', () => {
    const source = readFileSync('resources/js/Pages/Navigation/LeftSideMenu.vue', 'utf-8')
    for (const ghost of ['--c-radius-lg', '--c-shadow-sm', '--c-border-light', '--c-text-3', '--c-primary-100']) {
      expect(source, `несуществующий токен ${ghost}`).not.toContain(ghost)
    }
  })
})

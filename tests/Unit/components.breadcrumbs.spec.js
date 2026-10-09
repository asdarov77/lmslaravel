// @vitest-environment jsdom
/**
 * Хлебные крошки и каркас (AppLayout).
 *
 * Проверяем то, что было сломано:
 *  - крошек не было ни на одной странице: на «Пользователи → Иванов»
 *    и «Курсы → Конструкция» не было видно, где мы и как вернуться;
 *  - уровни объявлялись в meta маршрута, но попадали ПОМЕЧКОЙ маршрута
 *    (`}, breadcrumbs: [...] },`) — Vue Router читает только meta,
 *    и крошки молча не появлялись;
 *  - футер был собран на классах `container mx-auto flex
 *    justify-between items-center` — это Tailwind и Bulma
 *    одновременно, и ни того, ни другого в проекте нет: копирайт и
 *    переключатель языка стояли друг под другом;
 *  - рабочая область не ограничивалась по ширине: таблицы
 *    растягивались на весь экран.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { createRouter, createWebHashHistory } from 'vue-router'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import Breadcrumbs from '../../resources/js/components/ui/Breadcrumbs.vue'
import routes from '../../resources/js/Router/routes'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

const StubList = { template: '<div />' }

const makeRouter = () =>
  createRouter({
    history: createWebHashHistory(),
    // Крошки строятся из meta настоящих маршрутов приложения: если
    // уровень выпал бы из meta, тест поймал бы это здесь.
    routes: routes.map((r) => ({ ...r, component: StubList })),
  })

const mountCrumbs = async (path) => {
  const router = makeRouter()
  router.push(path)
  await router.isReady()

  return mount(Breadcrumbs, {
    global: { plugins: [vuetify, i18n, router] },
  })
}

const crumbsOf = (wrapper) =>
  wrapper.findAll('.u-crumbs__item').map(n => n.text().replace(/\s+/g, ' ').trim())

describe('Breadcrumbs: уровни объявлены в meta маршрутов', () => {
  it('у ключевых разделов есть breadcrumbs внутри meta', () => {
    const withCrumbs = routes
      .filter(r => Array.isArray(r.meta?.breadcrumbs))
      .map(r => r.path)

    for (const path of ['/user/list', '/groups/list', '/courses/list', '/categories',
      '/dashboard', '/my/learning', '/calendar', '/my/exams', '/questions-main']) {
      expect(withCrumbs, `маршрут ${path} должен объявлять крошки`).toContain(path)
    }
  })

  it('крошки лежат именно в meta, а не на уровне маршрута', () => {
    // Регресс: `meta: {...}, breadcrumbs: [...] },` — Vue Router
    // игнорирует неизвестное поле маршрута, и крошки не появлялись.
    for (const route of routes) {
      expect(
        route.breadcrumbs,
        `у маршрута ${route.path} breadcrumbs обязаны быть в meta`
      ).toBeUndefined()
    }
  })

  it('в meta нет вызовов $t: meta вычисляется вне компонента', () => {
    // $t не существует на уровне модуля: страница падала с
    // «$t is not defined». В meta лежат ключи перевода.
    const raw = JSON.stringify(routes.map(r => r.meta?.breadcrumbs ?? null))
    expect(raw).not.toContain('$t')
  })

  it('все ключи крошек существуют в ru и en', async () => {
    const resolve = (obj, path) =>
      path.split('.').reduce((acc, part) => (acc && typeof acc === 'object' ? acc[part] : undefined), obj)

    for (const route of routes) {
      for (const crumb of route.meta?.breadcrumbs ?? []) {
        expect(resolve(ru, crumb.key), `ru: ${crumb.key}`).toBeTruthy()
        expect(resolve(en, crumb.key), `en: ${crumb.key}`).toBeTruthy()
      }
    }
  })
})

describe('Breadcrumbs: отрисовка', () => {
  it('показывает путь раздела', async () => {
    const wrapper = await mountCrumbs('/user/list')
    const crumbs = crumbsOf(wrapper)

    expect(crumbs[0]).toContain('Система управления обучением')
    expect(crumbs.join(' ')).toContain('Пользователи')
  })

  it('последний уровень — текст, а не ссылка', async () => {
    // Текущая страница не должна вести сама на себя: возврат по
    // ней только сбивает с толку.
    const wrapper = await mountCrumbs('/user/list')
    const links = wrapper.findAll('a.u-crumbs__link')
    const currents = wrapper.findAll('.u-crumbs__current')

    expect(links.length).toBe(1)
    expect(links[0].attributes('href')).toContain('/')
    expect(currents.length).toBe(1)
    expect(currents[0].text()).toContain('Пользователи')
  })

  it('на странице без meta.breadcrumbs ничего не рисует', async () => {
    // Именно /login, а не '/': корневой адрес стал редиректом на
    // /dashboard, а у кабинета крошки есть. Проверка на '/' проверяла бы
    // уже не то, что задумано, и проходила бы случайно. /about больше
    // не маршрут — статические страницы удалены.
    const wrapper = await mountCrumbs('/login')
    expect(crumbsOf(wrapper)).toHaveLength(0)
    expect(wrapper.find('.u-crumbs').exists()).toBe(false)
  })

  it('на странице записи показывает все уровни, включая её саму', async () => {
    const wrapper = await mountCrumbs('/user/edit/7')
    const crumbs = crumbsOf(wrapper).join(' | ')

    expect(crumbs).toContain('Пользователи')
    expect(crumbs).toContain('Редактировать')
  })

  it('уровень без ссылки не кликабелен', async () => {
    const wrapper = await mountCrumbs('/user/edit/7')
    const hrefs = wrapper.findAll('a').map(a => a.attributes('href'))

    // Ссылка на список есть, на саму карточку — нет.
    expect(hrefs.some(h => String(h).includes('/user/list'))).toBe(true)
    expect(hrefs.some(h => String(h).includes('/user/edit'))).toBe(false)
  })

  it('имеет aria-label навигационной области', async () => {
    const wrapper = await mountCrumbs('/user/list')
    expect(wrapper.find('.u-crumbs').attributes('aria-label')).toBe('Хлебные крошки')
  })

  it('помечает текущий уровень через aria-current', async () => {
    const wrapper = await mountCrumbs('/groups/list')
    expect(wrapper.find('.u-crumbs__current').attributes('aria-current')).toBe('page')
  })
})

describe('Каркас: футер и ширина рабочей области', () => {
  it('в App.vue не осталось классов, которых в проекте нет', async () => {
    const { readFileSync } = await import('node:fs')
    const app = readFileSync('resources/js/App.vue', 'utf-8')

    // Tailwind (`flex`, `justify-between`, `items-center`, `container`,
    // `mx-auto`, `right`) и Bulma (`container`).
    expect(app).not.toMatch(/class="[^"]*\b(mx-auto|justify-between|items-center)\b/)
    expect(app).not.toMatch(/<div class="container/)
  })

  it('футер отрисован на классах дизайн-системы', async () => {
    const { readFileSync } = await import('node:fs')
    const app = readFileSync('resources/js/App.vue', 'utf-8')

    expect(app).toContain('app__footer-inner')
    expect(app).toContain('app__copyright')
    expect(app).toContain('app__langs')
  })

  it('стили футера и ширины объявлены в app.css', async () => {
    const { readFileSync } = await import('node:fs')
    const css = readFileSync('resources/css/app.css', 'utf-8')

    expect(css).toContain('.app__footer-inner')
    expect(css).toContain('justify-content: space-between')
    // Селектор с .v-container: у Vuetify `.v-container--fluid`
    // задаёт max-width: 100% двумя классами и побеждает одиночный.
    expect(css).toContain('.v-container.app__content')
  })
})

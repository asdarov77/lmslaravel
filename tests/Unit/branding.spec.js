// @vitest-environment jsdom
/**
 * Брендинг: знак, название, заголовки страниц.
 *
 * Закрывает то, что ломало идентичность приложения:
 *  - <title> ставился один раз из APP_NAME, и у всех страниц было
 *    одинаковое название: во вкладках, истории и поиске это выглядело
 *    как один и тот же сайт;
 *  - заголовок собирался с синтаксисом %(page)s — это подстановка
 *    PHP/gettext, в vue-i18n именованная подстановка записывается как
 *    {page}, и шаблон попадал в заголовок буквально;
 *  - заголовок не менялся при смене языка: хук afterEach срабатывает
 *    только при навигации;
 *  - `__()` в blade возвращал сам ключ, потому что каталог переводов
 *    лежит в resources/lang, а не в корневом lang (создавался
 *    не туда), и описание страницы выводилось как «app.description».
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { readFileSync } from 'node:fs'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import AppBrand from '../../resources/js/components/ui/AppBrand.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

const mountBrand = (props = {}) =>
  mount(AppBrand, { props, global: { plugins: [vuetify, i18n] } })

describe('AppBrand: знак и название', () => {
  it('рисует знак inline-SVG, а не картинку файлом', () => {
    // Файл-картинка не перекрасилась бы под тёмную тему; знак
    // наследует currentColor и токены.
    const wrapper = mountBrand()
    expect(wrapper.find('svg.u-brand__mark').exists()).toBe(true)
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('цвета знака заданы токенами, а не константами', () => {
    const source = readFileSync('resources/js/components/ui/AppBrand.vue', 'utf-8')

    expect(source).toContain('fill: var(--c-primary)')
    expect(source).toContain('stroke: var(--c-on-primary)')
    expect(source).not.toMatch(/fill:\s*#[0-9a-f]{3,6}/i)
    expect(source).not.toMatch(/stroke:\s*#[0-9a-f]{3,6}/i)
  })

  it('показывает короткое и полное название', () => {
    const wrapper = mountBrand({ full: true })
    expect(wrapper.find('.u-brand__short').text()).toBe(ru.app.brand)
    expect(wrapper.find('.u-brand__full').text()).toBe(ru.app.title)
  })

  it('без флага полного названия не показывает', () => {
    const wrapper = mountBrand()
    expect(wrapper.find('.u-brand__full').exists()).toBe(false)
    expect(wrapper.find('.u-brand__short').text()).toBe(ru.app.brand)
  })

  it('подписан на языке интерфейса', () => {
    i18n.global.locale.value = 'en'
    const wrapper = mountBrand({ full: true })

    expect(wrapper.find('.u-brand__full').text()).toBe(en.app.title)
    i18n.global.locale.value = 'ru'
  })

  it('знак помечен декоративным, чтобы не читался скринридером', () => {
    // Название рядом уже есть, поэтому повторять его в accessibility
    // tree незачем.
    const wrapper = mountBrand()
    const svg = wrapper.find('svg.u-brand__mark')

    expect(svg.attributes('aria-hidden')).toBe('true')
    expect(svg.attributes('focusable')).toBe('false')
  })
})

describe('Заголовок документа', () => {
  const routes = readFileSync('resources/js/Router/routes.js', 'utf-8')

  it('у ключевых разделов объявлен titleKey', () => {
    for (const [path, key] of [
      ['/', 'home'], ['/user/list', 'users'], ['/groups/list', 'groups'],
      ['/courses/list', 'courses'], ['/categories', 'categories'],
      ['/dashboard', 'dashboard'], ['/my/exams', 'exams'],
      ['/calendar', 'calendar'], ['/questions-main', 'questionbank'],
    ]) {
      expect(routes, `маршрут ${path}`).toContain(`path: '${path}'`)
      expect(routes, `у ${path} должен быть titleKey`).toContain(`titleKey: '${key}'`)
    }
  })

  it('titleKey лежит в meta, а не внутри объекта крошки', () => {
    // Ошибка была именно такой: скрипт искал первую `}` после
    // `meta: {`, а это конец первого объекта крошки, и ключ
    // оказывался полем крошки вместо поля meta.
    const metaLines = routes
      .split('\n')
      .filter(l => l.includes('titleKey') && l.includes('meta: {'))

    expect(metaLines.length).toBeGreaterThan(5)

    // Проверяем не по тексту, а по глубине скобок: titleKey должен
    // лежать на уровне meta (глубина 1), а не внутри объекта крошки
    // (глубина 2). Порядок полей при этом не важен: между крошками и
    // titleKey может стоять permission.
    for (const line of metaLines) {
      const start = line.indexOf('meta: {') + 'meta: '.length
      const keyAt = line.indexOf('titleKey:')
      if (keyAt === -1) continue

      let depth = 0
      for (let i = start; i < keyAt; i += 1) {
        if (line[i] === '{') depth += 1
        else if (line[i] === '}') depth -= 1
      }

      expect(depth, `titleKey на уровне meta: ${line.trim()}`).toBe(1)
    }
  })

  it('у всех ключей перевода есть подпись', () => {
    const keys = [...routes.matchAll(/titleKey: '([^']+)'/g)].map(m => m[1])

    expect(keys.length).toBeGreaterThan(10)
    for (const key of keys) {
      expect(ru.app.pages, `ru.app.pages.${key}`).toHaveProperty(key)
      expect(en.app.pages, `en.app.pages.${key}`).toHaveProperty(key)
    }
  })

  it('подстановка в заголовке именованная, а не gettext', () => {
    // %(page)s — синтаксис PHP; vue-i18n ждёт {page}. С %(page)s
    // заголовок выводился шаблоном целиком.
    expect(ru.app.titleBy).toContain('{page}')
    expect(ru.app.titleBy).not.toContain('%(page)s')
    expect(en.app.titleBy).toContain('{page}')
  })

  it('заголовок пересобирается при смене языка', () => {
    const source = readFileSync('resources/js/app.js', 'utf-8')

    expect(source, 'нужен следитель за локалью').toMatch(/watch\(i18n\.global\.locale/)
    expect(source, 'нужно хранить текущий маршрут').toContain('let current')
    expect(source).toContain('applyTitle')
  })

  it('знак подключён в шапке', () => {
    const app = readFileSync('resources/js/App.vue', 'utf-8')

    expect(app).toContain('<app-brand')
    expect(app).toContain("import AppBrand from './components/ui/AppBrand.vue'")
    // Ссылка остаётся: по названию системы возвращаемся на главную.
    expect(app).toMatch(/to="\/"[^>]*class="app__brand"/)
  })
})

describe('Метаданные страницы', () => {
  it('в blade объявлены иконка, описание и цвет панели', () => {
    const blade = readFileSync('resources/views/app.blade.php', 'utf-8')

    expect(blade).toContain('rel="icon"')
    expect(blade).toContain('favicon.svg')
    expect(blade).toContain('name="description"')
    // Цвет панели двумя media: под светлую и тёмную тему.
    expect(blade.match(/name="theme-color"/g)).toHaveLength(2)
    expect(blade).toContain('prefers-color-scheme: light')
    expect(blade).toContain('prefers-color-scheme: dark')
  })

  it('описание берётся из перевода, а не из сырого ключа', () => {
    const blade = readFileSync('resources/views/app.blade.php', 'utf-8')
    expect(blade).toContain("__('app.description')")
  })

  it('переводы лежат там, где их читает приложение', () => {
    // config/app.php указывает lang_path на resources/lang.
    // Каталог lang/ в корне приложение не читает: именно поэтому
    // __() возвращал сам ключ и описание выводилось как
    // «app.description».
    const config = readFileSync('config/app.php', 'utf-8')
    const usesResourcesLang = /['"]resources\/lang['"]/.test(config) || !/lang_path/.test(config)

    if (usesResourcesLang) {
      expect(readFileSync('resources/lang/ru/app.php', 'utf-8')).toContain("'description'")
      expect(readFileSync('resources/lang/en/app.php', 'utf-8')).toContain("'description'")
    }
  })

  it('знак для фавикона существует и это валидный SVG', () => {
    const svg = readFileSync('public/favicon.svg', 'utf-8')

    expect(svg.trim()).toMatch(/^<svg[\s\S]*<\/svg>$/)
    expect(svg).toContain('viewBox="0 0 32 32"')
    // Отдельный файл, а не копия из components: тест сверяет, что
    // он вообще есть, иначе вкладка остаётся со стандартной иконкой.
    expect(readFileSync('resources/js/components/ui/AppBrand.vue', 'utf-8')).toContain('viewBox="0 0 32 32"')
  })
})

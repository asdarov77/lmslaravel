// @vitest-environment jsdom
/**
 * Боковое меню: видимость пунктов по правам.
 *
 * Реальная жалоба обучаемого, которую закрывает этот файл:
 *  - в меню были «Файлы», «Категории», «Пользователи», «Группы», и каждый
 *    из них при клике отдавал 403, потому что пункт имел contentType: ""
 *    («видно всем») вместо требования права;
 *  - пункт «Учебный план» имел contentType: " " (пробел). visibleFor()
 *    делает .trim(), получал пустую строку, массив требований пустел и
 *    пункт показывался ВСЕМ — опять 403 по маршруту users.courses;
 *  - «Экзамены» были спрятаны за manage-users, хотя у обучаемого есть
 *    право exams.take и он должен проходить назначенный экзамен;
 *  - когда все пункты группы отфильтровывались, оставался её заголовок
 *    («Управление пользователями») с нулём пунктов внутри.
 */
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import LeftSideMenu from '../../resources/js/Pages/Navigation/LeftSideMenu.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

/**
 * Настоящий стор вместо мока.
 *
 * Левый компонент использует mapState('Auth', ...), поэтому подмена
 * $store объектом без state падала с «Cannot read properties of undefined
 * (reading '_modulesNamespaceMap')». Модуль повторяет контракт геттера
 * hasPermission стора приложения: достаточно ЛЮБОГО совпадения (OR).
 */
const authModule = slugs => ({
  namespaced: true,
  state: () => ({
    accessToken: 'token',
    user: { fio: 'Петров Иван Иванович', role: 'Обучаемый' },
    permissionSlugs: slugs,
  }),
  getters: {
    permissionSet: state => new Set(state.permissionSlugs),
    isSuperAdmin: () => false,
    hasPermission: state => required => {
      const wanted = (Array.isArray(required) ? required : [required]).filter(Boolean).map(String)
      // Пункт без требований доступен всем — как в сторе приложения.
      if (wanted.length === 0) return true
      return wanted.some(slug => state.permissionSlugs.includes(slug))
    },
  },
})

const mountMenu = permissions =>
  mount(LeftSideMenu, {
    global: {
      plugins: [
        vuetify,
        i18n,
        createStore({ modules: { Auth: authModule(permissions) } }),
      ],
      stubs: { 'logout-app': true },
    },
  })

const titles = wrapper =>
  wrapper.findAll('.v-list-item-title').map(n => n.text())

describe('Боковое меню: видимость по правам', () => {
  it('обучаемому показывает только его пункты, без 403-ловушек', () => {
    // Права обучаемого по role_matrix.
    const wrapper = mountMenu(['courses.view', 'content.view', 'exams.take', 'dictionaries.view'])
    const list = titles(wrapper)

    expect(list).toContain('Курсы')
    expect(list).toContain('Моё обучение')
    expect(list).toContain('Личный кабинет')
    expect(list).toContain('Экзамены')

    // Пункты, которые уводили обучаемого в 403.
    expect(list).not.toContain('Файлы')
    expect(list).not.toContain('Категории')
    expect(list).not.toContain('Пользователи')
    expect(list).not.toContain('Группы')
    expect(list).not.toContain('Права доступа')
    // Запись групп на курсы — методическая операция.
    expect(list).not.toContain('Учебный план')
  })

  it('не оставляет пустую группу «Управление пользователями»', () => {
    // У обучаемого все пункты группы отфильтрованы. Раньше оставался
    // заголовок группы с нулём пунктов — выглядело как недогруженная
    // страница и вводило в заблуждение.
    const wrapper = mountMenu(['courses.view', 'content.view', 'exams.take'])
    expect(wrapper.find('.v-list-group').exists()).toBe(false)
    expect(titles(wrapper)).not.toContain('Управление пользователями')
  })

  it('инструктору показывает методические пункты', () => {
    const wrapper = mountMenu([
      'courses.view', 'courses.manage', 'content.manage', 'files.upload',
      'categories.manage', 'groups.view', 'users.view', 'users.courses',
      'questions.view', 'exams.take',
    ])
    const list = titles(wrapper)

    expect(list).toContain('Файлы')
    expect(list).toContain('Категории')
    expect(list).toContain('Учебный план')
    expect(list).toContain('Экзамены')

    // Права доступа инструктору доступны, но ограниченно: он управляет
    // только своей группой и только правами, которые имеет сам
    // (PermissionScope). Поэтому пункт в меню есть.
    expect(list).toContain('Права доступа')

    // А вот банк вопросов и календарь — только для тех, кто ведёт
    // методику полностью; в списке прав инструктора их нет.
    expect(list).not.toContain('Банк вопросов')
    expect(list).not.toContain('Календарь')
  })

  it('администратору остаётся полное меню', () => {
    const wrapper = mountMenu([
      'content.manage', 'categories.manage', 'users.view', 'users.courses',
      'users.permissions', 'groups.view', 'exams.take', 'courses.view',
    ])
    const list = titles(wrapper)

    expect(list).toContain('Файлы')
    expect(list).toContain('Категории')
    expect(list).toContain('Пользователи')
    expect(list).toContain('Группы')
    expect(list).toContain('Права доступа')
  })

  it('ни один пункт без требований не ведёт в 403 для обучаемого', () => {
    // Пункт, у которого contentType не задан или содержит «пробел»,
    // считался доступным всем. Проверяем, что таких пунктов в меню нет:
    // каждый пункт либо виден обучаемому, либо требует право, которого у
    // него нет.
    const trainee = ['courses.view', 'content.view', 'exams.take', 'dictionaries.view']
    const wrapper = mountMenu(trainee)

    const visible = titles(wrapper)
    const forbiddenForTrainee = ['Файлы', 'Категории', 'Пользователи', 'Группы', 'Учебный план', 'Права доступа']

    visible.forEach(title => {
      expect(forbiddenForTrainee).not.toContain(title)
    })
  })

  it('не показывает «Мои курсы» (/auk): это дубликат личного кабинета', () => {
    // Два пункта «про одно и то же» в меню — лишнее. Сам маршрут /auk
    // остался рабочим, убран только пункт меню.
    const wrapper = mountMenu(['courses.view', 'content.view', 'exams.take'])
    expect(titles(wrapper)).not.toContain('Мои курсы')
  })
})

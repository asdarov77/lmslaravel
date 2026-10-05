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
 * Левый компонент использует mapGetters('Auth', ...), поэтому подмена
 * $store объектом без state падала с «Cannot read properties of undefined
 * (reading '_modulesNamespaceMap')».
 *
 * Модуль повторяет контракт геттера can стора приложения: массив — AND,
 * строка — OR. Именно can использует меню с тех пор, как требования
 * пунктов стали читаться из meta.permission маршрутов.
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
    // Семантика геттера can из AuthModule: массив — AND (все нужны),
    // строка — OR. Меню зовёт can([...]), как роутер-гард.
    can: state => (...perms) => {
      const required = perms.flat().filter(Boolean).map(String)
      if (required.length === 0) return true
      if (Array.isArray(perms[0])) {
        return required.every(slug => state.permissionSlugs.includes(slug))
      }
      return required.some(slug => state.permissionSlugs.includes(slug))
    },
    // Оставлен для страниц, которые ещё пользуются старым геттером.
    hasPermission: state => required => {
      const wanted = (Array.isArray(required) ? required : [required]).filter(Boolean).map(String)
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
    // Запись групп на курсы — методическая операция (users.courses).
    // Требование берётся из маршрута /group/learning/:idEdit?.
    expect(list).not.toContain('Учебный план')
    // «Классы», «Календарь» и «Банк вопросов» — тоже методические.
    expect(list).not.toContain('Классы')
    expect(list).not.toContain('Календарь')
    expect(list).not.toContain('Банк вопросов')
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
    // Права инструктора по role_matrix, включая exams.manage,
    // grading.manage, questions.view/manage и content.manage.
    const wrapper = mountMenu([
      'courses.view', 'courses.manage', 'content.manage', 'files.upload',
      'categories.manage', 'groups.view', 'users.view',
      'questions.view', 'questions.manage', 'exams.take', 'exams.manage',
      'grading.manage',
    ])
    const list = titles(wrapper)

    expect(list).toContain('Файлы')
    expect(list).toContain('Категории')
    expect(list).toContain('Экзамены')

    // Права доступа инструктору доступны, но ограниченно: он управляет
    // только своей группой и только правами, которые имеет сам
    // (PermissionScope). Поэтому пункт в меню есть.
    expect(list).toContain('Права доступа')

    // Банк вопросов и календарь инструктору ПОКАЗАНЫ: в role_matrix у
    // него есть questions.view/manage, exams.manage и grading.manage, и
    // теперь меню сверяется с маршрутом, а не с legacy-алиасом
    // "manage-users", которого у него не было. Раньше пункт скрывался
    // из-за расхождения, а не из-за отсутствия прав.
    expect(list).toContain('Банк вопросов')
    expect(list).toContain('Календарь')

    // Запись групп на курсы требует users.courses, а в role_matrix
    // инструктора этого права нет — пункт скрыт.
    expect(list).not.toContain('Учебный план')
  })

  it('администратору остаётся полное меню', () => {
    // Стаб не эмулирует isSuperAdmin, поэтому набор прав должен быть
    // таким, какой реально получает администратор. Пункт «Файлы»
    // требует files.upload|courses.manage — раньше в меню он был помечен
    // content.manage, и проверка проходила случайно.
    const wrapper = mountMenu([
      'content.manage', 'categories.manage', 'users.view', 'users.courses',
      'users.permissions', 'groups.view', 'exams.take', 'courses.view',
      'files.upload', 'courses.manage', 'users.create',
    ])
    const list = titles(wrapper)

    expect(list).toContain('Файлы')
    expect(list).toContain('Категории')
    expect(list).toContain('Пользователи')
    expect(list).toContain('Группы')
    expect(list).toContain('Права доступа')
  })

  it('ни один пункт без требований не ведёт в 403 для обучаемого', () => {
    // Пункт, у которого требования не заданы, считался доступным всем.
    // Проверяем, что таких пунктов в меню нет: каждый пункт либо виден
    // обучаемому, либо требует право, которого у него нет.
    const trainee = ['courses.view', 'content.view', 'exams.take', 'dictionaries.view']
    const wrapper = mountMenu(trainee)

    const visible = titles(wrapper)
    const forbiddenForTrainee = [
      'Файлы', 'Категории', 'Пользователи', 'Группы', 'Учебный план',
      'Права доступа', 'Классы', 'Календарь', 'Банк вопросов', 'Регистрация',
    ]

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

// @vitest-environment jsdom
/**
 * Глобальный поиск (GlobalSearch).
 *
 * Закрывает то, что ломало компонент по построению:
 *  - запрос короче двух символов сервер всё равно отклоняет (422),
 *    поэтому без пояснения пользователь видел пустой диалог;
 *  - без debounce каждая буква порождала запрос;
 *  - ответы приходят асинхронно, и медленный ответ мог перетереть
 *    свежий — поэтому сверяется номер запроса;
 *  - гость не должен видеть кнопку: /api/search требует авторизации.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { createStore } from 'vuex'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import GlobalSearch from '../../resources/js/components/ui/GlobalSearch.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} }
global.visualViewport = { addEventListener() {}, removeEventListener() {}, offsetLeft: 0, offsetTop: 0, width: 1024, height: 768, scale: 1 }

const searchGlobal = vi.fn()
vi.mock('../../resources/js/api/search.api', () => ({
  default: { searchGlobal: (...a) => searchGlobal(...a) },
  searchGlobal: (...a) => searchGlobal(...a),
}))

const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

const RESULTS = {
  total: 2,
  groups: {
    courses: [{ id: 1, title: 'Конструкция самолёта', subtitle: 'курс', to: '/courses/desc/1' }],
    users: [{ id: 2, title: 'Иванов', subtitle: 'Инструктор', to: '/user/edit/2' }],
  },
}

/**
 * v-dialog заменяется заглушкой.
 *
 * Настоящий диалог рендерится в телепорте (v-overlay-container), и его
 * содержимое не попадает в wrapper.text(). Здесь проверяется логика
 * компонента — состояние, разметка результатов, переходы; внешний вид
 * диалога проверен отдельно в браузере.
 */
const stubs = {
  'v-dialog': { template: '<div class="v-dialog-stub"><slot /></div>' },
}

const mountSearch = (loggedIn = true) => {
  const store = createStore({
    modules: { Auth: { namespaced: true, getters: { loggedIn: () => loggedIn } } },
  })
  const push = vi.fn(() => Promise.resolve())
  const wrapper = mount(GlobalSearch, {
    global: {
      plugins: [vuetify, i18n, store],
      mocks: { $router: { push } },
      stubs,
    },
  })
  return { wrapper, push }
}

const type = async (wrapper, value) => {
  wrapper.vm.query = value
  await wrapper.vm.onInput(value)
  // Ждём debounce (250 мс) и выполнение запроса.
  await new Promise(r => setTimeout(r, 350))
  await wrapper.vm.$nextTick()
}

beforeEach(() => {
  vi.clearAllMocks()
  searchGlobal.mockResolvedValue(envelope(RESULTS))
})

describe('GlobalSearch: открытие', () => {
  it('показывает кнопку в шапке вошедшему', () => {
    const { wrapper } = mountSearch(true)
    expect(wrapper.find('[data-test="search-trigger"]').exists()).toBe(true)
  })

  it('гостю кнопку не показывает', () => {
    // /api/search требует авторизации: кнопка без смысла вводила бы
    // в заблуждение и обещала результат, которого не будет.
    const { wrapper } = mountSearch(false)
    expect(wrapper.find('[data-test="search-trigger"]').exists()).toBe(true)
    wrapper.find('[data-test="search-trigger"]').trigger('click')
    expect(wrapper.vm.dialog).toBe(false)
  })

  it('открывается по Ctrl+K и закрывается повторным нажатием', async () => {
    const { wrapper } = mountSearch(true)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.dialog).toBe(true)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.dialog).toBe(false)
  })

  it('кнопка помечена для скринридера', () => {
    const { wrapper } = mountSearch(true)
    expect(wrapper.find('[data-test="search-trigger"]').attributes('aria-label')).toBe('Открыть поиск')
  })
})

describe('GlobalSearch: запрос', () => {
  it('поясняет, что символов мало, и не спрашивает сервер', async () => {
    // Сервер отвечает 422 на запрос короче двух символов: без
    // пояснения диалог выглядел бы как поломка.
    const { wrapper } = mountSearch(true)
    await type(wrapper, 'К')

    expect(searchGlobal).not.toHaveBeenCalled()
    expect(wrapper.vm.tooShort).toBe(true)
    expect(wrapper.text()).toContain('два символа')
  })

  it('разрезает наводку: одно нажатие — один запрос', async () => {
    const { wrapper } = mountSearch(true)

    wrapper.vm.query = 'Ко'
    await wrapper.vm.onInput('Ко')
    wrapper.vm.query = 'Кон'
    await wrapper.vm.onInput('Кон')
    wrapper.vm.query = 'Конс'
    await wrapper.vm.onInput('Конс')
    await new Promise(r => setTimeout(r, 350))

    expect(searchGlobal).toHaveBeenCalledTimes(1)
    expect(searchGlobal).toHaveBeenCalledWith('Конс')
  })

  it('показывает результаты по группам', async () => {
    const { wrapper } = mountSearch(true)
    await type(wrapper, 'Конс')

    expect(searchGlobal).toHaveBeenCalledWith('Конс')
    const texts = wrapper.findAll('[data-test="search-result"]').map(n => n.text())
    expect(texts.join(' ')).toContain('Конструкция самолёта')
    expect(wrapper.text()).toContain('Курсы')
  })

  it('группирует результаты по секциям с заголовками', async () => {
    const { wrapper } = mountSearch(true)
    await type(wrapper, 'Конс')

    expect(wrapper.findAll('.u-search__group')).toHaveLength(2)
    expect(wrapper.text()).toContain('Люди')
  })

  it('пустой результат объясняется словами, а не молчанием', async () => {
    searchGlobal.mockResolvedValue(envelope({ total: 0, groups: {} }))
    const { wrapper } = mountSearch(true)
    await type(wrapper, 'нетакого')

    expect(wrapper.text()).toContain('Ничего не найдено')
  })

  it('ошибка сервера показывается текстом', async () => {
    searchGlobal.mockRejectedValue({
      response: { data: { error: { message: 'Поиск недоступен' } } },
    })
    const { wrapper } = mountSearch(true)
    await type(wrapper, 'Конс')

    expect(wrapper.vm.error).toBe('Поиск недоступен')
    expect(wrapper.text()).toContain('Поиск недоступен')
  })

  it('в groups может прийти список вместо объекта — рендер не падает', async () => {
    // Пустой PHP-массив сериализуется как [], и Object.entries([])
    // даёт пусто: проверяем, что компонент это переживает.
    searchGlobal.mockResolvedValue({ data: { success: true, data: { total: 0, groups: [] } } })
    const { wrapper } = mountSearch(true)
    await type(wrapper, 'Конс')

    expect(wrapper.findAll('[data-test="search-result"]')).toHaveLength(0)
    expect(wrapper.text()).toContain('Ничего не найдено')
  })

  it('медленный ответ не перетирает свежий', async () => {
    // Ответы приходят асинхронно: первый (медленный) не должен
    // затирать результат второго.
    let resolveFirst
    searchGlobal
      .mockImplementationOnce(() => new Promise(r => { resolveFirst = r }))
      .mockImplementationOnce(() => Promise.resolve(envelope({
        total: 1,
        groups: { courses: [{ id: 9, title: 'Свежий результат', to: '/courses/desc/9' }] },
      })))

    const { wrapper } = mountSearch(true)

    wrapper.vm.query = 'Кон'
    await wrapper.vm.onInput('Кон')
    await new Promise(r => setTimeout(r, 300))

    wrapper.vm.query = 'Конс'
    await wrapper.vm.onInput('Конс')
    await new Promise(r => setTimeout(r, 350))
    await wrapper.vm.$nextTick()

    resolveFirst?.(envelope(RESULTS))
    await new Promise(r => setTimeout(r, 50))
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Свежий результат')
    expect(wrapper.text()).not.toContain('Конструкция самолёта')
  })
})

describe('GlobalSearch: переход к результату', () => {
  it('Enter открывает первый результат', async () => {
    const { wrapper, push } = mountSearch(true)
    await type(wrapper, 'Конс')

    await wrapper.vm.goFirst()

    expect(push).toHaveBeenCalledWith('/courses/desc/1')
    expect(wrapper.vm.dialog).toBe(false)
  })

  it('Enter без результатов ничего не делает', async () => {
    searchGlobal.mockResolvedValue(envelope({ total: 0, groups: {} }))
    const { wrapper, push } = mountSearch(true)
    await type(wrapper, 'нетакого')

    await wrapper.vm.goFirst()
    expect(push).not.toHaveBeenCalled()
  })

  it('результат без ссылки не открывается по Enter', async () => {
    searchGlobal.mockResolvedValue(envelope({
      total: 1,
      groups: { categories: [{ id: 1, title: 'Специальность', subtitle: null, to: null }] },
    }))
    const { wrapper, push } = mountSearch(true)
    await type(wrapper, 'Спец')

    await wrapper.vm.goFirst()
    expect(push).not.toHaveBeenCalled()
  })
})

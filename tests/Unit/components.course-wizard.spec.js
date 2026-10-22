// @vitest-environment jsdom
/**
 * Конструктор курса (Фаза 2.3.2): мастер из трёх шагов.
 *
 * Что здесь защищаем:
 *  - шаг живёт в URL (?step=), поэтому перезагрузка не сбрасывает прогресс;
 *  - валидация идёт ПРИ ПЕРЕХОДЕ, а не по «Готово»: нельзя уйти с шага
 *    с незаполненным названием;
 *  - чек-лист готовности считается из данных, а не захардкожен;
 *  - категория — ОДНА (бэкенд ждёт integer), мультивыбор давал 422.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createStore } from 'vuex'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { nextTick } from 'vue'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import CourseWizard from '../../resources/js/Pages/Course/CourseWizard.vue'
import ru from '../../resources/js/locales/ru.json'
import { toastItems, clear } from '../../resources/js/composables/useToast'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru },
})

const categories = [
  { id: 24, title: 'Командир' },
  { id: 25, title: 'Оператор БЛА' },
]

/**
 * Стор для мастера.
 *
 * Actions объявлены как обычные функции: vuex вызывает их с
 * (context, payload), поэтому проверять строковое имя внутри нельзя —
 * так «мок-экшн» молча ничего не коммитил и форма оставалась пустой.
 */
const makeHarness = (courseFixture = null) => {
  const createCourse = vi.fn(async () => ({ id: 1 }))
  const updateCourse = vi.fn(async () => ({ id: 1 }))

  let store
  store = createStore({
    modules: {
      Course: {
        namespaced: true,
        state: () => ({ course: null, categories: [] }),
        getters: { categories: () => store.state.Course.categories },
        mutations: {
          SET_COURSE(s, c) { s.course = c },
          SET_ALL_CATEGORIES(s, c) { s.categories = c },
        },
        actions: {
          // store.commit внутри namespaced-модуля бьёт в КОРЕНЬ, поэтому
          // путь указываем полностью — иначе «unknown mutation type».
          fetchCategories: () => store.commit('Course/SET_ALL_CATEGORIES', categories),
          fetchCourse: () => store.commit('Course/SET_COURSE', courseFixture),
          createCourse,
          updateCourse,
        },
      },
      Auth: { namespaced: true, getters: { can: () => () => true } },
    },
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div/>' } },
      // Шаг «Структура» ведёт в инструменты импорта. Без этих маршрутов
      // router.resolve бросает «No match for {name:...}» при рендере.
      { path: '/classes', name: 'air.store', component: { template: '<div/>' } },
      { path: '/filemanager', name: 'filemanager', component: { template: '<div/>' } },
    ],
  })

  return { store, router, createCourse, updateCourse }
}

const mountWizard = async (props = {}) => {
  const harness = makeHarness(props.course ?? null)
  const router = harness.router

  // История из двух записей: onSubmit уходит через router.back(), и на
  // единственной записи memory-history бросает «cannot go back».
  await router.push('/courses/list')
  await router.isReady()

  const wrapper = mount(CourseWizard, {
    props,
    global: {
      plugins: [vuetify, i18n, harness.store, router],
      stubs: {
        // VTextarea с auto-grow в jsdom падает в watcher: там замеры
        // текста, которых нет без layout. На контракт мастера это не
        // влияет — проверяем логику шагов, а не вёрстку textarea.
        // Спиннер в кнопке во время сохранения: в jsdom падает его
        // watcher (обращение к null в разметке прогресса).
        VProgressCircular: { name: 'VProgressCircular', template: '<span class="stub-spinner" />' },
        VTextarea: {
          name: 'VTextarea',
          props: ['modelValue'],
          emits: ['update:modelValue'],
          template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)"></textarea>',
        },
      },
    },
  })
  await flushPromises()

  return { ...harness, wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
  clear()
  http.get.mockResolvedValue({ data: { success: true, data: { aukstructures: [] } } })
})

const tick = () => new Promise(r => setTimeout(r, 0))

describe('CourseWizard: шаги в URL', () => {
  it('стартует с первого шага и переключает шаг по ?step=', async () => {
    const { wrapper, router } = await mountWizard()

    expect(wrapper.findAll('.u-stepper__nav-item').length).toBe(4)
    expect(wrapper.find('.u-stepper__nav-item.is-active').text()).toContain('Основное')

    // Прямая ссылка на четвёртый шаг открывает именно его.
    await router.replace('/course?step=publish')
    await flushPromises()

    expect(wrapper.find('.u-stepper__nav-item.is-active').text()).toContain('Публикация')
    wrapper.unmount()
  })
})

describe('CourseWizard: валидация при переходе', () => {
  it('не пускает дальше без названия и показывает ошибку поля', async () => {
    const { wrapper } = await mountWizard()

    const next = wrapper.findAll('.u-stepper__foot button').find(b => b.text().includes('Далее'))
    await next.trigger('click')
    await flushPromises()

    expect(wrapper.find('.u-stepper__nav-item.is-active').text()).toContain('Основное')
    expect(wrapper.find('.u-field__error').exists()).toBe(true)
    expect(wrapper.text()).toContain('Укажите название курса')

    wrapper.unmount()
  })

  it('с названием, но без категории — тоже не пускает', async () => {
    const { wrapper } = await mountWizard()

    wrapper.vm.form.values.title = 'Курс'
    await nextTick()

    const next = wrapper.findAll('.u-stepper__foot button').find(b => b.text().includes('Далее'))
    await next.trigger('click')
    await flushPromises()

    expect(wrapper.find('.u-stepper__nav-item.is-active').text()).toContain('Основное')
    expect(wrapper.text()).toContain('Выберите хотя бы одну категорию')

    wrapper.unmount()
  })

  it('с заполненными полями переходит на следующий шаг', async () => {
    const { wrapper, router } = await mountWizard()

    wrapper.vm.form.values.title = 'Курс'
    wrapper.vm.form.values.category_id = 24
    await nextTick()

    const next = wrapper.findAll('.u-stepper__foot button').find(b => b.text().includes('Далее'))
    await next.trigger('click')
    await flushPromises()

    expect(wrapper.find('.u-stepper__nav-item.is-active').text()).toContain('Структура')
    expect(router.currentRoute.value.query.step).toBe('structure')

    wrapper.unmount()
  })
})

describe('CourseWizard: чек-лист готовности', () => {
  it('отражает заполненность данных', async () => {
    const { wrapper } = await mountWizard()

    expect(wrapper.find('[data-test="wizard-readiness"]').text()).toContain('Черновик')

    wrapper.vm.form.values.title = 'Курс'
    wrapper.vm.form.values.category_id = 24
    await nextTick()

    const chip = wrapper.find('[data-test="wizard-readiness"]').text()
    expect(chip).toContain('2')
    expect(chip).not.toContain('Черновик')

    wrapper.unmount()
  })

  it('учитывает уроки из манифеста', async () => {
    http.get.mockResolvedValue({
      data: {
        success: true,
        data: { aukstructures: [{ id: 1, title: 'Урок 1' }, { id: 2, title: 'Урок 2' }] },
      },
    })

    const { wrapper } = await mountWizard({ idEdit: 70, course: { id: 70, title: 'Курс', category_id: 24, long_description: 'Описание' } })

    expect(wrapper.vm.lessons.length).toBe(2)
    expect(wrapper.find('[data-test="wizard-readiness"]').text()).toContain('Готов к публикации')

    wrapper.unmount()
  })
})

describe('CourseWizard: сохранение', () => {
  it('отправляет category_id числом, а не массивом', async () => {
    const { wrapper, createCourse } = await mountWizard()

    wrapper.vm.form.values.title = 'Курс'
    wrapper.vm.form.values.category_id = 24
    await nextTick()

    await wrapper.vm.submitForm()
    await flushPromises()

    expect(createCourse, 'нужен вызов createCourse').toHaveBeenCalled()
    const payload = createCourse.mock.calls[0][1]
    expect(payload.category_id).toBe(24)
    expect(Array.isArray(payload.category_id)).toBe(false)
    expect(payload.title).toBe('Курс')

    wrapper.unmount()
  })

  it('при ошибке не падает и оставляет пользователя на форме', async () => {
    const { wrapper, createCourse } = await mountWizard()

    createCourse.mockRejectedValueOnce(new Error('boom'))

    wrapper.vm.form.values.title = 'Курс'
    wrapper.vm.form.values.category_id = 24
    await nextTick()

    await wrapper.vm.submitForm()
    await flushPromises()

    // Форма на месте и значения не потеряны: ошибка отправки не должна
    // выбрасывать набранное.
    expect(wrapper.find('[data-test="course-title"]').exists()).toBe(true)
    expect(wrapper.vm.form.values.title).toBe('Курс')
    wrapper.unmount()
  })
})

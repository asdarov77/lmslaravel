// @vitest-environment jsdom
/**
 * Страницы сертификатов: список и печатный лист.
 *
 * Проверяются решения, которые ломаются тихо:
 *  - недоступный сертификат должен объяснять, чего не хватает, а не
 *    показывать пустую страницу;
 *  - панель кнопок не должна печататься: на листе нужны рамка, текст
 *    и код проверки;
 *  - код проверки обязан быть напечатан — иначе проверить сертификат
 *    нечем.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import http from '../../resources/js/api/httpClient'
import CertificateView from '../../resources/js/Pages/Learning/CertificateView.vue'
import ru from '../../resources/js/locales/ru.json'

// Компонент импортирует клиент напрямую, поэтому подменять нужно модуль:
// mocks.$api на импорты не влияет и тест проходил бы вхолостую.
vi.mock('../../resources/js/api/httpClient', () => ({
  default: { get: vi.fn() },
}))

globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

const vuetify = createVuetify({
  components: vuetifyComponents,
  directives: vuetifyDirectives,
})

const i18n = createI18n({
  legacy: false,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru },
})

const certificate = {
  fio: 'Петров Пётр',
  course: 'Авиационная безопасность',
  module_title: 'Общие вопросы',
  study_from: '2026-01-10',
  study_to: '2026-03-20',
  issued_at: '2026-03-25',
  lessons_done: 12,
  lessons_total: 12,
  exams_passed: 2,
  exams_total: 2,
  code: '000012000007ABCDEF12',
}

const mountView = (get) => {
  http.get.mockImplementation(get)
  const print = vi.spyOn(window, 'print').mockImplementation(() => {})

  const wrapper = mount(CertificateView, {
    props: { idEdit: 7 },
    global: { plugins: [vuetify, i18n], mocks: { $route: { params: { idEdit: 7 } } } },
  })

  return { wrapper, print }
}

describe('CertificateView — печатный сертификат', () => {
  beforeEach(() => vi.clearAllMocks())

  it('показывает ФИО, курс и код проверки', async () => {
    const { wrapper } = mountView(() => Promise.resolve({ data: { success: true, data: certificate } }))
    await flushPromises()

    expect(wrapper.find('[data-test="cert-sheet"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Петров Пётр')
    expect(wrapper.text()).toContain('Авиационная безопасность')
    expect(wrapper.find('[data-test="cert-code"]').text()).toBe(certificate.code)
  })

  it('не печатает панель кнопок', async () => {
    const { wrapper } = mountView(() => Promise.resolve({ data: { success: true, data: certificate } }))
    await flushPromises()

    const actions = wrapper.find('.no-print')

    expect(actions.exists()).toBe(true)
    expect(actions.find('[data-test="cert-print"]').exists()).toBe(true)
  })

  it('печатает по нажатию', async () => {
    const { wrapper, print } = mountView(() => Promise.resolve({ data: { success: true, data: certificate } }))
    await flushPromises()

    await wrapper.find('[data-test="cert-print"]').trigger('click')

    expect(print).toHaveBeenCalled()
  })

  it('объясняет, почему сертификат недоступен', async () => {
    const { wrapper } = mountView(() =>
      Promise.reject({ response: { status: 403, data: { message: 'Курс ещё не завершён' } } })
    )
    await flushPromises()

    expect(wrapper.find('[data-test="cert-sheet"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="cert-error"]').exists()).toBe(true)
    // Голое «ошибка» бесполезно: человек не понимает, что делать дальше.
    expect(wrapper.text()).toContain('Курс ещё не завершён')
  })

  it('не показывает листы, пока данные грузятся', async () => {
    let resolve
    const { wrapper } = mountView(() => new Promise((r) => {
      resolve = r
    }))

    expect(wrapper.find('[data-test="cert-sheet"]').exists()).toBe(false)

    resolve({ data: { success: true, data: certificate } })
    await flushPromises()

    expect(wrapper.find('[data-test="cert-sheet"]').exists()).toBe(true)
  })
})
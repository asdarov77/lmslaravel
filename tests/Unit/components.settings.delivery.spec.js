// @vitest-environment jsdom
/**
 * Переключатель раздачи материалов (Настройки → Раздача материалов).
 *
 * Галка здесь опаснее обычного чекбокса: режим влияет на выдачу файлов
 * ВСЕМ сразу, и состояние «переключатель включён, а материалы всё ещё
 * отдаёт PHP» выглядит как поломка. Поэтому проверяется главное —
 * что состояние галки берётся из ответа сервера, а не из того, что
 * клиент хотел, и что неудачное сохранение откатывает галку.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import ContentDeliverySettings from '../../resources/js/Pages/Settings/ContentDeliverySettings.vue'
import * as preflight from '../../resources/js/utils/preflight'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

// VProgressCircular (спиннер внутри v-switch) измеряет себя через
// ResizeObserver и visualViewport; в jsdom их нет, и компонент падает
// с «Unhandled error during setup» прямо во время рендера.
global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} }
global.visualViewport = { addEventListener() {}, removeEventListener() {}, offsetLeft: 0, offsetTop: 0, width: 1024, height: 768, scale: 1 }

const get = vi.fn()
const put = vi.fn()
vi.mock('../../resources/js/api/httpClient', () => ({
  default: {
    get: (...a) => get(...a),
    put: (...a) => put(...a),
  },
}))

const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

const mountSettings = () =>
  mount(ContentDeliverySettings, {
    global: { plugins: [vuetify, i18n] },
  })

const flush = async () => {
  await Promise.resolve()
  await Promise.resolve()
  await Promise.resolve()
}

beforeEach(() => {
  vi.clearAllMocks()
  get.mockResolvedValue(envelope({ mode: 'php', available: ['php', 'nginx'], source: 'settings', accel_internal: '/_protected-content' }))
})

describe('ContentDeliverySettings', () => {
  it('показывает текущий режим из настроек', async () => {
    const wrapper = mountSettings()
    await flush()

    expect(get).toHaveBeenCalledWith('/api/settings/content-delivery')
    expect(wrapper.vm.mode).toBe('php')
    expect(wrapper.vm.nginx).toBe(false)
    expect(wrapper.find('[data-test="delivery-current"]').text()).toBe('php')
  })

  it('в режиме nginx показывает предупреждение о внутреннем location', async () => {
    get.mockResolvedValue(envelope({ mode: 'nginx', source: 'settings', accel_internal: '/_protected-content' }))
    const wrapper = mountSettings()
    await flush()

    expect(wrapper.find('[data-test="delivery-nginx-warning"]').exists()).toBe(true)
  })

  it('в режиме php предупреждения нет', async () => {
    const wrapper = mountSettings()
    await flush()

    expect(wrapper.find('[data-test="delivery-nginx-warning"]').exists()).toBe(false)
  })

  it('включение отправляет mode=nginx', async () => {
    put.mockResolvedValue(envelope({ mode: 'nginx', source: 'settings' }))
    const wrapper = mountSettings()
    await flush()

    await wrapper.vm.save(true)

    expect(put).toHaveBeenCalledWith('/api/settings/content-delivery', { mode: 'nginx' })
    expect(wrapper.vm.mode).toBe('nginx')
  })

  it('выключение отправляет mode=php', async () => {
    get.mockResolvedValue(envelope({ mode: 'nginx', source: 'settings' }))
    put.mockResolvedValue(envelope({ mode: 'php', source: 'settings' }))
    const wrapper = mountSettings()
    await flush()

    await wrapper.vm.save(false)

    expect(put).toHaveBeenCalledWith('/api/settings/content-delivery', { mode: 'php' })
    expect(wrapper.vm.mode).toBe('php')
  })

  it('при ошибке сохранения галка возвращается обратно', async () => {
    // Ключевое: сервер не сохранил, а галка осталась бы включённой —
    // и выглядело бы как «переключил, но не работает».
    put.mockRejectedValue({ response: { data: { error: { message: 'Нет прав' } } } })
    const wrapper = mountSettings()
    await flush()

    await wrapper.vm.save(true)

    expect(wrapper.vm.mode).toBe('php')
    expect(wrapper.vm.alert.type).toBe('error')
    expect(wrapper.vm.alert.text).toBe('Нет прав')
  })

  it('состояние берётся из ответа сервера, а не из клиента', async () => {
    // Клиент отправил nginx, но сервер вернул php (например, значение
    // отфильтровали). Галка обязана показать php.
    put.mockResolvedValue(envelope({ mode: 'php', source: 'settings' }))
    const wrapper = mountSettings()
    await flush()

    await wrapper.vm.save(true)

    expect(wrapper.vm.mode).toBe('php')
  })

  it('ошибка загрузки показывается и режим не выдумывается', async () => {
    get.mockRejectedValue({ response: { data: { error: { message: 'Сервис недоступен' } } } })
    const wrapper = mountSettings()
    await flush()

    expect(wrapper.vm.alert.text).toBe('Сервис недоступен')
    expect(wrapper.vm.nginx).toBe(false)
  })
})

describe('ContentDeliverySettings: preflight', () => {
  it('проба сообщает, что nginx стоит перед приложением', async () => {
    const spy = vi.spyOn(preflight, 'probeDelivery').mockResolvedValue({
      ok: true,
      status: 404,
      server: 'nginx',
      isNginx: true,
      verdict: 'nginx-in-front',
      detail: 'nginx отдал 404 на внутренний location',
    })

    const wrapper = mountSettings()
    await flush()

    await wrapper.find('[data-test="delivery-preflight"]').trigger('click')
    await flush()

    expect(spy).toHaveBeenCalled()
    expect(wrapper.find('[data-test="delivery-preflight-result"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="delivery-preflight-result"]').text()).toContain('nginx')

    spy.mockRestore()
  })

  it('при PHP-обработке показывает предупреждение и конфиг nginx', async () => {
    const spy = vi.spyOn(preflight, 'probeDelivery').mockResolvedValue({
      ok: false,
      status: 404,
      server: '',
      isNginx: false,
      verdict: 'php-direct',
      detail: 'Ответ пришёл без заголовка Server — запрос обработал PHP',
    })

    const wrapper = mountSettings()
    await flush()

    await wrapper.vm.runPreflight()
    await flush()

    const result = wrapper.find('[data-test="delivery-preflight-result"]')
    expect(result.exists()).toBe(true)
    expect(result.text()).toMatch(/PHP/)
    // Сниппет собирается из значения internal, пришедшего с сервера.
    expect(wrapper.find('.delivery-snippet').text()).toContain('/_protected-content')

    spy.mockRestore()
  })

  it('проба не ломает страницу, если сеть недоступна', async () => {
    const spy = vi.spyOn(preflight, 'probeDelivery').mockRejectedValue(new Error('boom'))

    const wrapper = mountSettings()
    await flush()

    await expect(wrapper.vm.runPreflight()).rejects.toBeTruthy()
    expect(wrapper.vm.probing).toBe(false)

    spy.mockRestore()
  })
})

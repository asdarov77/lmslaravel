// @vitest-environment jsdom
/**
 * Календарь учебного процесса (resources/js/Pages/EventCalendar.vue).
 *
 * Регрессы, которые закрывает файл.
 *
 *  1. Страница была недописанным демо-шаблоном FullCalendar: два
 *     захардкоженных события («All-day event», «Timed event»), создание
 *     через prompt(), удаление через confirm(), ничего не сохранялось —
 *     после перезагрузки всё исчезало. Тест требует, чтобы события
 *     приходили из API, а календарь был read-only.
 *
 *  2. Ширина сетки. FullCalendar меряет контейнер один раз при
 *     инициализации и на этой странице запоминал ширику больше фактической:
 *     таблица дней уезжала под обрезку, колонки «сб»/«вс» пропадали.
 *     Лечится пересчётом размера по ResizeObserver — тест проверяет, что
 *     наблюдатель подключён к доске.
 *
 *  3. Фильтры должны применяться автоматически, без кнопки «применить»,
 *     и активные фильтры — показываться текстом.
 *
 *  4. Пустое состояние обязано различать «никого не записали» и
 *     «под фильтр ничего не попало» — как на странице курсов.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

const http = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import EventCalendar from '../../resources/js/Pages/EventCalendar.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

const event = (over = {}) => ({
  id: 'learning-1',
  title: 'Курс А',
  start: '2026-10-02',
  end: '2026-10-12',
  allDay: true,
  extendedProps: {
    course_id: 5,
    course_title: 'Курс А',
    module_title: 'Пожар',
    group_id: 1,
    group_name: 'Группа А',
    study_from: '2026-10-02',
    study_to: '2026-10-11',
    status: 'active',
    categories: [],
    ...(over.extendedProps ?? {}),
  },
  ...over,
})

const response = (events, filters = {}) => ({
  data: {
    success: true,
    data: {
      events,
      filters: {
        groups: [{ id: 1, title: 'Группа А' }],
        courses: [{ id: 5, title: 'Курс А' }],
        categories: [],
        ...filters,
      },
    },
    error: null,
    meta: { total: events.length },
  },
})

const tick = () => new Promise(r => setTimeout(r, 0))

/**
 * v-dialog и AppToast заменяются заглушками.
 *
 * Настоящие v-dialog/v-snackbar в jsdom падают с «visualViewport is not
 * defined» — jsdom не реализует этот API, а Vuetify к нему обращается.
 * Здесь проверяется логика компонента (состояние тоста, содержимое
 * карточки периода), а не рендеринг Vuetify; визуально диалог проверен
 * отдельно в браузере.
 */
const stubs = {
  'v-dialog': { template: '<div class="v-dialog-stub"><slot /></div>' },
  AppToast: {
    props: ['modelValue', 'text', 'type'],
    template: '<div class="app-toast-stub" v-if="modelValue">{{ text }}</div>',
  },
}

const mountCalendar = async () => {
  const wrapper = mount(EventCalendar, {
    global: { plugins: [vuetify, i18n], stubs },
  })
  await wrapper.vm.$nextTick()
  await tick()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  http.get.mockResolvedValue(response([event()]))
  // ResizeObserver в jsdom нет — компонент обязан это переживать.
  global.ResizeObserver = class {
    observe() {}
    disconnect() {}
  }
})

describe('Календарь обучения', () => {
  it('берёт события из API, а не из захардкоженного демо', async () => {
    const wrapper = await mountCalendar()

    expect(http.get).toHaveBeenCalledWith('/api/calendar', expect.anything())
    expect(wrapper.vm.events).toHaveLength(1)
    expect(wrapper.text()).not.toContain('All-day event')
  })

  it('подписывает период одной строкой «курс · модуль»', async () => {
    const wrapper = await mountCalendar()

    // Однострочная подпись принципиальна: кастомный многострочный
    // контент задавал колонкам минимальную ширину по самому длинному
    // названию, и таблица разъезжалась за правый край карточки.
    expect(wrapper.vm.eventLabel(event())).toBe('Курс А · Пожар')
    expect(wrapper.vm.eventLabel(event({ extendedProps: { course_title: 'Курс А', module_title: null } }))).toBe('Курс А')
  })

  it('календарь read-only: события нельзя перетащить или создать мышью', async () => {
    const wrapper = await mountCalendar()

    // Источник периодов — запись группы на курс, а не календарь.
    // Редактирование здесь создавало бы вторую правду, которая никуда
    // не сохраняется.
    expect(wrapper.vm.calendarOptions.editable).toBe(false)
    expect(wrapper.vm.calendarOptions.selectable).toBe(false)
  })

  it('наблюдает за шириной доски и пересчитывает размер', async () => {
    const observed = []
    global.ResizeObserver = class {
      constructor(cb) { this.cb = cb }
      observe(el) { observed.push(el) }
      disconnect() {}
    }
    http.get.mockResolvedValue(response([event()]))

    const wrapper = mount(EventCalendar, { global: { plugins: [vuetify, i18n], stubs } })
    await wrapper.vm.$nextTick()
    await tick()

    expect(observed.length, 'ResizeObserver должен быть подключён к доске').toBeGreaterThan(0)
    expect(wrapper.vm.lastBoardWidth).toBe(0)
    expect(wrapper.vm.observeResize).toBeInstanceOf(Function)
  })

  it('статус фильтруется и перезапрашивает ленту', async () => {
    const wrapper = await mountCalendar()
    http.get.mockClear()
    http.get.mockResolvedValue(response([]))

    wrapper.vm.setStatus('completed')
    await tick()

    expect(http.get).toHaveBeenCalledWith('/api/calendar', expect.objectContaining({
      params: expect.objectContaining({ status: 'completed' }),
    }))
  })

  it('повторный клик по тому же статусу снимает фильтр', async () => {
    const wrapper = await mountCalendar()

    wrapper.vm.setStatus('active')
    await tick()
    wrapper.vm.setStatus('active')
    await tick()

    expect(wrapper.vm.query.status).toBeNull()
  })

  it('смена фильтра показывается текстом', async () => {
    const wrapper = await mountCalendar()
    wrapper.vm.query.course_id = 5
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.activeFilterLabels.join(' ')).toContain('Курс А')
    expect(wrapper.vm.hasFilters).toBe(true)
  })

  it('сброс очищает все фильтры', async () => {
    const wrapper = await mountCalendar()
    wrapper.vm.query.course_id = 5
    wrapper.vm.query.status = 'active'
    await tick()

    wrapper.vm.resetFilters()
    await tick()

    expect(wrapper.vm.query.course_id).toBeNull()
    expect(wrapper.vm.query.status).toBeNull()
    expect(wrapper.vm.hasFilters).toBe(false)
  })

  it('пустое состояние различает «нет записей» и «не подошёл фильтр»', async () => {
    const wrapper = await mountCalendar()
    expect(wrapper.text()).not.toContain('Под фильтр ничего не попало')

    http.get.mockResolvedValue(response([]))
    wrapper.vm.setStatus('completed')
    await tick()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.events).toHaveLength(0)
    expect(wrapper.text()).toContain('Под фильтр ничего не попало')
  })

  it('ошибка сервера показывается тостом, а не пустым календарём молча', async () => {
    http.get.mockRejectedValue({ response: { status: 500 } })

    const wrapper = await mountCalendar()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.alert).toBe(true)
    expect(wrapper.vm.alertType).toBe('error')
    expect(wrapper.vm.alertText).not.toBe('')
  })

  it('запрет доступа сообщается отдельным текстом', async () => {
    http.get.mockRejectedValue({ response: { status: 403 } })

    const wrapper = await mountCalendar()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.alertType).toBe('error')
    expect(wrapper.vm.alertText).toBe(wrapper.vm.$t('calendar.forbidden'))
  })

  it('форматирует даты без сдвига на сутки', async () => {
    const wrapper = await mountCalendar()

    // Через new Date() локальная зона сдвигала бы дату назад.
    expect(wrapper.vm.formatDate('2026-10-02')).toBe('02.10.2026')
    expect(wrapper.vm.formatDate(null)).toBe('—')
  })

  it('фильтр типа события разделяет периоды и сроки сдачи', async () => {
    const wrapper = await mountCalendar()
    http.get.mockClear()
    http.get.mockResolvedValue(response([
      { ...event(), extendedProps: { ...event().extendedProps, kind: 'deadline' } },
    ]))

    wrapper.vm.setKind('deadline')
    await tick()

    expect(http.get).toHaveBeenCalledWith('/api/calendar', expect.objectContaining({
      params: expect.objectContaining({ kind: 'deadline' }),
    }))
    expect(wrapper.vm.query.kind).toBe('deadline')
  })

  it('подпись активного фильтра типа переведена, а не сырым ключом', async () => {
    // Ключи плоские (calendar.kindDeadline). Обращение по вложенному пути
    // (calendar.kind.deadline) показывало пользователю «calendar.kind.deadline».
    const wrapper = await mountCalendar()
    wrapper.vm.query.kind = 'deadline'
    await wrapper.vm.$nextTick()

    const labels = wrapper.vm.activeFilterLabels.join(' ')
    expect(labels).not.toMatch(/calendar\./i)
    expect(labels).toContain(wrapper.vm.$t('calendar.kindDeadline'))
  })

  it('метка срока сдачи сохраняет подпись с бэкенда', async () => {
    const wrapper = await mountCalendar()

    // Бэкенд присылает готовую подпись «Срок сдачи: …». Раньше она
    // затиралась собранной из course/module, и метка выглядела как ещё
    // одна полоса периода.
    const withPrefix = {
      ...event(),
      title: 'Срок сдачи: Курс А · Пожар',
      extendedProps: { ...event().extendedProps, kind: 'deadline' },
    }
    expect(wrapper.vm.eventLabel(withPrefix)).toBe('Срок сдачи: Курс А · Пожар')
  })

  it('события получают класс по типу для разной заливки', async () => {
    const wrapper = await mountCalendar()

    expect(wrapper.vm.events[0].classNames).toContain('cal-event--period')

    http.get.mockResolvedValue(response([
      event({ extendedProps: { ...event().extendedProps, kind: 'deadline' } }),
    ]))
    await wrapper.vm.load()
    expect(wrapper.vm.events[0].classNames).toContain('cal-event--deadline')
  })

  it('сброс очищает и фильтр типа', async () => {
    const wrapper = await mountCalendar()
    wrapper.vm.query.kind = 'deadline'
    wrapper.vm.query.status = 'active'
    await tick()

    wrapper.vm.resetFilters()
    await tick()

    expect(wrapper.vm.query.kind).toBeNull()
    expect(wrapper.vm.hasFilters).toBe(false)
  })

  it('формат даты в диалоге соответствует периоду', async () => {
    const wrapper = await mountCalendar()

    wrapper.vm.selected = event().extendedProps
    wrapper.vm.detail = true
    await wrapper.vm.$nextTick()

    const dialogText = wrapper.text()
    expect(dialogText).toContain('02.10.2026')
    expect(dialogText).toContain('11.10.2026')
  })
})

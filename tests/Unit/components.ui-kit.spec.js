// @vitest-environment jsdom
/**
 * Дизайн-система 2.0 (Фаза 1.3): смоук-проверка новых примитивов.
 *
 * Каждый компонент монтируется с минимальными входными данными и
 * должен отрисоваться без предупреждений о неизвестных компонентах.
 * Смысл не в проверке вёрстки, а в том, что SFC компилируется и не
 * тянет за собой несуществующие зависимости: собрать «мёртвый»
 * компонент, который никто не импортирует, обычная сборка Vite не
 * даёт, и ошибка всплыла бы только при первом использовании.
 */
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import AppSkeleton from '../../resources/js/components/ui/AppSkeleton.vue'
import FormField from '../../resources/js/components/ui/FormField.vue'
import SectionCard from '../../resources/js/components/ui/SectionCard.vue'
import StatCard from '../../resources/js/components/ui/StatCard.vue'
import ProgressBarRing from '../../resources/js/components/ui/ProgressBarRing.vue'
import AvatarGroup from '../../resources/js/components/ui/AvatarGroup.vue'
import StepperShell from '../../resources/js/components/ui/StepperShell.vue'
import TabsForm from '../../resources/js/components/ui/TabsForm.vue'
import DropzoneUpload from '../../resources/js/components/ui/DropzoneUpload.vue'
import CommandPalette from '../../resources/js/components/ui/CommandPalette.vue'
import OnboardingTour from '../../resources/js/components/ui/OnboardingTour.vue'
import Sparkline from '../../resources/js/components/ui/charts/Sparkline.vue'
import LineChart from '../../resources/js/components/ui/charts/LineChart.vue'
import BarChart from '../../resources/js/components/ui/charts/BarChart.vue'
import Heatmap from '../../resources/js/components/ui/charts/Heatmap.vue'

// Компоненты графиков измеряют ширину через ResizeObserver, которого
// в jsdom нет — без заглушки падает любой из них.
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

// VOverlay (v-dialog) в Vuetify 3 читает visualViewport при позиционировании.
// В jsdom объекта нет — заглушка нужна, чтобы CommandPalette монтировался.
if (!globalThis.visualViewport) {
  globalThis.visualViewport = {
    width: 1024,
    height: 768,
    offsetLeft: 0,
    offsetTop: 0,
    scale: 1,
    addEventListener() {},
    removeEventListener() {},
  }
}

const vuetify = createVuetify({
  components: vuetifyComponents,
  directives: vuetifyDirectives,
})

const i18n = createI18n({
  legacy: false,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru: {} },
})

const mountOptions = {
  global: {
    plugins: [vuetify, i18n],
    stubs: { transition: false },
  },
}

const steps = [
  { key: 'one', title: 'Первый', subtitle: 'шаг' },
  { key: 'two', title: 'Второй' },
]

describe('UI-примитивы Фазы 1.3 монтируются', () => {
  it('AppSkeleton: варианты table/cards/form/text', () => {
    for (const variant of ['table', 'cards', 'form', 'text']) {
      const wrapper = mount(AppSkeleton, { ...mountOptions, props: { variant, count: 2, columns: 3 } })
      expect(wrapper.find('.u-skeleton').exists()).toBe(true)
      wrapper.unmount()
    }
  })

  it('FormField: подпись, ошибка, счётчик', () => {
    const wrapper = mount(FormField, {
      ...mountOptions,
      props: { label: 'Название', error: 'Обязательное поле', counter: { current: 3, max: 10 } },
    })
    expect(wrapper.text()).toContain('Название')
    expect(wrapper.text()).toContain('Обязательное поле')
    expect(wrapper.text()).toContain('3 / 10')
    wrapper.unmount()
  })

  it('SectionCard: сворачиваемая секция', async () => {
    const wrapper = mount(SectionCard, {
      ...mountOptions,
      props: { title: 'Основное', collapsible: true, startCollapsed: false },
      slots: { default: '<p>тело</p>' },
    })
    expect(wrapper.find('.u-section__body').exists()).toBe(true)
    await wrapper.find('.u-section__toggle').trigger('click')
    expect(wrapper.classes()).toContain('u-section--collapsed')
    wrapper.unmount()
  })

  it('StatCard: значение, единица, тренд', () => {
    const wrapper = mount(StatCard, {
      ...mountOptions,
      props: { label: 'Студенты', value: 1200, suffix: 'чел.', trend: '+12%', tone: 'success' },
    })
    expect(wrapper.text()).toContain('Студенты')
    expect(wrapper.text()).toContain('+12%')
    wrapper.unmount()
  })

  it('ProgressBarRing: ограничивает значение 0..100', () => {
    const wrapper = mount(ProgressBarRing, { ...mountOptions, props: { value: 140 } })
    expect(wrapper.text()).toContain('100%')
    wrapper.unmount()
  })

  it('AvatarGroup: показывает «+N» при переполнении', () => {
    const people = Array.from({ length: 7 }, (_, i) => ({ id: i, name: `Иван ${i}` }))
    const wrapper = mount(AvatarGroup, { ...mountOptions, props: { people, max: 4 } })
    expect(wrapper.text()).toContain('+3')
    wrapper.unmount()
  })

  it('StepperShell: переход вперёд и назад', async () => {
    const wrapper = mount(StepperShell, {
      ...mountOptions,
      props: { steps },
      slots: { one: '<p>шаг 1</p>', two: '<p>шаг 2</p>' },
    })
    expect(wrapper.text()).toContain('шаг 1')
    await wrapper.findAll('button').find((b) => b.text().includes('Далее')).trigger('click')
    expect(wrapper.text()).toContain('шаг 2')
    wrapper.unmount()
  })

  it('StepperShell: beforeNext=false блокирует переход', async () => {
    const beforeNext = vi.fn(() => false)
    const wrapper = mount(StepperShell, {
      ...mountOptions,
      props: { steps, beforeNext },
      slots: { one: '<p>шаг 1</p>', two: '<p>шаг 2</p>' },
    })
    await wrapper.findAll('button').find((b) => b.text().includes('Далее')).trigger('click')
    expect(beforeNext).toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('шаг 2')
    wrapper.unmount()
  })

  it('TabsForm: переключение вкладок и метка ошибки', async () => {
    const tabs = [
      { key: 'main', title: 'Основное' },
      { key: 'extra', title: 'Дополнительно' },
    ]
    const wrapper = mount(TabsForm, {
      ...mountOptions,
      props: { tabs, errorKeys: ['extra'] },
      slots: { main: '<p>основное</p>', extra: '<p>доп</p>' },
    })
    expect(wrapper.find('.has-error').exists()).toBe(true)
    await wrapper.findAll('.u-tabs-form__tab')[1].trigger('click')
    expect(wrapper.text()).toContain('доп')
    wrapper.unmount()
  })

  it('DropzoneUpload: рисует очередь из переданного движка', () => {
    const queue = {
      tasks: [
        { id: 'a', name: 'file.pdf', size: 2048, sent: 1024, status: 'uploading', error: null },
        { id: 'b', name: 'doc.pdf', size: 4096, sent: 0, status: 'done', error: null },
      ],
      addAll: vi.fn(() => []),
      remove: vi.fn(),
    }
    const wrapper = mount(DropzoneUpload, { ...mountOptions, props: { queue } })
    expect(wrapper.text()).toContain('file.pdf')
    expect(wrapper.text()).toContain('doc.pdf')
    wrapper.unmount()
  })

  it('CommandPalette: нечёткий поиск и группировка', async () => {
    const commands = [
      { id: '1', title: 'Создать курс', group: 'Действия', keywords: 'course new' },
      { id: '2', title: 'Открыть экзамены', group: 'Навигация' },
    ]
    const wrapper = mount(CommandPalette, {
      ...mountOptions,
      attachTo: document.body,
      props: { modelValue: true, commands, enableHotkey: false },
    })
    // Контент v-dialog уезжает в оверлей (teleport) и появляется не в
    // первый тик — ждём отрисовку и смотрим на body, а не на wrapper.
    await nextTick()
    await nextTick()
    expect(document.body.textContent).toContain('Создать курс')
    expect(document.body.textContent).toContain('Действия')
    wrapper.unmount()
  })

  it('OnboardingTour: autoStart=false не открывается', () => {
    const wrapper = mount(OnboardingTour, {
      ...mountOptions,
      attachTo: document.body,
      props: { steps: [{ target: 'body', title: 'Шаг' }], autoStart: false, tourKey: 'test-tour' },
    })
    expect(document.querySelector('.u-tour')).toBeNull()
    wrapper.unmount()
  })
})

describe('ChartsKit монтируется', () => {
  it('Sparkline', () => {
    const wrapper = mount(Sparkline, { ...mountOptions, props: { data: [1, 3, 2, 5, 4] } })
    expect(wrapper.find('path').exists()).toBe(true)
    wrapper.unmount()
  })

  it('LineChart', () => {
    const wrapper = mount(LineChart, {
      ...mountOptions,
      props: {
        labels: ['Пн', 'Вт', 'Ср'],
        series: [
          { name: 'Прогресс', data: [1, 2, 3], area: true },
          { name: 'Цель', data: [2, 2, 2] },
        ],
      },
    })
    expect(wrapper.find('.u-chart__svg').exists()).toBe(true)
    expect(wrapper.text()).toContain('Прогресс')
    expect(wrapper.text()).toContain('Цель')
    wrapper.unmount()
  })

  it('BarChart', () => {
    const wrapper = mount(BarChart, {
      ...mountOptions,
      props: { labels: ['A', 'B'], series: [{ name: 'Сдачи', data: [3, 5] }] },
    })
    expect(wrapper.findAll('.u-chart__bar').length).toBe(2)
    wrapper.unmount()
  })

  it('Heatmap', () => {
    const wrapper = mount(Heatmap, {
      ...mountOptions,
      props: { data: [0, 1, 2, 3, 4], weekCount: 2 },
    })
    expect(wrapper.findAll('.u-heatmap__cell').length).toBeGreaterThan(0)
    wrapper.unmount()
  })
})

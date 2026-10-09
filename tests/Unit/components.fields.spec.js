// @vitest-environment jsdom
/**
 * Каскадные select (утилита) и поля Фазы 2.2: маска, повторяемые
 * строки, диапазон дат.
 */
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import { cascadeOptions, setCascadeValue, activeCascadeSteps } from '../../resources/js/utils/cascade'
import MaskedField from '../../resources/js/components/ui/fields/MaskedField.vue'
import RepeatableFields from '../../resources/js/components/ui/fields/RepeatableFields.vue'
import DateRangeField from '../../resources/js/components/ui/fields/DateRangeField.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, locale: 'ru', fallbackLocale: 'ru', messages: { ru: {} } })
const mountOptions = { global: { plugins: [vuetify, i18n] } }

describe('utils/cascade', () => {
  const steps = [
    { key: 'category', options: [{ id: 1, title: 'A' }] },
    { key: 'type', options: (values) => (values[0] === 1 ? [{ id: 10, title: 'A1' }] : []) },
  ]

  it('cascadeOptions поддерживает массив и функцию', () => {
    expect(cascadeOptions(steps[0], [])).toEqual([{ id: 1, title: 'A' }])
    expect(cascadeOptions(steps[1], [1])).toEqual([{ id: 10, title: 'A1' }])
    expect(cascadeOptions(steps[1], [2])).toEqual([])
  })

  it('setCascadeValue сбрасывает младшие уровни', () => {
    expect(setCascadeValue(steps, [1, 10], 0, 2)).toEqual([2, null])
    expect(setCascadeValue(steps, [1, 10], 1, 20)).toEqual([1, 20])
  })

  it('activeCascadeSteps открывает шаг после заполнения предыдущего', () => {
    expect(activeCascadeSteps(steps, [null, null])).toEqual([true, false])
    expect(activeCascadeSteps(steps, [1, null])).toEqual([true, true])
  })
})

describe('MaskedField', () => {
  it('форматирует значение по пресету', () => {
    const wrapper = mount(MaskedField, { ...mountOptions, props: { modelValue: '9991234567', preset: 'phone' } })
    expect(wrapper.find('input').element.value).toBe('+7 (999) 123-45-67')
    wrapper.unmount()
  })

  it('эмитит отформатированное значение при вводе', async () => {
    const wrapper = mount(MaskedField, { ...mountOptions, props: { modelValue: '', preset: 'date' } })
    await wrapper.find('input').setValue('31122024')
    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted.at(-1)).toEqual(['31.12.2024'])
    wrapper.unmount()
  })
})

describe('RepeatableFields', () => {
  const item = () => ({ id: undefined, text: '' })

  it('добавляет и удаляет строки', async () => {
    const wrapper = mount(RepeatableFields, {
      ...mountOptions,
      props: { modelValue: [item()], blank: () => ({ text: '' }) },
    })

    await wrapper.findAll('button').find((b) => b.text().includes('Добавить строку')).trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toHaveLength(2)

    const latest = wrapper.emitted('update:modelValue').at(-1)[0]
    await wrapper.setProps({ modelValue: latest })
    await wrapper.find('button[aria-label="Удалить строку"]').trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toHaveLength(1)

    wrapper.unmount()
  })

  it('дублирует строку без переноса id', async () => {
    const wrapper = mount(RepeatableFields, {
      ...mountOptions,
      props: { modelValue: [{ id: 7, text: 'вопрос' }], blank: () => ({ text: '' }) },
    })

    await wrapper.find('button[aria-label="Дублировать строку"]').trigger('click')
    const next = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(next).toHaveLength(2)
    expect(next[1].text).toBe('вопрос')
    expect(next[1].id).toBeUndefined()

    wrapper.unmount()
  })

  it('переставляет строку стрелкой с клавиатуры', async () => {
    const wrapper = mount(RepeatableFields, {
      ...mountOptions,
      props: { modelValue: [{ text: 'a' }, { text: 'b' }] },
    })

    await wrapper.findAll('.u-repeatable__handle')[0].trigger('keydown', { key: 'ArrowDown' })
    const next = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(next.map((i) => i.text)).toEqual(['b', 'a'])

    wrapper.unmount()
  })
})

describe('DateRangeField', () => {
  it('не даёт концу быть раньше начала', async () => {
    const wrapper = mount(DateRangeField, {
      ...mountOptions,
      props: { modelValue: { from: '2026-05-10', to: '2026-05-20' } },
    })

    const inputs = wrapper.findAll('input')
    await inputs[1].setValue('2026-05-01')
    const emitted = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(emitted.from).toBe('2026-05-01')
    expect(emitted.to).toBe('2026-05-01')

    wrapper.unmount()
  })
})

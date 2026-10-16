// @vitest-environment jsdom
/**
 * MultiSelectField: прокидывание выбранного значения наружу.
 *
 * Регрессия, найденная в браузере на конструкторе курса: опция в списке
 * отмечалась (aria-selected="true"), но значение не доходило до формы —
 * превью не показывало категорию, а шаг не пропускал валидацию.
 */
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'

import MultiSelectField from '../../resources/js/components/ui/fields/MultiSelectField.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const mountOptions = { global: { plugins: [vuetify] } }

const options = [
  { value: 1, label: 'Командир' },
  { value: 2, label: 'Оператор БЛА' },
]

describe('MultiSelectField', () => {
  it('эмитит выбранные значения наружу', async () => {
    const wrapper = mount(MultiSelectField, {
      ...mountOptions,
      attachTo: document.body,
      props: { modelValue: [], options, multiple: true },
    })

    await wrapper.find('.multiselect-search').trigger('click')
    await wrapper.find('.multiselect-dropdown .multiselect-option').trigger('click')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted, 'выбор опции должен эмитить значение').toBeTruthy()
    expect(emitted.at(-1)[0]).toEqual([1])

    wrapper.unmount()
  })

  it('получает значения извне и показывает их чипами', async () => {
    const wrapper = mount(MultiSelectField, {
      ...mountOptions,
      props: { modelValue: [2], options, multiple: true },
    })

    expect(wrapper.text()).toContain('Оператор БЛА')

    wrapper.unmount()
  })
})

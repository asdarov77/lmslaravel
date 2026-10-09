<template>
  <v-text-field
    :model-value="display"
    :placeholder="placeholder"
    :disabled="disabled"
    :clearable="clearable"
    :maxlength="maxLength"
    @update:model-value="onInput"
  />
</template>

<script setup>
import { computed } from 'vue'
import { applyMask, MASK_PRESETS } from '../../../utils/mask'

/**
 * Поле с маской ввода.
 *
 * Зачем: телефон/дата/ИНН вводились как свободный текст, и на бэкенд
 * уходило то, что набрали («8-999…», «31/12/2024»). Маска приводит
 * ввод к одному виду ещё в поле.
 *
 * `preset` — имя из MASK_PRESETS ('phone', 'date', 'inn', 'snils',
 * 'passport'), `mask` — собственный шаблон (9 — цифра, A — буква,
 * * — любой символ). Остальные атрибуты (label, rules и т.д.)
 * прокидываются на v-text-field автоматически.
 *
 * Значение наружу уходит в отформатированном виде; для «сырых» данных
 * есть unmask()/digitsOnly() из utils/mask.
 */
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  preset: { type: String, default: '' },
  mask: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  maxLength: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:modelValue'])

const pattern = computed(() => MASK_PRESETS[props.preset] || props.mask || '')
const display = computed(() => applyMask(props.modelValue, pattern.value))

const onInput = (value) => {
  emit('update:modelValue', applyMask(value, pattern.value))
}
</script>

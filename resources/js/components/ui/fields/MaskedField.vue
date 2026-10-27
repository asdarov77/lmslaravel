<template>
  <v-text-field
    :model-value="shown"
    :placeholder="placeholder"
    :disabled="disabled"
    :clearable="clearable"
    :maxlength="maxLength"
    @update:model-value="onInput"
  />
</template>

<script setup>
import { computed, ref, watch } from 'vue'
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
 * В модель уходит НЕ отформатированное значение, а «сырое»: в поле
 * видно «+7 (999) 000-00-00», а форма отправляет «+79990000000».
 *
 * Раньше наружу уходил отформатированный вид, и маска телефона
 * отправляла 17 символов в колонку varchar(16) — Postgres отвечал
 * «String data, right truncated», то есть форма падала с 500 на
 * ровном месте. Бэкенду нужны цифры, человеку — удобный ввод.
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

/**
 * Что видит человек в поле.
 *
 * Отдельная переменная, а не вычисление из modelValue: пока человек
 * набирает номер, значение в модели «сырое» и без разделителей, и
 * перерисовывать его по маске на каждый символ значило бы терять
 * позицию курсора.
 */
const shown = ref('')

watch(
  () => props.modelValue,
  (value) => {
    const masked = applyMask(value, pattern.value)

    if (masked !== shown.value) {
      shown.value = masked
    }
  },
  { immediate: true }
)

const onInput = (value) => {
  shown.value = applyMask(value, pattern.value)

  /*
   * Наружу — без разделителей: маска существует для ввода, а не для
   * хранения.
   *
   * Убираются ТОЛЬКО разделители (пробел, скобки, дефисы, точки).
   * unmask() по шаблону здесь не годится: литералы маски в нём теряются,
   * и номер «+7 (999) 000-00-00» превращался в «+9990000000» — без
   * кода страны. Плюс сохраняется: он часть номера.
   */
  emit('update:modelValue', shown.value.replace(/[^\d+]/g, '').replace(/^\++/, '+'))
}
</script>

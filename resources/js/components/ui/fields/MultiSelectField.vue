<template>
  <Multiselect
    class="u-multiselect"
    :model-value="modelValue"
    :options="options"
    :multiple="multiple"
    :searchable="searchable"
    :disabled="disabled"
    :clear-on-select="!multiple"
    :close-on-select="!multiple"
    :allow-empty="clearable"
    :placeholder="placeholder"
    :no-results-text="noResultsText"
    label="label"
    value-prop="value"
    group-label="group"
    group-values="values"
    @update:model-value="onUpdate"
    @blur="$emit('blur', $event)"
  />
</template>

<script setup>
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'

/**
 * Мультивыбор с чипами и группировкой.
 *
 * Зачем: @vueform/multiselect уже был в зависимостях, но жил «сам по
 * себе» — мимо темы и токенов. Здесь он завёрнут и перекрашен под
 * дизайн-систему, чтобы select с чипами был один на всё приложение.
 *
 * options — [{ value, label }] или [{ label, values: [...] }] для
 * группировки. Значение наружу: id или массив id.
 */
const props = defineProps({
  modelValue: { type: [Array, String, Number, null], default: null },
  options: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: true },
  searchable: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  placeholder: { type: String, default: '' },
  noResultsText: { type: String, default: 'Ничего не найдено' },
})

const emit = defineEmits(['update:modelValue', 'blur'])

/**
 * Приводим ответ библиотеки к контракту компонента: массив ЗНАЧЕНИЙ.
 *
 * Зачем: @vueform/multiselect в этой версии отдаёт объект опции
 * ({ value, label }) и одиночный объект вместо массива при multiple.
 * Наружу уходил объект, значение не доходило до формы — категория
 * «выбиралась», но поле оставалось пустым, и шаг не пропускал
 * валидацию. Форма работает со значениями (id), а не с объектами:
 * объекты нужны только для отображения.
 */
const onUpdate = (value) => {
  const raw = Array.isArray(value) ? value : value === null || value === undefined ? [] : [value]

  const list = raw.map((item) => {
    if (item && typeof item === 'object') return item.value ?? item.id ?? item.label ?? item

    return item
  })

  emit('update:modelValue', props.multiple ? list : list[0] ?? null)
}
</script>

<style scoped>
/* Приводим компонент к токенам темы: библиотека поставляет светлую
   палитру, которая в тёмном режиме выбивалась из интерфейса. */
.u-multiselect {
  --ms-font-size: var(--fs-base);
  --ms-line-height: var(--lh-normal);
  --ms-radius: var(--radius-md);
  --ms-border-color: var(--c-border);
  --ms-bg: var(--c-surface);
  --ms-color: var(--c-text);
  --ms-dropdown-border-color: var(--c-border);
  --ms-dropdown-bg: var(--c-surface);
  --ms-option-color: var(--c-text);
  --ms-option-bg-selected: var(--c-primary-soft);
  --ms-option-color-selected: var(--c-primary);
  --ms-option-bg-pointed: var(--c-surface-3);
  --ms-placeholder-color: var(--c-text-muted);
  --ms-spinner-color: var(--c-accent);
  --ms-ring-color: var(--c-accent);
}
</style>

<template>
  <div class="u-daterange">
    <v-text-field
      type="date"
      :model-value="from"
      :label="fromLabel"
      :max="to || undefined"
      :disabled="disabled"
      @update:model-value="update('from', $event)"
    />
    <span class="u-daterange__sep" aria-hidden="true">—</span>
    <v-text-field
      type="date"
      :model-value="to"
      :label="toLabel"
      :min="from || undefined"
      :disabled="disabled"
      @update:model-value="update('to', $event)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Диапазон дат (назначения, зачисления, отчёты).
 *
 * Раньше начало и конец вводились двумя разрозненными полями без
 * взаимных ограничений: можно было поставить конец раньше начала, и на
 * бэкенд уходил пустой результат. Здесь поля связаны через min/max, а
 * значение — объект { from, to } (ISO yyyy-mm-dd).
 */
const props = defineProps({
  modelValue: { type: Object, default: () => ({ from: '', to: '' }) },
  fromLabel: { type: String, default: 'С' },
  toLabel: { type: String, default: 'По' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const from = computed(() => props.modelValue?.from ?? '')
const to = computed(() => props.modelValue?.to ?? '')

const update = (key, value) => {
  const next = { from: from.value, to: to.value }
  next[key] = value || ''

  // Конец не может быть раньше начала: подрезаем, а не оставляем
  // пользователю «пустой» диапазон, на который бэкенд вернёт ноль строк.
  if (key === 'from' && next.to && next.from && next.to < next.from) next.to = next.from
  if (key === 'to' && next.from && next.to && next.to < next.from) next.from = next.to

  emit('update:modelValue', { from: next.from, to: next.to })
}
</script>

<style scoped>
.u-daterange {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.u-daterange__sep {
  color: var(--c-text-muted);
}

.u-daterange :deep(.v-field) {
  min-width: 160px;
}
</style>

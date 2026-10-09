<template>
  <div class="u-cascade">
    <v-select
      v-for="(step, index) in steps"
      :key="step.key || index"
      :model-value="values[index]"
      :items="available[index]"
      :item-title="step.itemTitle || 'title'"
      :item-value="step.itemValue || 'id'"
      :label="step.label"
      :placeholder="step.placeholder || ''"
      :disabled="disabled || !enabled[index]"
      :clearable="clearable && index > 0"
      class="u-cascade__step"
      @update:model-value="onChange(index, $event)"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { activeCascadeSteps, cascadeOptions, setCascadeValue } from '../../../utils/cascade'

/**
 * Каскадный выбор (категория → тип → aircraft).
 *
 * Зачем: до этого цепочка собиралась в каждой форме руками, и при
 * смене родителя дочернее значение не сбрасывалось — уходила
 * несогласованная пара id, на которую бэкенд отвечал 422.
 *
 * `steps` — массив: { key, label, options: array|(values)=>array }.
 * Значение наружу — массив выбранных id по уровням.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  steps: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue'])

const values = ref(
  props.steps.map((_, index) => (props.modelValue[index] !== undefined ? props.modelValue[index] : null))
)

// Синхронизация снаружи (например, при загрузке сущности на edit).
watch(
  () => props.modelValue,
  (next) => {
    const normalized = props.steps.map((_, index) => (next[index] !== undefined ? next[index] : null))
    if (JSON.stringify(normalized) !== JSON.stringify(values.value)) values.value = normalized
  },
  { deep: true }
)

const enabled = computed(() => activeCascadeSteps(props.steps, values.value))

const available = computed(() =>
  props.steps.map((step, index) => (enabled.value[index] ? cascadeOptions(step, values.value) : []))
)

const onChange = (index, value) => {
  values.value = setCascadeValue(props.steps, values.value, index, value)
  emit('update:modelValue', values.value.slice())
}
</script>

<style scoped>
.u-cascade {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

@media (min-width: 960px) {
  .u-cascade__step {
    max-width: var(--w-form);
  }
}
</style>

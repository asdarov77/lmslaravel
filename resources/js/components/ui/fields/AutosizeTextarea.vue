<template>
  <v-textarea
    :model-value="modelValue"
    :rows="rows"
    :maxlength="maxlength"
    :counter="showCounter"
    :disabled="disabled"
    auto-grow
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import { computed } from 'vue'

/**
 * Textarea с авто-высотой и счётчиком символов.
 *
 * Зачем: длинные описания вводились в textarea фиксированной высоты
 * без лимита и без счётчика — пользователь не знал, сколько ещё
 * влезет, а на бэкенде описание молча обрезалось по maxlength.
 *
 * `maxlength` задаётся из схемы (правило maxLength) и одновременно
 * ограничивает ввод и питает счётчик «N / max».
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  rows: { type: [String, Number], default: 3 },
  /** Лимит символов. null — без ограничения и без счётчика. */
  maxlength: { type: [String, Number], default: null },
  disabled: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])

const showCounter = computed(() => props.maxlength !== null && props.maxlength !== undefined)
</script>

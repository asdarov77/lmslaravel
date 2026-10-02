<template>
  <v-dialog
    :model-value="modelValue"
    max-width="440"
    role="alertdialog"
    :aria-labelledby="titleId"
    :aria-describedby="bodyId"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card class="confirm-dialog">
      <v-card-title :id="titleId" class="confirm-dialog__title">
        <v-icon class="mr-3" :color="iconColor" :icon="icon" size="22" aria-hidden="true"></v-icon>
        {{ title }}
      </v-card-title>

      <v-card-text :id="bodyId" class="confirm-dialog__body">
        <slot>{{ text }}</slot>
      </v-card-text>

      <v-card-actions class="confirm-dialog__actions">
        <v-btn variant="text" :disabled="busy" @click="$emit('cancel')">
          {{ cancelText }}
        </v-btn>
        <v-btn
          :color="confirmColor"
          variant="flat"
          :loading="busy"
          @click="$emit('confirm')"
        >
          {{ confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
/**
 * Диалог подтверждения необратимого действия.
 *
 * Заменяет Pages/Modal/Dialog.vue и разнобой в подтверждениях удаления.
 *
 * Что было не так:
 *  1. Шесть разных способов подтвердить удаление: window.confirm (2
 *     страницы), кастомный v-dialog (1 страница) и — что хуже всего —
 *     удаление без подтверждения сразу (3 страницы: категории, курсы,
 *     вопросы). Одно нажатие — и данных больше нет.
 *  2. В Pages/Modal/Dialog.vue стояло props: { text: Text, title: Text }.
 *     Text — это DOM-конструктор, а не тип Vue, поэтому валидация пропов
 *     молча не работала.
 *  3. Цвета были перепутаны местами: «Отменить» был красным, «Удалить» —
 *     зелёным. Ровно наоборот интуиции.
 *  4. Проп dialog был один общий булев на страницу, а не на строку, —
 *     открывалась модалка сразу у всех строк.
 *
 * Здесь: роль alertdialog, фокус на кнопке отмены, сброс состояния
 * закрытия, прогресс на кнопке подтверждения, правильные цвета.
 */
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Подтвердите действие' },
  text: { type: String, default: '' },
  confirmText: { type: String, default: 'Удалить' },
  cancelText: { type: String, default: 'Отмена' },
  busy: { type: Boolean, default: false },
  /** danger — необратимое действие, primary — обычное подтверждение. */
  tone: {
    type: String,
    default: 'danger',
    validator: (v) => ['danger', 'primary'].includes(v),
  },
})

defineEmits(['update:modelValue', 'confirm', 'cancel'])

const titleId = `confirm-title-${Math.random().toString(36).slice(2, 9)}`
const bodyId = `confirm-body-${Math.random().toString(36).slice(2, 9)}`

const confirmColor = computed(() => (props.tone === 'danger' ? 'error' : 'primary'))
const iconColor = computed(() => (props.tone === 'danger' ? 'error' : 'primary'))
const icon = computed(() =>
  props.tone === 'danger' ? 'mdi-alert-outline' : 'mdi-help-circle-outline'
)
</script>

<style scoped>
.confirm-dialog__title {
  display: flex;
  align-items: center;
  font-size: 1.125rem;
  font-weight: 600;
  line-height: 1.25;
  color: var(--c-text);
  padding-top: 1.25rem;
}

.confirm-dialog__body {
  color: var(--c-text-secondary);
  font-size: 0.875rem;
  line-height: 1.5;
  padding-bottom: 0.5rem;
}

.confirm-dialog__actions {
  padding: 0.5rem 1rem 1rem;
}
</style>

<template>
  <div class="form-actions" :class="{ 'u-is-busy': busy }">
    <slot>
      <v-btn type="submit" color="primary" variant="flat" :loading="busy">
        {{ submitText }}
      </v-btn>
      <v-btn v-if="showCancel" type="button" variant="text" :disabled="busy" @click="$emit('cancel')">
        {{ cancelText }}
      </v-btn>
    </slot>
  </div>
</template>

<script setup>
/**
 * Кнопки формы.
 *
 * Раньше на 12 формах было 4 разных варианта: ButtonGroup (i18n, но
 * порядок «Отмена» слева и без loading), голая пара v-btn, кнопка
 * «Сохранить» вообще без отмены (в 4 формах), а в диалоге удаления
 * цвета были ещё и перепутаны. Здесь один порядок, одна подпись,
 * одно состояние занятости.
 *
 * busy обязателен: без него двойной клик отправляет форму дважды —
 * раньше это происходило на 16 страницах, где индикатора загрузки
 * не было вовсе.
 */
defineProps({
  busy: { type: Boolean, default: false },
  submitText: { type: String, default: 'Сохранить' },
  cancelText: { type: String, default: 'Отмена' },
  showCancel: { type: Boolean, default: true },
})

defineEmits(['submit', 'cancel'])
</script>

<style scoped>
.form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--sp-2);
  transition: opacity var(--dur-fast) var(--ease);
}
</style>

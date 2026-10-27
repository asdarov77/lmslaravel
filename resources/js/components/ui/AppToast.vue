<template>
  <v-snackbar
    :model-value="modelValue"
    :color="color"
    :timeout="timeout"
    location="bottom right"
    :aria-live="type === 'error' ? 'assertive' : 'polite'"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="d-flex align-center">
      <v-icon class="mr-3" :icon="icon" size="20" aria-hidden="true"></v-icon>
      <span class="app-toast__text">{{ text }}</span>
    </div>

    <template #actions>
      <v-btn
        variant="text"
        size="small"
        :aria-label="$t('common.close')"
        @click="$emit('update:modelValue', false)"
      >
        {{ $t("common.close") }}
      </v-btn>
    </template>
  </v-snackbar>
</template>

<script setup>
/**
 * Уведомление о результате действия.
 *
 * Заменяет Pages/Popup.vue.
 *
 * Что было не так:
 *  1. Popup рисовался через v-overlay — то есть полноэкранная
 *     полупрозрачная шторка поверх всего приложения на 3 секунды после
 *     каждого сохранения. Это не «уведомление», это блокировка работы.
 *  2. В Popup.vue был <style scooped> — с опечаткой. Vue не счёл блок
 *     scoped, и правило `.v-overlay { display:flex; top:50% }` утекло
 *     на ВСЕ оверлеи приложения, включая диалоги и выпадающие меню.
 *  3. Никакой роли для скринридера: assertive не на ошибке, polite
 *     на успехе.
 *
 * Теперь это настоящий тост в углу, не перекрывающий интерфейс,
 * с ролью для озвучивания и корректным таймаутом.
 */
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  text: { type: String, default: '' },
  type: {
    type: String,
    default: 'success',
    validator: (v) => ['success', 'error', 'warning', 'info'].includes(v),
  },
  timeout: { type: Number, default: 4000 },
})

defineEmits(['update:modelValue'])

const ICONS = {
  success: 'mdi-check-circle-outline',
  error: 'mdi-alert-circle-outline',
  warning: 'mdi-alert-outline',
  info: 'mdi-information-outline',
}

const icon = computed(() => ICONS[props.type] ?? ICONS.info)
const color = computed(() => props.type)
</script>

<style scoped>
.app-toast__text {
  font-size: 0.875rem;
  line-height: 1.4;
}
</style>

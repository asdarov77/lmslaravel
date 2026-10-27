<template>
  <div class="form-actions" :class="{ 'u-is-busy': busy }">
    <slot>
      <v-btn type="submit" color="primary" variant="flat" :loading="busy">
        {{ submitLabel }}
      </v-btn>
      <v-btn v-if="showCancel" type="button" variant="text" :disabled="busy" @click="$emit('cancel')">
        {{ cancelLabel }}
      </v-btn>
    </slot>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  busy: { type: Boolean, default: false },
  submitText: { type: String, default: '' },
  cancelText: { type: String, default: '' },
  showCancel: { type: Boolean, default: true },
})

const { t: $t } = useI18n()

const submitLabel = computed(() => props.submitText || $t('save'))
const cancelLabel = computed(() => props.cancelText || $t('cancel'))

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
<template>
  <div class="u-tabs-form">
    <div class="u-tabs-form__bar" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        role="tab"
        class="u-tabs-form__tab"
        :class="{ 'is-active': tab.key === activeKey, 'has-error': errorKeys.includes(tab.key) }"
        :aria-selected="tab.key === activeKey"
        @click="activeKey = tab.key"
      >
        <v-icon v-if="tab.icon" :icon="tab.icon" size="18" aria-hidden="true" />
        <span>{{ tab.title }}</span>
        <span v-if="badges[tab.key]" class="u-tabs-form__badge">{{ badges[tab.key] }}</span>
        <v-icon
          v-if="errorKeys.includes(tab.key)"
          class="u-tabs-form__error-dot"
          icon="mdi-alert-circle"
          size="16"
          aria-hidden="true"
        />
      </button>
    </div>

    <div class="u-tabs-form__panel" role="tabpanel">
      <slot :name="activeKey" :tab="activeTab">
        <slot :tab="activeTab" />
      </slot>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

/**
 * Форма с вкладками.
 *
 * «Пользователь», «Курс», «Настройки» — это длинные формы с разными
 * по смыслу группами полей. Показывать их простыней плохо, а делать
 * отдельные страницы — терять контекст. Вкладки делят форму, но
 * оставляют её одной формой: одни submit, один dirty-guard, одно
 * место для ошибок.
 *
 * Вкладка с ошибкой помечается иконкой: если валидация провалилась
 * на скрытой вкладке, пользователь должен это увидеть, а не гадать,
 * почему форма не отправляется.
 */
const props = defineProps({
  /** [{ key, title, icon }] */
  tabs: { type: Array, required: true },
  modelValue: { type: String, default: '' },
  /**
   * Ключи вкладок, содержащих ошибки валидации. Так пользователь
   * видит, куда вернуться.
   */
  errorKeys: { type: Array, default: () => [] },
  /** Счётчики у вкладок: { [key]: number } */
  badges: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'change'])

const activeKey = ref(props.modelValue || props.tabs[0]?.key)

watch(() => props.modelValue, (v) => {
  if (v) activeKey.value = v
})

watch(activeKey, (v) => {
  emit('update:modelValue', v)
  emit('change', v)
})

const activeTab = computed(() => props.tabs.find((t) => t.key === activeKey.value) ?? props.tabs[0])
</script>

<style scoped>
.u-tabs-form {
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
  overflow: hidden;
}

.u-tabs-form__bar {
  display: flex;
  align-items: stretch;
  gap: var(--sp-1);
  padding: var(--sp-2) var(--sp-3) 0;
  border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-2);
  overflow-x: auto;
}

.u-tabs-form__tab {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-3);
  border: none;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: var(--c-text-secondary);
  font: inherit;
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  white-space: nowrap;
  cursor: pointer;
  transition: color var(--dur-fast) var(--ease), border-color var(--dur-fast) var(--ease);
}

.u-tabs-form__tab:hover {
  color: var(--c-text);
}

.u-tabs-form__tab.is-active {
  color: var(--c-primary);
  border-bottom-color: var(--c-primary);
}

.u-tabs-form__tab.has-error {
  color: var(--c-danger);
}

.u-tabs-form__tab.has-error.is-active {
  border-bottom-color: var(--c-danger);
}

.u-tabs-form__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: var(--radius-pill);
  background: var(--c-surface-3);
  color: var(--c-text-secondary);
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
}

.u-tabs-form__panel {
  padding: var(--sp-5);
}
</style>

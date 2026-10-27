<template>
  <section class="u-section" :class="{ 'u-section--collapsed': isCollapsed }">
    <header class="u-section__head">
      <button
        v-if="collapsible"
        type="button"
        class="u-section__toggle"
        :aria-expanded="!isCollapsed"
        :aria-controls="bodyId"
        @click="toggle"
      >
        <v-icon class="u-section__chevron" icon="mdi-chevron-down" size="20" aria-hidden="true" />
        <span class="u-section__head-text">
          <span class="u-section__title">{{ title }}</span>
          <span v-if="subtitle" class="u-section__subtitle">{{ subtitle }}</span>
        </span>
      </button>

      <div v-else class="u-section__head-text">
        <h2 class="u-section__title">{{ title }}</h2>
        <p v-if="subtitle" class="u-section__subtitle">{{ subtitle }}</p>
      </div>

      <div v-if="$slots.actions" class="u-section__actions">
        <slot name="actions" />
      </div>
    </header>

    <div v-show="!isCollapsed" :id="bodyId" class="u-section__body">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { ref, useId } from 'vue'

/**
 * Секция формы — с заголовком и, если нужно, сворачиваемая.
 *
 * Длинные формы (курс, настройки, права) раньше шли сплошным
 * полотном: 30–40 полей подряд, в которых легко потеряться. Секции
 * дают оглавление и позволяют свернуть неактуальное: администратор,
 * меняющий только расписание, не прокручивает мимо обложки, описания
 * и категорий.
 *
 * По умолчанию секция развёрнута: сворачивание — помощь, а не
 * навязанное поведение.
 */
const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  collapsible: { type: Boolean, default: false },
  /** Начальное состояние, если collapsible. */
  startCollapsed: { type: Boolean, default: false },
})

const isCollapsed = ref(props.collapsible && props.startCollapsed)
const bodyId = `section-${useId()}`

function toggle() {
  isCollapsed.value = !isCollapsed.value
}
</script>

<style scoped>
.u-section {
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
  overflow: hidden;
}

.u-section + .u-section {
  margin-top: var(--sp-4);
}

.u-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
}

.u-section--collapsed .u-section__head {
  border-bottom: none;
}

.u-section__head:has(+ .u-section__body) {
  border-bottom: 1px solid var(--c-border);
}

.u-section__toggle {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.u-section__chevron {
  color: var(--c-text-muted);
  transition: transform var(--dur-base) var(--ease);
  flex-shrink: 0;
}

.u-section--collapsed .u-section__chevron {
  transform: rotate(-90deg);
}

.u-section__head-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.u-section__title {
  font-size: var(--fs-md);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
}

.u-section__subtitle {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
}

.u-section__actions {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  flex-shrink: 0;
}

.u-section__body {
  padding: var(--sp-5);
  border-top: 1px solid var(--c-border);
}
</style>

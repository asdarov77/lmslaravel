<template>
  <span class="u-brand" data-test="app-brand">
    <!--
        Знак рисуется цветом currentColor, а не зашитым: он должен
        одинаково читаться и на светлой, и на тёмной теме, где primary
        становится светлым.
    -->
    <svg
      class="u-brand__mark"
      viewBox="0 0 32 32"
      aria-hidden="true"
      focusable="false"
    >
      <rect class="u-brand__bg" x="0" y="0" width="32" height="32" rx="8" />
      <!-- Учебная траектория: шеврон вверх и базовая черта. -->
      <path
        class="u-brand__glyph"
        d="M9 20.5 16 13l7 7.5"
        fill="none"
        stroke-width="2.6"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <path
        class="u-brand__glyph"
        d="M9 24.5h14"
        fill="none"
        stroke-width="2.6"
        stroke-linecap="round"
      />
    </svg>

    <span class="u-brand__name">
      <span class="u-brand__short">{{ short }}</span>
      <span v-if="showFull" class="u-brand__full">{{ full }}</span>
    </span>
  </span>
</template>

<script setup>
/**
 * Знак и название системы.
 *
 * Раньше в шапке была просто ссылка с текстом `app.title` («Система
 * управления обучением»): на 300-пиксельном меню длинное название
 * съедало место, а на мобильном обрезалось. Теперь короткая форма идёт
 * всегда, полная — только там, где есть ширина.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n({ useScope: 'global' })

const props = defineProps({
  /** Показывать ли полное название (нужна ширина). */
  full: { type: Boolean, default: false },
})

const short = computed(() => t('app.brand'))
const full = computed(() => t('app.title'))
const showFull = computed(() => props.full)
</script>

<style scoped>
.u-brand {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
  color: inherit;
}

.u-brand__mark {
  flex: none;
  width: 28px;
  height: 28px;
}

.u-brand__bg {
  fill: var(--c-primary);
}

.u-brand__glyph {
  stroke: var(--c-on-primary);
}

.u-brand__name {
  display: inline-flex;
  align-items: baseline;
  gap: var(--sp-2);
  min-width: 0;
  font-weight: 600;
  line-height: 1.2;
}

.u-brand__short {
  white-space: nowrap;
}

.u-brand__full {
  color: var(--c-text-secondary);
  font-weight: 400;
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/*
   На узких экранах полное название убираем: важнее, чтобы в шапке
   поместились поиск и меню пользователя.
*/
@media (max-width: 1100px) {
  .u-brand__full {
    display: none;
  }
}
</style>

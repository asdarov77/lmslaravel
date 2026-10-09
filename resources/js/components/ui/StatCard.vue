<template>
  <div class="u-stat" :class="`u-stat--${tone}`">
    <div class="u-stat__top">
      <span class="u-stat__label">{{ label }}</span>
      <v-icon v-if="icon" class="u-stat__icon" :icon="icon" size="20" aria-hidden="true" />
    </div>

    <div class="u-stat__value-row">
      <span class="u-stat__value">{{ formattedValue }}</span>
      <span v-if="suffix" class="u-stat__suffix">{{ suffix }}</span>

      <span v-if="trend" class="u-stat__trend" :class="`u-stat__trend--${trendDirection}`">
        <v-icon :icon="trendIcon" size="16" aria-hidden="true" />
        {{ trend }}
      </span>
    </div>

    <div v-if="$slots.sparkline || $slots.default" class="u-stat__foot">
      <slot name="sparkline">
        <slot />
      </slot>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * KPI-карточка: крупная цифра, подпись, тренд и место под спарклайн.
 *
 * На дашбордах «активные студенты», «средний балл», «просрочено»
 * раньше были просто строками текста без акцента: цифра не
 * выделялась из подписи, непонятно, растёт показатель или падает.
 * Здесь число — главный элемент, а тренд задаётся знаком: «+12%»
 * зелёным, «−8%» красным.
 *
 * tone управляет цветом акцента всей карточки: neutral (по умолчанию),
 * success, warning, danger.
 */
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  /** Единица после числа: «%», «ч», «чел.» */
  suffix: { type: String, default: '' },
  icon: { type: String, default: '' },
  /**
   * Тренд в виде готовой строки: «+12%», «−3». Отрицательный знак —
   * дефис U+2212, не дефис-перенос. Направление определяется по
   * первому символу.
   */
  trend: { type: String, default: '' },
  /** neutral | success | warning | danger */
  tone: { type: String, default: 'neutral' },
})

const formattedValue = computed(() =>
  typeof props.value === 'number' ? props.value.toLocaleString('ru-RU') : props.value,
)

const trendDirection = computed(() => {
  const t = props.trend.trim()
  if (!t) return 'flat'
  if (t.startsWith('+')) return 'up'
  if (t.startsWith('-') || t.startsWith('−')) return 'down'
  return 'flat'
})

const trendIcon = computed(() => {
  if (trendDirection.value === 'up') return 'mdi-trending-up'
  if (trendDirection.value === 'down') return 'mdi-trending-down'
  return 'mdi-minus'
})
</script>

<style scoped>
.u-stat {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
  transition: box-shadow var(--dur-base) var(--ease), transform var(--dur-base) var(--ease);
}

.u-stat:hover {
  box-shadow: var(--shadow-2);
}

.u-stat__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
}

.u-stat__label {
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--c-text-secondary);
}

.u-stat__icon {
  color: var(--c-text-muted);
}

.u-stat__value-row {
  display: flex;
  align-items: baseline;
  gap: var(--sp-2);
  flex-wrap: wrap;
}

.u-stat__value {
  font-size: var(--fs-3xl);
  line-height: 1;
  font-weight: var(--fw-bold);
  letter-spacing: -0.02em;
  color: var(--c-text);
  font-variant-numeric: tabular-nums;
}

.u-stat__suffix {
  font-size: var(--fs-md);
  font-weight: var(--fw-medium);
  color: var(--c-text-muted);
}

.u-stat__trend {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  margin-left: auto;
  padding: 2px var(--sp-2);
  border-radius: var(--radius-pill);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  font-variant-numeric: tabular-nums;
}

.u-stat__trend--up {
  background: var(--c-success-soft);
  color: var(--c-success);
}

.u-stat__trend--down {
  background: var(--c-danger-soft);
  color: var(--c-danger);
}

.u-stat__trend--flat {
  background: var(--c-surface-3);
  color: var(--c-text-secondary);
}

.u-stat__foot {
  margin-top: var(--sp-1);
}

/* Тональные акценты: цветная полоса слева, чтобы ряд KPI читался
   как светофор, а не как одинаковые серые плитки. */
.u-stat--success { border-left: 3px solid var(--c-success); }
.u-stat--warning { border-left: 3px solid var(--c-warning); }
.u-stat--danger  { border-left: 3px solid var(--c-danger); }
</style>

<template>
  <div class="u-ring" :style="ringStyle" role="img" :aria-label="ariaLabel">
    <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" class="u-ring__svg">
      <circle
        class="u-ring__track"
        :cx="center"
        :cy="center"
        :r="radius"
        fill="none"
        :stroke-width="stroke"
      />
      <circle
        class="u-ring__bar"
        :cx="center"
        :cy="center"
        :r="radius"
        fill="none"
        :stroke-width="stroke"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        :stroke-linecap="rounded ? 'round' : 'butt'"
        transform-origin="center"
        :transform="`rotate(-90 ${center} ${center})`"
      />
    </svg>

    <div class="u-ring__label">
      <slot>
        <span class="u-ring__value">{{ clamped }}%</span>
        <span v-if="caption" class="u-ring__caption">{{ caption }}</span>
      </slot>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Кольцо прогресса.
 *
 * В курсе, экзамене и на дашборде процент прохождения показывался
 * линейным v-progress-linear или просто числом. Кольцо компактнее
 * (влезает в карточку урока и в hero-строку «продолжить обучение»)
 * и сразу читается глазом, даже без цифры.
 *
 * Рисуется на SVG, а не на CSS conic-gradient: обычный gradient не
 * умеет скруглённые концы и даёт артефакт на стыке 0/100%.
 */
const props = defineProps({
  value: { type: Number, default: 0 },
  size: { type: Number, default: 96 },
  stroke: { type: Number, default: 8 },
  rounded: { type: Boolean, default: true },
  caption: { type: String, default: '' },
  /** success | warning | danger | accent — цвет дуги. */
  tone: { type: String, default: 'accent' },
})

const clamped = computed(() => Math.max(0, Math.min(100, Math.round(props.value))))
const center = computed(() => props.size / 2)
const radius = computed(() => props.size / 2 - props.stroke / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const dashOffset = computed(() => circumference.value * (1 - clamped.value / 100))

const toneColor = computed(() => {
  if (props.tone === 'success') return 'var(--c-success)'
  if (props.tone === 'warning') return 'var(--c-warning)'
  if (props.tone === 'danger') return 'var(--c-danger)'
  return 'var(--c-accent)'
})

const ringStyle = computed(() => ({
  '--ring-color': toneColor.value,
}))

const ariaLabel = computed(() => `Прогресс ${clamped.value}%`)
</script>

<style scoped>
.u-ring {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.u-ring__svg {
  display: block;
}

.u-ring__track {
  stroke: var(--c-surface-3);
}

.u-ring__bar {
  stroke: var(--ring-color, var(--c-accent));
  transition: stroke-dashoffset var(--dur-slow) var(--ease-out);
}

.u-ring__label {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  text-align: center;
}

.u-ring__value {
  font-size: var(--fs-lg);
  font-weight: var(--fw-bold);
  color: var(--c-text);
  font-variant-numeric: tabular-nums;
}

.u-ring__caption {
  font-size: var(--fs-2xs);
  color: var(--c-text-muted);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wide);
}
</style>

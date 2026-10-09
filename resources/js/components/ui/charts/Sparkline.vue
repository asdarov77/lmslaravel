<template>
  <svg
    class="u-spark"
    :width="width"
    :height="height"
    :viewBox="`0 0 ${width} ${height}`"
    preserveAspectRatio="none"
    role="img"
    :aria-label="ariaLabel"
  >
    <defs>
      <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="color" stop-opacity="0.22" />
        <stop offset="100%" :stop-color="color" stop-opacity="0" />
      </linearGradient>
    </defs>

    <path v-if="area" :d="areaPath" :fill="`url(#${gradId})`" />
    <path
      :d="linePath"
      fill="none"
      :stroke="color"
      :stroke-width="strokeWidth"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <circle v-if="showLast" :cx="last.x" :cy="last.y" :r="2.5" :fill="color" />
  </svg>
</template>

<script setup>
import { computed, useId } from 'vue'

/**
 * Спарклайн — миниатюрный график тренда для KPI-карточки.
 *
 * На дашборде нужен не точный график, а форма кривой: «растёт»,
 * «падает», «сезонные колебания». Полноценный график для этого
 * избыточен и не влезает в карточку, поэтому здесь только линия без
 * осей и подписей.
 *
 * Рисуется на SVG без внешней библиотеки: 20 точек не стоят того,
 * чтобы тянуть движок графиков в бандл (см. ChartsKit).
 */
const props = defineProps({
  /** Числовой ряд. */
  data: { type: Array, default: () => [] },
  width: { type: Number, default: 120 },
  height: { type: Number, default: 36 },
  /** accent | success | warning | danger */
  tone: { type: String, default: 'accent' },
  area: { type: Boolean, default: true },
  showLast: { type: Boolean, default: true },
  strokeWidth: { type: Number, default: 2 },
})

const gradId = `spark-${useId()}`

const color = computed(() => {
  if (props.tone === 'success') return 'var(--c-success)'
  if (props.tone === 'warning') return 'var(--c-warning)'
  if (props.tone === 'danger') return 'var(--c-danger)'
  return 'var(--c-accent)'
})

const padding = 3

const points = computed(() => {
  const values = props.data.length ? props.data : [0, 0]
  const min = Math.min(...values)
  const max = Math.max(...values)
  const span = max - min || 1
  const innerW = props.width - padding * 2
  const innerH = props.height - padding * 2
  const step = values.length > 1 ? innerW / (values.length - 1) : 0

  return values.map((v, i) => ({
    x: padding + i * step,
    y: padding + innerH - ((v - min) / span) * innerH,
  }))
})

const linePath = computed(() =>
  points.value.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '),
)

const areaPath = computed(() => {
  const base = props.height - padding
  const first = points.value[0]
  const last = points.value[points.value.length - 1]
  return `${linePath.value} L${last.x.toFixed(1)},${base} L${first.x.toFixed(1)},${base} Z`
})

const last = computed(() => points.value[points.value.length - 1])

const ariaLabel = computed(() => `Тренд: ${props.data.join(', ')}`)
</script>

<style scoped>
.u-spark {
  display: block;
  width: 100%;
  overflow: visible;
}
</style>

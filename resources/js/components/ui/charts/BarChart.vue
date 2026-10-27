<template>
  <figure ref="root" class="u-chart">
    <figcaption v-if="title" class="u-chart__title">{{ title }}</figcaption>

    <svg
      class="u-chart__svg"
      :width="width"
      :height="height"
      :viewBox="`0 0 ${width} ${height}`"
      role="img"
      :aria-label="ariaLabel"
    >
      <g v-if="showGrid" class="u-chart__grid">
        <template v-for="t in yTicks" :key="`y${t.value}`">
          <line :x1="pad.l" :y1="t.y" :x2="width - pad.r" :y2="t.y" />
          <text :x="pad.l - 8" :y="t.y + 4" class="u-chart__axis-label" text-anchor="end">
            {{ formatY(t.value) }}
          </text>
        </template>
      </g>

      <g v-for="(group, gi) in groups" :key="`g${gi}`">
        <rect
          v-for="(bar, bi) in group.bars"
          :key="`b${gi}-${bi}`"
          :x="bar.x"
          :y="bar.y"
          :width="bar.width"
          :height="bar.height"
          :fill="bar.color"
          rx="3"
          class="u-chart__bar"
        >
          <title>{{ group.label }} — {{ bar.value }}</title>
        </rect>

        <text
          :x="group.center"
          :y="height - pad.b + 16"
          class="u-chart__axis-label"
          text-anchor="middle"
        >
          {{ group.label }}
        </text>
      </g>
    </svg>

    <ul v-if="series.length > 1" class="u-chart__legend">
      <li v-for="(s, si) in series" :key="`l${si}`" class="u-chart__legend-item">
        <span class="u-chart__legend-dot" :style="{ background: color(s, si) }" />
        {{ s.name }}
      </li>
    </ul>
  </figure>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Столбчатый график для сравнения категорий (курсы, группы, сдачи).
 *
 * Как и LineChart — на SVG без внешнего движка. Поддерживает несколько
 * серий (сгруппированные столбцы) и подсказку через нативный <title>
 * (браузерный тултип): для столбцов этого достаточно, а лишний слой
 * абсолютных блоков только мешал бы.
 */
const props = defineProps({
  /** [{ name, color?, data: number[] }] */
  series: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  title: { type: String, default: '' },
  height: { type: Number, default: 260 },
  showGrid: { type: Boolean, default: true },
  format: { type: Function, default: null },
})

const root = ref(null)
const width = ref(600)
let observer = null

onMounted(() => {
  if (typeof ResizeObserver === 'undefined' || !root.value) return
  observer = new ResizeObserver((entries) => {
    const w = entries[0]?.contentRect?.width
    if (w && w > 0) width.value = Math.max(240, Math.round(w))
  })
  observer.observe(root.value)
})

onBeforeUnmount(() => observer?.disconnect())

const pad = { t: 12, r: 12, b: 28, l: 44 }

const allValues = computed(() => props.series.flatMap((s) => s.data || []))
const yMax = computed(() => (allValues.value.length ? Math.max(...allValues.value) : 0))
const innerW = computed(() => width.value - pad.l - pad.r)
const innerH = computed(() => props.height - pad.t - pad.b)

const palette = [
  'var(--c-primary)',
  'var(--c-accent)',
  'var(--c-warning)',
  'var(--c-success)',
  'var(--c-danger)',
]

function color(series, index) {
  return series.color || palette[index % palette.length]
}

const groups = computed(() => {
  const count = props.labels.length
  if (count === 0) return []
  const groupW = innerW.value / count
  const seriesCount = Math.max(1, props.series.length)
  const gap = 6
  const barW = Math.max(6, (groupW - gap * 2) / seriesCount)

  return props.labels.map((label, gi) => {
    const center = pad.l + groupW * gi + groupW / 2
    const startX = center - (barW * seriesCount) / 2
    const bars = props.series.map((s, si) => {
      const value = s.data?.[gi] ?? 0
      const h = yMax.value ? (value / yMax.value) * innerH.value : 0
      return {
        value,
        color: color(s, si),
        x: startX + si * barW,
        width: barW,
        y: pad.t + innerH.value - h,
        height: h,
      }
    })
    return { label, center, bars }
  })
})

const yTicks = computed(() => {
  const ticks = 4
  return Array.from({ length: ticks + 1 }, (_, i) => {
    const value = (yMax.value * i) / ticks
    return { value, y: pad.t + innerH.value - (innerH.value * i) / ticks }
  })
})

function formatY(value) {
  if (props.format) return props.format(value)
  return Number.isInteger(value) ? String(value) : value.toFixed(1)
}

const ariaLabel = computed(() =>
  props.series.map((s) => `${s.name || 'ряд'}: ${(s.data || []).join(', ')}`).join('; '),
)
</script>

<style scoped>
.u-chart {
  margin: 0;
  min-width: 0;
}

.u-chart__title {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
  margin-bottom: var(--sp-2);
}

.u-chart__svg {
  display: block;
  width: 100%;
  height: auto;
}

.u-chart__grid line {
  stroke: var(--c-border);
  stroke-width: 1;
  shape-rendering: crispEdges;
}

.u-chart__axis-label {
  font-size: 11px;
  fill: var(--c-text-muted);
}

.u-chart__bar {
  transition: fill-opacity var(--dur-fast) var(--ease);
}

.u-chart__bar:hover {
  fill-opacity: 0.8;
}

.u-chart__legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-4);
  margin: var(--sp-2) 0 0;
  padding: 0;
  list-style: none;
}

.u-chart__legend-item {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-xs);
  color: var(--c-text-secondary);
}

.u-chart__legend-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: var(--radius-pill);
}
</style>

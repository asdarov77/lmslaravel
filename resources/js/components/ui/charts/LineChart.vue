<template>
  <figure ref="root" class="u-chart" :style="{ '--chart-h': `${height}px` }">
    <figcaption v-if="title" class="u-chart__title">{{ title }}</figcaption>

    <svg
      class="u-chart__svg"
      :width="width"
      :height="height"
      :viewBox="`0 0 ${width} ${height}`"
      role="img"
      :aria-label="ariaLabel"
      @mousemove="onMove"
      @mouseleave="hoverIndex = -1"
    >
      <!-- Горизонтальная сетка + подписи оси Y -->
      <g v-if="showGrid" class="u-chart__grid">
        <template v-for="t in yTicks" :key="`y${t.value}`">
          <line :x1="pad.l" :y1="t.y" :x2="width - pad.r" :y2="t.y" />
          <text :x="pad.l - 8" :y="t.y + 4" class="u-chart__axis-label" text-anchor="end">
            {{ formatY(t.value) }}
          </text>
        </template>
      </g>

      <!-- Подписи оси X -->
      <g class="u-chart__xaxis">
        <text
          v-for="(label, i) in xLabels"
          :key="`x${i}`"
          :x="xFor(i)"
          :y="height - pad.b + 16"
          class="u-chart__axis-label"
          text-anchor="middle"
        >
          {{ label }}
        </text>
      </g>

      <!-- Серии -->
      <g v-for="(s, si) in series" :key="s.name || si">
        <path
          v-if="s.area"
          :d="areaPath(s)"
          :fill="color(s, si)"
          fill-opacity="0.12"
        />
        <path
          :d="linePath(s)"
          fill="none"
          :stroke="color(s, si)"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle
          v-for="(p, i) in pointsFor(s)"
          :key="`${si}-${i}`"
          :cx="p.x"
          :cy="p.y"
          r="3"
          :fill="color(s, si)"
          :class="{ 'is-hot': i === hoverIndex }"
        />
      </g>

      <!-- Вертикальная линия и маркер наведения -->
      <line
        v-if="hoverIndex >= 0"
        class="u-chart__crosshair"
        :x1="xFor(hoverIndex)"
        :y1="pad.t"
        :x2="xFor(hoverIndex)"
        :y2="height - pad.b"
      />
    </svg>

    <!-- Легенда -->
    <ul v-if="series.length > 1" class="u-chart__legend">
      <li v-for="(s, si) in series" :key="`l${si}`" class="u-chart__legend-item">
        <span class="u-chart__legend-dot" :style="{ background: color(s, si) }" />
        {{ s.name }}
      </li>
    </ul>

    <!-- Тултип -->
    <div v-if="tooltip" class="u-chart__tooltip" :style="tooltip.style">
      <strong>{{ tooltip.label }}</strong>
      <ul>
        <li v-for="row in tooltip.rows" :key="row.name">
          <span class="u-chart__legend-dot" :style="{ background: row.color }" />
          {{ row.name }}: {{ formatY(row.value) }}
        </li>
      </ul>
    </div>
  </figure>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Линейный график (с областями) для дашбордов и отчётов.
 *
 * Движок графиков в проект не добавлялся намеренно: всё, что нужно
 * сейчас — прогресс по времени и сравнение серий — рисуется на SVG
 * штатными средствами. Это на десятки килобайт меньше, чем ECharts,
 * и не тянет ещё один API поверх токенов.
 *
 * Сетка и подписи берутся из токенов, поэтому график автоматически
 * выглядит правильно и в светлой, и в тёмной теме.
 */
const props = defineProps({
  /**
   * Серии: [{ name, color?, area?, data: number[] }]. Точки индексируются
   * одинаково; labels задаются один раз на все серии.
   */
  series: { type: Array, default: () => [] },
  labels: { type: Array, default: () => [] },
  title: { type: String, default: '' },
  height: { type: Number, default: 260 },
  showGrid: { type: Boolean, default: true },
  /** Формат подписи Y. По умолчанию — с разрядами. */
  format: { type: Function, default: null },
})

const root = ref(null)
const width = ref(600)
const hoverIndex = ref(-1)

const pad = { t: 12, r: 12, b: 28, l: 44 }

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

const allValues = computed(() =>
  props.series.flatMap((s) => (Array.isArray(s.data) ? s.data : [])),
)

const yMax = computed(() => (allValues.value.length ? Math.max(...allValues.value) : 0))
const yMin = computed(() => (allValues.value.length ? Math.min(0, Math.min(...allValues.value)) : 0))

const innerW = computed(() => width.value - pad.l - pad.r)
const innerH = computed(() => props.height - pad.t - pad.b)

function xFor(i) {
  const n = Math.max(1, (props.labels.length || props.series[0]?.data?.length || 1) - 1)
  return pad.l + (i / n) * innerW.value
}

function yFor(value) {
  const span = yMax.value - yMin.value || 1
  return pad.t + innerH.value - ((value - yMin.value) / span) * innerH.value
}

function pointsFor(series) {
  return (series.data || []).map((v, i) => ({ x: xFor(i), y: yFor(v), value: v }))
}

function linePath(series) {
  return pointsFor(series)
    .map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`)
    .join(' ')
}

function areaPath(series) {
  const pts = pointsFor(series)
  if (pts.length === 0) return ''
  const base = props.height - pad.b
  const first = pts[0]
  const last = pts[pts.length - 1]
  return `${linePath(series)} L${last.x.toFixed(1)},${base} L${first.x.toFixed(1)},${base} Z`
}

const yTicks = computed(() => {
  const ticks = 4
  const span = yMax.value - yMin.value || 1
  return Array.from({ length: ticks + 1 }, (_, i) => {
    const value = yMin.value + (span * i) / ticks
    return { value, y: yFor(value) }
  })
})

const xLabels = computed(() => props.labels)

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

function formatY(value) {
  if (props.format) return props.format(value)
  return Number.isInteger(value) ? String(value) : value.toFixed(1)
}

const tooltip = computed(() => {
  if (hoverIndex.value < 0) return null
  const rows = props.series.map((s, si) => ({
    name: s.name || `Ряд ${si + 1}`,
    value: s.data?.[hoverIndex.value] ?? 0,
    color: color(s, si),
  }))
  return {
    label: props.labels[hoverIndex.value] ?? '',
    rows,
    style: { left: `${xFor(hoverIndex.value)}px` },
  }
})

const ariaLabel = computed(() => {
  if (!props.series.length) return 'Пустой график'
  return props.series.map((s) => `${s.name || 'ряд'}: ${(s.data || []).join(', ')}`).join('; ')
})

function onMove(event) {
  if (!props.series[0]?.data?.length) return
  const rect = event.currentTarget.getBoundingClientRect()
  const x = event.clientX - rect.left
  const n = props.labels.length || props.series[0].data.length
  const step = innerW.value / Math.max(1, n - 1)
  hoverIndex.value = Math.max(0, Math.min(n - 1, Math.round((x - pad.l) / step)))
}
</script>

<style scoped>
.u-chart {
  position: relative;
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
  height: var(--chart-h, 260px);
  overflow: visible;
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

.u-chart__crosshair {
  stroke: var(--c-border-strong);
  stroke-width: 1;
  stroke-dasharray: 3 3;
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
  flex-shrink: 0;
}

.u-chart__tooltip {
  position: absolute;
  top: var(--sp-6);
  transform: translateX(-50%);
  padding: var(--sp-2) var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  background: var(--c-surface);
  box-shadow: var(--shadow-3);
  font-size: var(--fs-xs);
  color: var(--c-text);
  pointer-events: none;
  white-space: nowrap;
  z-index: var(--z-dropdown);
}

.u-chart__tooltip ul {
  margin: var(--sp-1) 0 0;
  padding: 0;
  list-style: none;
}

.u-chart__tooltip li {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  color: var(--c-text-secondary);
}
</style>

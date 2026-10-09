<template>
  <figure class="u-heatmap">
    <figcaption v-if="title" class="u-heatmap__title">{{ title }}</figcaption>

    <div class="u-heatmap__grid" :style="{ '--weeks': weeks.length }">
      <div v-for="(week, wi) in weeks" :key="`w${wi}`" class="u-heatmap__week">
        <span
          v-for="day in week"
          :key="day.key"
          class="u-heatmap__cell"
          :class="`level-${day.level}`"
          :title="`${day.label}: ${day.value}`"
          :aria-label="`${day.label}: ${day.value}`"
          role="img"
        />
      </div>
    </div>

    <div v-if="showLegend" class="u-heatmap__legend">
      <span class="u-heatmap__legend-label">{{ legendFrom }}</span>
      <span v-for="l in 5" :key="`leg${l}`" class="u-heatmap__cell" :class="`level-${l - 1}`" />
      <span class="u-heatmap__legend-label">{{ legendTo }}</span>
    </div>
  </figure>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Календарь активности (heatmap) — streak-сетка в духе вкладов.
 *
 * Для обучаемого важно видеть ритм занятий: «не пропускаю неделю»,
 * «вернулся после паузы». Число рядом с графиком этого не даёт, а
 * сетка из дней показывает сразу.
 *
 * Вход — массив по дням [{ date, value }] или [value] (тогда даты
 * строятся от start. Его можно не задавать: по умолчанию — последние
 * 12 недель до сегодня.
 */
const props = defineProps({
  /** [{ date: 'YYYY-MM-DD' | Date, value: number }] либо number[]. */
  data: { type: Array, default: () => [] },
  /** Сколько уровней градации. */
  levels: { type: Number, default: 5 },
  showLegend: { type: Boolean, default: true },
  legendFrom: { type: String, default: 'Меньше' },
  legendTo: { type: String, default: 'Больше' },
  title: { type: String, default: '' },
  /** Недель в сетке. */
  weekCount: { type: Number, default: 12 },
})

const toKey = (d) => {
  const date = d instanceof Date ? d : new Date(d)
  return date.toISOString().slice(0, 10)
}

const valuesByDate = computed(() => {
  const map = new Map()
  let i = 0
  props.data.forEach((item) => {
    if (typeof item === 'number') {
      map.set(`__idx-${i}`, item)
      i += 1
    } else if (item && item.date) {
      map.set(toKey(item.date), Number(item.value) || 0)
    }
  })
  return map
})

const maxValue = computed(() => {
  const nums = props.data.map((it) => (typeof it === 'number' ? it : Number(it?.value) || 0))
  return Math.max(1, ...nums)
})

const weeks = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  // Конец — суббота текущей недели, чтобы столбцы были ровными.
  const end = new Date(today)
  end.setDate(end.getDate() + (6 - end.getDay()))

  const days = []

  const count = props.weekCount * 7
  for (let offset = count - 1; offset >= 0; offset -= 1) {
    const date = new Date(end)
    date.setDate(end.getDate() - offset)

    const key = toKey(date)
    let value = valuesByDate.value.get(key)

    if (value === undefined && props.data.every((d) => typeof d === 'number')) {
      const index = count - 1 - offset
      value = valuesByDate.value.get(`__idx-${index}`)
    }

    value = Number(value) || 0
    const level = value === 0
      ? 0
      : Math.min(props.levels - 1, Math.ceil((value / maxValue.value) * (props.levels - 1)))

    days.push({
      key,
      value,
      level,
      label: date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' }),
    })
  }

  const result = []
  for (let i = 0; i < days.length; i += 7) result.push(days.slice(i, i + 7))
  return result
})
</script>

<style scoped>
.u-heatmap {
  margin: 0;
}

.u-heatmap__title {
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
  margin-bottom: var(--sp-2);
}

.u-heatmap__grid {
  display: flex;
  gap: 3px;
  overflow-x: auto;
  padding-bottom: var(--sp-1);
}

.u-heatmap__week {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.u-heatmap__cell {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 3px;
  background: var(--c-surface-3);
  flex-shrink: 0;
}

.u-heatmap__cell.level-1 { background: color-mix(in srgb, var(--c-accent) 25%, var(--c-surface-3)); }
.u-heatmap__cell.level-2 { background: color-mix(in srgb, var(--c-accent) 45%, var(--c-surface-3)); }
.u-heatmap__cell.level-3 { background: color-mix(in srgb, var(--c-accent) 70%, var(--c-surface-3)); }
.u-heatmap__cell.level-4 { background: var(--c-accent); }

.u-heatmap__legend {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  margin-top: var(--sp-2);
  font-size: var(--fs-2xs);
  color: var(--c-text-muted);
}

.u-heatmap__legend-label {
  margin: 0 var(--sp-1);
}
</style>

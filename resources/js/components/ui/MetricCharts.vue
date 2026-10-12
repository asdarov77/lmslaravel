<template>
  <div>
    <!--
      Графики нарисованы обычным SVG, а не библиотекой.

      В проекте нет chart.js/apexcharts, а ради двух столбчатых
      диаграмм тянуть зависимость означало бы добавить ~300 КБ в бандл
      ради отрисовки прямоугольников. SVG здесь даёт то же самое и
      остаётся доступным: у полос есть текстовые подписи и значения.
    -->
    <section class="u-card u-card__body chart-block" data-test="chart-attempts">
      <h2 class="u-card__title">{{ $t('manager.trends.attemptsTitle') }}</h2>
      <p class="u-page__subtitle">{{ $t('manager.trends.attemptsHint') }}</p>

      <div v-if="!months.length" class="u-muted mt-3">{{ $t('manager.trends.empty') }}</div>

      <BarChart
      v-else
      class="mt-4"
      :series="series"
      :labels="labels"
      :height="220"
    />

    </section>

    <section class="u-card u-card__body chart-block" data-test="chart-groups">
      <h2 class="u-card__title">{{ $t('manager.trends.groupsTitle') }}</h2>

      <p v-if="!groups.length" class="u-muted mt-3">{{ $t('manager.trends.empty') }}</p>

      <ul v-else class="chart__list">
        <li v-for="group in groups" :key="group.id" class="chart__row">
          <span class="chart__row-name">{{ group.name }}</span>

          <!-- Группа без попыток показывается прочерком, а не нулём:
               ноль процентов читается как «все не сдали». -->
          <span v-if="group.percent === null" class="chart__row-empty">—</span>
          <template v-else>
            <span class="chart__track">
              <span class="chart__fill" :style="{ width: group.percent + '%' }"></span>
            </span>
            <span class="chart__row-value">{{ group.percent }}%</span>
          </template>
        </li>
      </ul>
    </section>
  </div>
</template>

<script>
/**
 * Графики админ-дашборда: динамика попыток и успеваемость по группам.
 *
 * Компонент чистый: принимает готовые данные и рисует. Считать их на
 * клиенте нельзя — пришлось бы отдать наружу все попытки, а их могут
 * быть десятки тысяч.
 */
import { BarChart } from './charts'

export default {
  name: 'MetricCharts',
  components: { BarChart },
  props: {
    /** @type {{month: string, label: string, attempts: number, passed: number}[]} */
    months: { type: Array, default: () => [] },
    /** @type {{id: number, name: string, percent: number|null, attempts: number}[]} */
    groups: { type: Array, default: () => [] },
  },
  computed: {
    /** Подписи месяцев по оси X. */
    labels() {
      return this.months.map((item) => item.label);
    },

    /**
     * Две серии: все попытки и сдачные.
     *
     * Ряд «сдано» всегда короче «всех» — это и есть смысл диаграммы:
     * видно не только нагрузку, но и долю успешных попыток.
     */
    series() {
      return [
        {
          name: this.$t('manager.trends.all'),
          data: this.months.map((item) => Number(item.attempts) || 0),
        },
        {
          name: this.$t('manager.trends.passed'),
          data: this.months.map((item) => Number(item.passed) || 0),
        },
      ];
    },
  },
}
</script>

<style scoped>
.chart-block + .chart-block {
  margin-top: var(--sp-4);
}











.chart__list {
  list-style: none;
  margin: var(--sp-3) 0 0;
  padding: 0;
}

.chart__row {
  align-items: center;
  display: flex;
  gap: var(--sp-3);
  padding: var(--sp-2) 0;
}

.chart__row-name {
  min-width: 160px;
}

.chart__track {
  background: var(--surface-2, #f5f6f8);
  border-radius: 999px;
  flex: 1;
  height: 8px;
  overflow: hidden;
}

.chart__fill {
  background: var(--c-primary, #3b6fd4);
  display: block;
  height: 100%;
}

.chart__row-value,
.chart__row-empty {
  font-variant-numeric: tabular-nums;
  min-width: 4ch;
  text-align: right;
}

.chart__row-empty {
  color: var(--text-muted);
}
</style>
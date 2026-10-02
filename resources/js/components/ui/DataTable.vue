<template>
  <div class="u-card">
    <!-- Заголовок блока с необязательными счётчиком и действиями -->
    <div v-if="title || $slots.toolbar" class="u-card__head">
      <div class="d-flex align-center" style="gap: var(--sp-2)">
        <h2 v-if="title" class="u-card__title">{{ title }}</h2>
        <span v-if="count != null" class="u-badge">{{ count }}</span>
      </div>
      <div class="d-flex align-center" style="gap: var(--sp-2)">
        <slot name="toolbar" />
      </div>
    </div>

    <!-- Поиск/фильтры -->
    <div v-if="$slots.filters" class="u-card__filters">
      <slot name="filters" />
    </div>

    <!-- Таблица либо пустое состояние -->
    <div v-if="isEmpty" class="u-card__body u-card__body--flush">
      <slot name="empty">
        <EmptyState
          :icon="emptyIcon"
          :title="emptyTitle"
          :text="emptyText"
        >
          <slot name="empty-action" />
        </EmptyState>
      </slot>
    </div>

    <div v-else class="u-table-wrap">
      <table class="u-table">
        <caption v-if="caption" class="u-sr-only">{{ caption }}</caption>
        <thead>
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              :style="column.width ? { width: column.width } : null"
              :scope="'col'"
              :class="column.align ? `u-table__actions--${column.align}` : null"
            >
              {{ column.title }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="rowKey(row)"
            :class="{ 'is-selected': isSelected(row) }"
          >
            <td
              v-for="column in columns"
              :key="column.key"
              :data-label="column.title"
            >
              <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                {{ format(row[column.key]) }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Пагинация/счётчик внизу -->
    <div v-if="!isEmpty && $slots.footer" class="u-card__foot">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import EmptyState from './EmptyState.vue'

/**
 * Таблица данных — единая разметка для всех списков приложения.
 *
 * Заменяет разнобой из четырёх подходов, которые были в проекте:
 * v-table с elevation-1, v-data-table из labs с elevation-3, сырой
 * <table> в GradeSettings и <v-table class="table"> с глобальными
 * стилями из RecursiveTable.vue.
 *
 * Что приводится к общему виду:
 *  - зебра и hover у строк (не было нигде);
 *  - sticky-шапка при вертикальной прокрутке;
 *  - курсор-указатель только на действиях, а не на всей таблице
 *    (раньше cursor-pointer стоял на <v-table>, хотя ни одна строка
 *    не была кликабельной — обманчивая подсказка);
 *  - счётчик и действия в шапке, а не «размазанные» по колонкам;
 *  - пустое состояние вместо пустой таблицы.
 */
const props = defineProps({
  /** @type {{key: string, title: string, width?: string, align?: 'left'|'right'}[]} */
  columns: { type: Array, required: true },
  /** @type {object[]} */
  rows: { type: Array, default: () => [] },
  /** Ключ строки. По умолчанию берётся id. */
  rowKey: { type: Function, default: (row) => row?.id },
  /** Функция определения «выделенной» строки. */
  isSelected: { type: Function, default: () => false },
  loading: { type: Boolean, default: false },
  title: { type: String, default: '' },
  count: { type: Number, default: null },
  caption: { type: String, default: '' },
  emptyTitle: { type: String, default: 'Пока пусто' },
  emptyText: { type: String, default: '' },
  emptyIcon: { type: String, default: 'mdi-inbox-outline' },
})

const isEmpty = computed(() => !props.loading && props.rows.length === 0)

const format = (value) => {
  if (value === null || value === undefined || value === '') return '—'
  return value
}
</script>

<style scoped>
.u-card__filters {
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-2);
}
</style>

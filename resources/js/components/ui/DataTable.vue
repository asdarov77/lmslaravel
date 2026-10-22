<template>
  <div class="u-card">
    <!--
      Заголовок блока. Условие учитывает и кнопку настройки колонок:
      раньше блок показывался только при title или слоте toolbar, и
      страница, которая просто передала columns/rows, не получала
      кнопку видимости колонок вовсе.
    -->
    <div v-if="title || $slots.toolbar || (showColumnToggle && toggleableColumns.length > 1)" class="u-card__head">
      <div class="d-flex align-center" style="gap: var(--sp-2)">
        <h2 v-if="title" class="u-card__title">{{ title }}</h2>
        <span v-if="count != null" class="u-badge">{{ count }}</span>
      </div>
      <div class="d-flex align-center" style="gap: var(--sp-2)">
        <slot name="toolbar" />

        <!--
          Видимость колонок. Раньше логика была написана (скрытые
          колонки живут в localStorage), но кнопки не было — функции
          toggleColumnVisibility/resetColumns не вызывались ниоткуда,
          то есть «спрятать лишнюю колонку» было невозможно.
        -->
        <v-menu v-if="showColumnToggle && toggleableColumns.length > 1" location="bottom end">
          <template #activator="{ props: activator }">
            <v-btn v-bind="activator" icon="mdi-table-cog" size="small" variant="text"
              :aria-label="$t('common.columns')" data-test="dt-columns" />
          </template>

          <v-list class="u-datatable__column-menu">
            <v-list-subheader>{{ $t('common.columns') }}</v-list-subheader>
            <v-list-item
              v-for="col in toggleableColumns"
              :key="col.key"
              :title="col.title"
              density="compact"
            >
              <template #prepend>
                <v-checkbox-btn
                  :model-value="colVisible(col.key)"
                  color="primary"
                  hide-details
                  density="compact"
                  :aria-label="col.title"
                  @update:model-value="toggleColumnVisibility(col.key)"
                />
              </template>
            </v-list-item>

            <v-divider />
            <v-list-item density="compact" :title="$t('common.resetColumns')" @click="resetColumns" />
          </v-list>
        </v-menu>
      </div>
    </div>

    <!-- Поиск/фильтры -->
    <div v-if="$slots.filters" class="u-card__filters">
      <slot name="filters" />
    </div>

    <!-- Панель массовых действий -->
    <div v-if="selectedRows.length > 0" class="u-card__bulk">
      <span class="u-card__bulk-count">
        {{ $t('common.selected', { count: selectedRows.length }) }}
      </span>
      <slot name="bulk-actions" :rows="selectedRows" />
      <v-btn variant="text" size="small" @click="clearSelection">
        {{ $t('common.clearSelection') }}
      </v-btn>
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

    <div
      v-else
      class="u-table-wrap"
      :class="{ 'u-table-wrap--sticky': stickyHeader && !isMobile }"
    >
      <table class="u-table">
        <caption v-if="caption" class="u-sr-only">{{ caption }}</caption>
        <thead :class="{ 'u-table__head--sticky': stickyHeader && !isMobile }">
          <tr>
            <th v-if="selectable" class="u-table__check">
              <input
                type="checkbox"
                :checked="allSelected"
                :indeterminate="someSelected"
                :aria-label="$t('common.selectAll')"
                @change="toggleSelectAll"
              />
            </th>
            <th
              v-for="column in visibleColumns"
              :key="column.key"
              :style="column.width ? { width: column.width } : null"
              :scope="'col'"
              :class="[
                column.align ? `u-table__actions--${column.align}` : null,
                column.sortable ? 'u-table__th--sortable' : null,
                sortKey === column.key ? 'u-table__th--sorted' : null,
              ]"
              :aria-sort="sortKey === column.key ? (sortOrder === 'asc' ? 'ascending' : 'descending') : undefined"
            >
              <button
                v-if="column.sortable"
                class="u-table__sort-btn"
                @click="toggleSort(column.key)"
              >
                {{ column.title }}
                <v-icon
                  v-if="sortKey === column.key"
                  :icon="sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down'"
                  size="14"
                />
                <v-icon v-else icon="mdi-arrow-up-down" size="14" class="u-table__sort-icon--muted" />
              </button>
              <template v-else>{{ column.title }}</template>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in sortedRows"
            :key="rowKey(row)"
            :class="{ 'is-selected': isSelected(row) }"
          >
            <td v-if="selectable" class="u-table__check">
              <input
                type="checkbox"
                :checked="isRowSelected(row)"
                :aria-label="$t('common.selectRow')"
                @change="toggleRowSelection(row)"
              />
            </td>
            <td
              v-for="column in visibleColumns"
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

        <!-- Mobile card mode -->
        <div v-if="mobileCards && isMobile" class="u-table__mobile-cards">
          <div
            v-for="row in sortedRows"
            :key="rowKey(row)"
            class="u-table__mobile-card"
            :class="{ 'is-selected': isSelected(row) }"
          >
            <div class="u-table__mobile-card-header">
              <template v-if="selectable">
                <input
                  type="checkbox"
                  :checked="isRowSelected(row)"
                  :aria-label="$t('common.selectRow')"
                  @change="toggleRowSelection(row)"
                  class="u-table__mobile-check"
                />
              </template>
              <div class="u-table__mobile-card-title">
                <slot name="cell-title" :row="row" :value="row.title">
                  {{ row.title || row.name || row.id }}
                </slot>
              </div>
            </div>
            <div class="u-table__mobile-card-body">
              <div
                v-for="column in visibleColumns"
                :key="column.key"
                class="u-table__mobile-field"
              >
                <span class="u-table__mobile-label">{{ column.title }}</span>
                <span class="u-table__mobile-value">
                  <slot :name="'cell-' + column.key" :row="row" :value="row[column.key]">
                    {{ format(row[column.key]) }}
                  </slot>
                </span>
              </div>
            </div>
          </div>
        </div>

    <!-- Пагинация/счётчик внизу -->
    <div v-if="!isEmpty && $slots.footer" class="u-card__foot">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import EmptyState from './EmptyState.vue'

/**
 * Мобильный режим.
 *
 * isMobile в шаблоне использовался, но НИГДЕ не был объявлен: обращение
 * к undefined давало false, поэтому карточный режим не включался НИКОГДА
 * — на телефоне таблица с горизонтальной прокруткой оставалась в 12
 * колонок. Отслеживаем ширину по media-query, а не по ширине окна в
 * обработчике resize: так смена режима приходит сразу, без опроса.
 */
const MOBILE_QUERY = '(max-width: 768px)'
const isMobile = ref(
  typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    ? window.matchMedia(MOBILE_QUERY).matches
    : false
)

let mediaQuery = null
if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
  mediaQuery = window.matchMedia(MOBILE_QUERY)
  const onChange = (event) => { isMobile.value = event.matches }
  if (mediaQuery.addEventListener) mediaQuery.addEventListener('change', onChange)
  else if (mediaQuery.addListener) mediaQuery.addListener(onChange)

  onBeforeUnmount(() => {
    if (mediaQuery.removeEventListener) mediaQuery.removeEventListener('change', onChange)
    else if (mediaQuery.removeListener) mediaQuery.removeListener(onChange)
  })
}

const props = defineProps({
  /** @type {{key: string, title: string, width?: string, align?: 'left'|'right', sortable?: boolean, visible?: boolean, cellClass?: string}[]} */
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
  /** Включить выбор строк чекбоксами. */
  selectable: { type: Boolean, default: false },
  /** Включить карточный режим на мобильных (default: true). */
  mobileCards: { type: Boolean, default: true },
  /** Показывать переключатель видимости колонок в тулбаре. */
  showColumnToggle: { type: Boolean, default: true },
  /** Заголовок таблицы прилипает при прокрутке. */
  stickyHeader: { type: Boolean, default: true },
})

const emit = defineEmits(['update:selected'])

const sortKey = ref('')
const sortOrder = ref('asc')
const selectedRows = ref([])

/** Колонки, скрытые пользователем (localStorage). */
const hiddenColumns = ref(
  JSON.parse(localStorage.getItem('datatable-hidden-columns') || '[]')
)

const saveHiddenColumns = () => {
  localStorage.setItem('datatable-hidden-columns', JSON.stringify(hiddenColumns.value))
}

const isEmpty = computed(() => !props.loading && props.rows.length === 0)

/** Колонки, которые пользователю есть смысл скрывать: без служебных. */
const toggleableColumns = computed(() =>
  props.columns.filter((col) => col.toggleable !== false)
)

const visibleColumns = computed(() =>
  props.columns.filter((col) => col.visible !== false && !hiddenColumns.value.includes(col.key))
)

const sortedRows = computed(() => {
  if (!sortKey.value) return props.rows
  const col = props.columns.find((c) => c.key === sortKey.value)
  if (!col?.sortable) return props.rows

  const sorted = [...props.rows].sort((a, b) => {
    const aVal = a[sortKey.value]
    const bVal = b[sortKey.value]

    if (aVal === bVal) return 0
    if (aVal === null || aVal === undefined) return 1
    if (bVal === null || bVal === undefined) return -1

    if (typeof aVal === 'number' && typeof bVal === 'number') {
      return sortOrder.value === 'asc' ? aVal - bVal : bVal - aVal
    }

    const aStr = String(aVal).toLowerCase()
    const bStr = String(bVal).toLowerCase()
    if (aStr < bStr) return sortOrder.value === 'asc' ? -1 : 1
    if (aStr > bStr) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })

  return sorted
})

const allSelected = computed(() =>
  props.rows.length > 0 && selectedRows.value.length === props.rows.length
)

const someSelected = computed(() =>
  selectedRows.value.length > 0 && !allSelected.value
)

const isRowSelected = (row) =>
  selectedRows.value.some((r) => props.rowKey(r) === props.rowKey(row))

const toggleRowSelection = (row) => {
  const key = props.rowKey(row)
  const idx = selectedRows.value.findIndex((r) => props.rowKey(r) === key)
  if (idx >= 0) {
    selectedRows.value.splice(idx, 1)
  } else {
    selectedRows.value.push(row)
  }
  emit('update:selected', selectedRows.value)
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedRows.value = []
  } else {
    selectedRows.value = [...props.rows]
  }
  emit('update:selected', selectedRows.value)
}

const clearSelection = () => {
  selectedRows.value = []
  emit('update:selected', [])
}

const toggleSort = (key) => {
  if (sortKey.value === key) {
    if (sortOrder.value === 'asc') {
      sortOrder.value = 'desc'
    } else {
      sortKey.value = ''
      sortOrder.value = 'asc'
    }
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

const toggleColumnVisibility = (key) => {
  const idx = hiddenColumns.value.indexOf(key)
  if (idx >= 0) {
    hiddenColumns.value.splice(idx, 1)
  } else {
    hiddenColumns.value.push(key)
  }
  saveHiddenColumns()
}

const isColumnHidden = (key) => hiddenColumns.value.includes(key)

const resetColumns = () => {
  hiddenColumns.value = []
  saveHiddenColumns()
}

const colVisible = (key) => !hiddenColumns.value.includes(key)

const isLastColumn = (col) => {
  const idx = props.columns.findIndex(c => c.key === col.key)
  return idx === props.columns.length - 1
}

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

.u-card__bulk {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  background: var(--c-primary-soft);
  border-bottom: 1px solid var(--c-border);
}

.u-card__bulk-count {
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--c-text);
}

.u-table__check {
  width: 1%;
  padding: var(--sp-3) var(--sp-4);
}

.u-table__th--sortable {
  padding: 0;
}

.u-table__sort-btn {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  width: 100%;
  padding: var(--sp-3) var(--sp-4);
  font: inherit;
  text-align: left;
  background: none;
  border: none;
  cursor: pointer;
  color: inherit;
}

.u-table__sort-btn:hover {
  background: var(--c-surface-3);
}

.u-table__th--sorted {
  color: var(--c-primary);
}

.u-table__sort-icon--muted {
  opacity: 0.4;
}

@media (max-width: 768px) {
  .u-table__check {
    padding: var(--sp-2);
  }
}

/* Sticky header */
.u-table-wrap--sticky .u-table__head--sticky {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--c-surface);
  border-bottom: 1px solid var(--c-border);
}

.u-table__head--sticky th {
  background: var(--c-surface);
}

/* Mobile card mode */
.u-table--mobile-cards {
  display: none;
}

.u-table__mobile-cards {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-3);
}

.u-table__mobile-card {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-1);
}

.u-table__mobile-card.is-selected {
  border-color: var(--c-primary);
  box-shadow: 0 0 0 2px var(--c-primary-soft);
}

.u-table__mobile-card-header {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3);
  border-bottom: 1px solid var(--c-border);
}

.u-table__mobile-check {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.u-table__mobile-card-title {
  font-weight: var(--fw-medium);
  font-size: var(--fs-base);
}

.u-table__mobile-card-body {
  padding: var(--sp-3);
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.u-table__mobile-field {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--sp-1) 0;
  border-bottom: 1px solid var(--c-border);
}

.u-table__mobile-field:last-child {
  border-bottom: none;
}

.u-table__mobile-label {
  font-size: var(--fs-sm);
  color: var(--c-text-muted);
  font-weight: var(--fw-medium);
}

.u-table__mobile-value {
  font-size: var(--fs-sm);
  color: var(--c-text);
  text-align: right;
  max-width: 60%;
  word-break: break-word;
}

.u-table__mobile-check {
  accent-color: var(--c-primary);
}

/* Column toggle menu */
.u-datatable__column-menu {
  min-width: 220px;
  max-height: 300px;
  overflow-y: auto;
}

.u-datatable__column-menu .v-list-item {
  padding: var(--sp-2) var(--sp-3);
}

.u-datatable__column-menu .v-divider {
  margin: var(--sp-1) 0;
}

/* Sortable column hover */
.u-table__sort-btn:focus-visible {
  outline: 2px solid var(--c-primary);
  outline-offset: -2px;
  border-radius: var(--radius-sm);
}

/* Selected row */
.u-table tbody tr.is-selected {
  background: var(--c-primary-soft);
}

/* Column visibility */
.u-table th[data-hidden] {
  display: none;
}

@media (max-width: 768px) {
  .u-table__check {
    padding: var(--sp-2);
  }
}
</style>

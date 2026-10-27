<template>
  <div class="u-recursive-table">
    <div v-if="title" class="u-recursive-table__header">
      <h2 class="u-recursive-table__title">{{ title }}</h2>
      <span v-if="count != null" class="u-badge">{{ count }}</span>
    </div>

    <div v-if="loading" class="u-recursive-table__loading">
      <v-skeleton-loader type="table" />
    </div>

    <div v-else-if="isEmpty" class="u-recursive-table__empty">
      <EmptyState
        :icon="emptyIcon"
        :title="emptyTitle"
        :text="emptyText"
      >
        <slot name="empty-action" />
      </EmptyState>
    </div>

    <div v-else class="u-recursive-table__wrap" :class="{ 'u-recursive-table__wrap--sticky': stickyHeader }" @scroll.passive="onScroll">
      <div v-if="$slots.filters" class="u-recursive-table__filters">
        <slot name="filters" />
      </div>

      <!-- Панель массовых действий -->
      <div v-if="selectedRows.length > 0" class="u-recursive-table__bulk">
        <span class="u-recursive-table__bulk-count">
          {{ $t('common.selected', { count: selectedRows.length }) }}
        </span>
        <slot name="bulk-actions" :rows="selectedRows" />
        <v-btn variant="text" size="small" @click="clearSelection">
          {{ $t('common.clearSelection') }}
        </v-btn>
      </div>

      <table class="u-table u-recursive-table__table">
        <caption v-if="caption" class="u-sr-only">{{ caption }}</caption>
        <thead :class="{ 'u-table__head--sticky': stickyHeader }">
          <tr>
            <th v-if="selectable" class="u-table__check" scope="col">
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
              scope="col"
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
            <th v-if="showColumnToggle && toggleableColumns.length > 1" class="u-table__actions" scope="col">
              <v-menu location="bottom end">
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
            </th>
          </tr>
        </thead>
        <tbody>
          <!--
            Виртуализация: при большом числе строк рендерится только
            окно видимости (обрезка сверху — spacer-строкой). Порог
            VIRTUALIZE_AFTER — ниже него DOM живёт без spacer.
          -->
          <tr v-if="padTop" aria-hidden="true" :style="{ height: `${padTop}px` }">
            <td v-if="selectable" class="u-table__check" />
            <td v-for="column in visibleColumns" :key="`pad-top-${column.key}`"></td>
          </tr>
          <tr
            v-for="row in windowRows"
            :key="getRowKey(row)"
            :class="[
              'u-recursive-table__row',
              `u-recursive-table__row--level-${row._level}`,
              { 'is-selected': isRowSelected(row) },
              { 'is-expanded': row._expanded && row._hasChildren },
              { 'has-children': row._hasChildren },
            ]"
            :style="{ '--level': row._level }"
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
              :class="column.key === treeColumnKey ? 'u-recursive-table__tree-cell' : ''"
            >
              <slot
                :name="`cell-${column.key}`"
                :row="row"
                :value="row[column.key]"
                :level="row._level"
                :expanded="row._expanded"
                :has-children="row._hasChildren"
              >
                <template v-if="column.key === treeColumnKey">
                  <div class="u-recursive-table__tree-content" :style="{ paddingLeft: `${(row._level - 1) * 24}px` }">
                    <button
                      v-if="row._hasChildren"
                      type="button"
                      class="u-recursive-table__toggle"
                      :aria-expanded="row._expanded"
                      :aria-label="row._expanded ? $t('common.collapse') : $t('common.expand')"
                      @click.stop="toggleRowExpansion(row)"
                    >
                      <v-icon
                        class="u-recursive-table__icon"
                        :icon="row._expanded ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                        size="16"
                      />
                    </button>
                    <span v-else class="u-recursive-table__icon" aria-hidden="true">
                      <v-icon size="16">mdi-circle-small</v-icon>
                    </span>
                    <span class="u-recursive-table__label">{{ getCellValue(row, column.key) }}</span>
                  </div>
                </template>
                <template v-else>
                  {{ format(getCellValue(row, column.key)) }}
                </template>
              </slot>
            </td>
          </tr>

          <tr
            v-for="aggregate in visibleAggregates"
            :key="getRowKey(aggregate)"
            :class="[
              'u-recursive-table__row',
              'u-recursive-table__aggregate',
              `u-recursive-table__aggregate--level-${aggregate._level}`,
            ]"
            :style="{ '--level': aggregate._level }"
          >
            <td v-if="selectable" class="u-table__check" />
            <td
              v-for="column in visibleColumns"
              :key="column.key"
              :data-label="column.title"
            >
              <slot
                :name="`aggregate-${column.key}`"
                :row="aggregate"
                :value="aggregate[column.key]"
                :level="aggregate._level"
              >
                {{ format(getCellValue(aggregate, column.key)) }}
              </slot>
            </td>
          </tr>

          <!-- Spacer снизу держит высоту scrolталённого списка. -->
          <tr v-if="padBottom" aria-hidden="true" :style="{ height: `${padBottom}px` }">
            <td v-if="selectable" class="u-table__check" />
            <td v-for="column in visibleColumns" :key="`pad-bottom-${column.key}`"></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="!isEmpty && $slots.footer" class="u-recursive-table__footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import EmptyState from './EmptyState.vue'

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
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: Function, default: (row) => row?.id },
  parentKey: { type: String, default: 'parent_id' },
  treeColumnKey: { type: String, default: 'name' },
  levelKey: { type: String, default: 'level' },
  isSelected: { type: Function, default: () => false },
  loading: { type: Boolean, default: false },
  title: { type: String, default: '' },
  count: { type: Number, default: null },
  caption: { type: String, default: '' },
  emptyTitle: { type: String, default: 'Пока пусто' },
  emptyText: { type: String, default: '' },
  emptyIcon: { type: String, default: 'mdi-inbox-outline' },
  selectable: { type: Boolean, default: false },
  stickyHeader: { type: Boolean, default: true },
  showColumnToggle: { type: Boolean, default: true },
  aggregates: { type: Function, default: null },
  defaultExpanded: { type: Boolean, default: false },
})

const emit = defineEmits(['update:selected', 'toggle', 'select', 'aggregate'])

const sortKey = ref('')
const sortOrder = ref('asc')
const selectedRows = ref([])
const expandedRows = ref(new Set())
const hiddenColumns = ref(
  JSON.parse(localStorage.getItem('recursivetable-hidden-columns') || '[]')
)

const saveHiddenColumns = () => {
  localStorage.setItem('recursivetable-hidden-columns', JSON.stringify(hiddenColumns.value))
}

const isEmpty = computed(() => !props.loading && props.rows.length === 0)

const toggleableColumns = computed(() =>
  props.columns.filter((col) => col.toggleable !== false)
)

const visibleColumns = computed(() =>
  props.columns.filter((col) => col.visible !== false && !hiddenColumns.value.includes(col.key))
)

const columnMap = computed(() => {
  const map = {}
  props.columns.forEach((col) => { map[col.key] = col })
  return map
})

const treeBuilder = (rows) => {
  const nodeMap = new Map()
  const roots = []

  rows.forEach((row) => {
    const id = props.rowKey(row)
    nodeMap.set(id, { ...row, _children: [], _hasChildren: false, _level: row[props.levelKey] || 1, _expanded: props.defaultExpanded })
  })

  rows.forEach((row) => {
    const id = props.rowKey(row)
    const parentId = row[props.parentKey]
    const node = nodeMap.get(id)

    if (parentId != null && nodeMap.has(parentId)) {
      const parent = nodeMap.get(parentId)
      parent._children.push(node)
      parent._hasChildren = true
      node._level = parent._level + 1
    } else {
      roots.push(node)
    }
  })

  const sortChildren = (nodes) => {
    nodes.forEach((node) => {
      if (node._children.length) {
        node._children.sort((a, b) => {
          const aVal = a[props.treeColumnKey]
          const bVal = b[props.treeColumnKey]
          if (aVal === bVal) return 0
          if (aVal === null || aVal === undefined) return 1
          if (bVal === null || bVal === undefined) return -1
          return String(aVal).localeCompare(String(bVal))
        })
        sortChildren(node._children)
      }
    })
  }
  sortChildren(roots)

  return { roots, nodeMap }
}

const initExpandedRows = () => {
  if (props.defaultExpanded) {
    const { roots } = treeBuilder(props.rows)
    const collect = (nodes) => {
      nodes.forEach((node) => {
        if (node._hasChildren) {
          expandedRows.value.add(props.rowKey(node))
        }
        if (node._children) {
          collect(node._children)
        }
      })
    }
    collect(roots)
  }
}

watch([() => props.defaultExpanded, () => props.rows], () => {
  expandedRows.value.clear()
  initExpandedRows()
}, { immediate: true, deep: true })

const treeRoots = computed(() => {
  const { roots } = treeBuilder(props.rows)
  return roots
})

const treeNodeMap = computed(() => {
  const { nodeMap } = treeBuilder(props.rows)
  return nodeMap
})

const flattenTree = (nodes, expandedSet) => {
  const result = []
  const walk = (node) => {
    result.push(node)
    if (node._hasChildren && expandedSet.has(props.rowKey(node))) {
      node._children?.forEach((child) => walk(child))
    }
  }
  nodes?.forEach((node) => walk(node))
  return result
}

const toNumber = (val) => {
  if (val === null || val === undefined || val === '') return null
  const num = Number(val)
  return Number.isNaN(num) ? null : num
}

const flattenedRows = computed(() => {
  const rows = flattenTree(treeRoots.value, expandedRows.value)
  if (!sortKey.value) return rows

  const col = props.columns.find((c) => c.key === sortKey.value)
  if (!col?.sortable) return rows

  return [...rows].sort((a, b) => {
    const aVal = a[sortKey.value]
    const bVal = b[sortKey.value]

    if (aVal === bVal) return 0
    if (aVal === null || aVal === undefined) return 1
    if (bVal === null || bVal === undefined) return -1

    const aNum = toNumber(aVal)
    const bNum = toNumber(bVal)
    if (aNum !== null && bNum !== null) {
      return sortOrder.value === 'asc' ? aNum - bNum : bNum - aNum
    }

    const aStr = String(aVal).toLowerCase()
    const bStr = String(bVal).toLowerCase()
    if (aStr < bStr) return sortOrder.value === 'asc' ? -1 : 1
    if (aStr > bStr) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })
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

// Виртуализация: окно рендера строк по позиции скролла контейнера.
// На мобильной раскладке и для <=300 строк DOM живёт без spacer'ов.
const VIRTUAL_ROW_HEIGHT = 44
const VIRTUALIZE_LIMIT = 300

const scrollTop = ref(0)
const viewportHeight = ref(600)

const onScroll = (event) => {
  scrollTop.value = event.target.scrollTop
  viewportHeight.value = event.target.clientHeight || viewportHeight.value
}

const virtualize = computed(
  () => !isMobile.value && flattenedRows.value.length > VIRTUALIZE_LIMIT + visibleAggregates.value.length,
)

const windowRows = computed(() => {
  if (!virtualize.value) return flattenedRows.value
  const first = Math.floor(scrollTop.value / VIRTUAL_ROW_HEIGHT)
  const visible = Math.ceil(viewportHeight.value / VIRTUAL_ROW_HEIGHT) + 5
  return flattenedRows.value.slice(Math.max(0, first - 2), first + visible)
})

const padTop = computed(() => {
  if (!virtualize.value) return 0
  const first = Math.floor(scrollTop.value / VIRTUAL_ROW_HEIGHT)
  return Math.max(0, first - 2) * VIRTUAL_ROW_HEIGHT
})

const padBottom = computed(() => {
  if (!virtualize.value) return 0
  return Math.max(0, (flattenedRows.value.length + visibleAggregates.value.length) * VIRTUAL_ROW_HEIGHT - padTop.value - windowRows.value.length * VIRTUAL_ROW_HEIGHT)
})

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

const toggleRowExpansion = (row) => {
  const key = props.rowKey(row)
  if (expandedRows.value.has(key)) {
    expandedRows.value.delete(key)
  } else {
    expandedRows.value.add(key)
  }
  emit('toggle', { row, expanded: expandedRows.value.has(key) })
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

const resetColumns = () => {
  hiddenColumns.value = []
  saveHiddenColumns()
}

const colVisible = (key) => !hiddenColumns.value.includes(key)

const getRowKey = (row) => props.rowKey(row)

const getCellValue = (row, key) => row[key]

const format = (value) => {
  if (value === null || value === undefined || value === '') return '—'
  return value
}

const visibleAggregates = computed(() => {
  if (!props.aggregates) return []
  const allAggregates = props.aggregates(props.rows)
  return allAggregates.filter((agg) => {
    const parentKey = agg._parent || agg.parent_id
    const isExpanded = expandedRows.value.size === 0 && props.defaultExpanded
      ? true
      : expandedRows.value.has(parentKey)
    return !parentKey || isExpanded
  })
})

watch(() => props.rows, () => {
  expandedRows.value.clear()
})
</script>

<style scoped>
.u-recursive-table__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-4);
}

.u-recursive-table__title {
  font-size: var(--fs-xl);
  font-weight: var(--fw-semibold);
  margin: 0;
}

.u-recursive-table__loading {
  padding: var(--sp-6);
}

.u-recursive-table__empty {
  padding: var(--sp-6);
}

.u-recursive-table__wrap {
  overflow: auto;
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
}

.u-recursive-table__wrap--sticky .u-table__head--sticky {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--c-surface);
  border-bottom: 1px solid var(--c-border);
}

.u-recursive-table__table {
  width: 100%;
  border-collapse: collapse;
}

.u-recursive-table__table thead th {
  background: var(--c-surface-2);
  padding: var(--sp-3) var(--sp-4);
  text-align: left;
  font-weight: var(--fw-medium);
  font-size: var(--fs-sm);
  color: var(--c-text-muted);
  border-bottom: 1px solid var(--c-border);
  white-space: nowrap;
}

.u-recursive-table__table th.u-table__check {
  padding: var(--sp-3) var(--sp-4);
  width: 1%;
}

.u-recursive-table__th--sortable {
  padding: 0;
}

.u-recursive-table__sort-btn {
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

.u-recursive-table__sort-btn:hover {
  background: var(--c-surface-3);
}

.u-recursive-table__th--sorted {
  color: var(--c-primary);
}

.u-recursive-table__sort-icon--muted {
  opacity: 0.4;
}

.u-recursive-table__row {
  transition: background var(--dur-fast) var(--ease);
}

.u-recursive-table__row:hover {
  background: var(--c-surface-2);
}

.u-recursive-table__row.is-selected {
  background: var(--c-primary-soft);
}

.u-recursive-table__row--level-1 {
  font-weight: var(--fw-medium);
}

.u-recursive-table__row--level-2 {
  color: var(--c-text);
}

.u-recursive-table__row--level-3 {
  color: var(--c-text-muted);
  font-size: var(--fs-sm);
}

.u-recursive-table__row--level-4,
.u-recursive-table__row--level-5 {
  color: var(--c-text-muted);
  font-size: var(--fs-xs);
}

.u-recursive-table__tree-cell {
  min-width: 280px;
}

.u-recursive-table__tree-content {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  min-height: 36px;
}

.u-recursive-table__toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  color: var(--c-text-muted);
  cursor: pointer;
  border-radius: var(--radius-sm);
  transition: color var(--dur-fast) var(--ease), background var(--dur-fast) var(--ease);
  flex-shrink: 0;
}

.u-recursive-table__toggle:hover {
  background: var(--c-surface-2);
  color: var(--c-text);
}

.u-recursive-table__toggle:focus-visible {
  outline: 2px solid var(--c-primary);
  outline-offset: 2px;
}

.u-recursive-table__icon {
  font-size: 16px;
  line-height: 1;
  flex-shrink: 0;
}

.u-recursive-table__label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.u-recursive-table__aggregate {
  background: var(--c-surface-3);
  font-weight: var(--fw-medium);
  border-top: 1px solid var(--c-border);
}

.u-recursive-table__aggregate--level-1 {
  background: var(--c-primary-soft);
  color: var(--c-primary);
}

.u-recursive-table__aggregate--level-2 {
  background: var(--c-surface-2);
}

.u-recursive-table__footer {
  padding: var(--sp-3) var(--sp-5);
  border-top: 1px solid var(--c-border);
  background: var(--c-surface-2);
}

@media (max-width: 768px) {
  .u-recursive-table__tree-content {
    padding-left: calc((var(--level, 1) - 1) * 16px) !important;
  }

  .u-recursive-table__toggle {
    width: 20px;
    height: 20px;
  }
}
</style>
<!--
  CourseTree — доступное дерево курса (role="tree", roving tabindex, Д10).

  Данные: nodes из player-endpoint (плоский список, source of truth).
  Дерево строится по parent_id на сервере; здесь только визуальная рекурсия.
  Признак материала — node.is_material (сервер, Д3), кликабельны только материалы.
-->
<template>
  <ul class="ct" role="tree" :aria-label="label" aria-multiselectable="false">
    <li
      v-for="node in rootNodes"
      :key="node.id"
      role="none"
    >
      <CourseTreeNode
        :node="node"
        :depth="0"
        :active-id="activeId"
        :by-id="byId"
        :tab-id="tabId"
        @select="$emit('select', $event)"
        @set-tab="$emit('set-tab', $event)"
      />
    </li>
  </ul>
</template>

<script>
import CourseTreeNode from './CourseTreeNode.vue'

export default {
  name: 'CourseTree',
  components: { CourseTreeNode },
  props: {
    nodes: { type: Array, default: () => [] }, // плоский список
    activeId: { type: Number, default: null },
    tabId: { type: Number, default: null },    // узел, принимающий фокус (roving)
    label: { type: String, default: 'Содержание курса' },
  },
  emits: ['select', 'set-tab'],
  computed: {
    byId() {
      return new Map(this.nodes.map((n) => [n.id, n]))
    },
    // Д4: корни = parent_id 0/null ИЛИ висящие на отсутствующем родителе.
    rootNodes() {
      return this.nodes.filter((n) => {
        const pid = n.parent_id ?? 0
        return pid === 0 || !this.byId.has(pid)
      })
    },
  },
}
</script>

<style scoped>
.ct {
  list-style: none;
  margin: 0;
  padding: 0 4px;
}
</style>

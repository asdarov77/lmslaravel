<template>
  <div class="u-tree" role="tree" aria-label="Course structure">
    <TreeViewNode
      v-for="node in rootNodes"
      :key="node.id"
      :node="node"
      :active-id="activeId"
      :expanded-nodes="expandedNodes"
      :all-nodes="nodes"
      @select="$emit('select', $event)"
      @toggle="$emit('toggle', $event)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import TreeViewNode from './TreeViewNode.vue'

const props = defineProps({
  /** Flat list of nodes with level property (1=theme, 2=section, 3=material) */
  nodes: { type: Array, default: () => [] },
  /** Currently active node ID */
  activeId: { type: [String, Number], default: null },
  /** Set of expanded node IDs */
  expandedNodes: { type: Set, default: () => new Set() },
})

defineEmits(['select', 'toggle'])

const rootNodes = computed(() => {
  if (!props.nodes.length) return []
  const minLevel = Math.min(...props.nodes.map((n) => n.level || 1))
  return props.nodes.filter((n) => (n.level || 1) === minLevel)
})
</script>

<style scoped>
.u-tree {
  font-size: var(--fs-sm);
  line-height: 1.5;
}

.u-tree ul {
  list-style: none;
  margin: 0;
  padding: 0 0 0 var(--sp-3);
}
</style>

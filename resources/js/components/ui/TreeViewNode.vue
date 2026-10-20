<template>
  <div class="u-tree__node">
    <div
      class="u-tree__item"
      :class="{ 'u-tree__item--active': isActive }"
      @click="handleSelect"
    >
      <button
        v-if="hasChildren"
        type="button"
        class="u-tree__toggle"
        :aria-expanded="isExpanded"
        :aria-label="isExpanded ? $t('tree.collapse') : $t('tree.expand')"
        @click.stop="toggle"
      >
        <v-icon
          class="u-tree__icon"
          :icon="isExpanded ? 'mdi-chevron-down' : 'mdi-chevron-right'"
          size="16"
        />
      </button>

      <span v-else class="u-tree__icon" aria-hidden="true">
        <v-icon size="16">mdi-circle-small</v-icon>
      </span>

      <span class="u-tree__label">{{ node.title }}</span>
    </div>

    <transition name="tree-children">
      <ul
        v-show="isExpanded"
        class="u-tree__children"
        :class="isExpanded ? 'u-tree__children--expanded' : 'u-tree__children--collapsed'"
        role="group"
        :aria-label="$t('tree.childrenOf', { title: node.title })"
      >
        <TreeViewNode
          v-for="child in children"
          :key="child.id"
          :node="child"
          :active-id="activeId"
          :expanded-nodes="expandedNodes"
          @select="$emit('select', $event)"
          @toggle="$emit('toggle', $event)"
        />
      </ul>
    </transition>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineProps({
  node: { type: Object, required: true },
  activeId: { type: [String, Number], default: null },
  expandedNodes: { type: Set, default: () => new Set() },
  allNodes: { type: Array, default: () => [] },
})

defineEmits(['select', 'toggle'])

const children = computed(() => {
  // Find children of this node (nodes with level = parent level + 1 that appear after this node)
  const nodeLevel = node.level || 1
  const nodeIndex = allNodes.value.findIndex(n => n.id === node.id)
  if (nodeIndex === -1) return []

  const children = []
  for (let i = nodeIndex + 1; i < allNodes.value.length; i++) {
    const item = allNodes.value[i]
    if (item.level <= node.level) break // Stop at same or higher level
    if (item.level === nodeLevel + 1) {
      children.push(item)
    }
  }
  return children
})

const hasChildren = computed(() => children.value.length > 0)

const isActive = computed(() => props.node.id === props.activeId)

const isExpanded = computed(() => props.expandedNodes.has(props.node.id))

const toggle = () => {
  if (hasChildren.value) {
    emit('toggle', props.node.id)
  }
}

const handleSelect = () => {
  emit('select', props.node)
}
</script>

<style scoped>
.u-tree__node {
  display: flex;
  flex-direction: column;
}

.u-tree__item {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-1) var(--sp-2);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background var(--dur-fast) var(--ease);
  user-select: none;
}

.u-tree__item:hover {
  background: var(--c-surface-2);
}

.u-tree__item--active {
  background: var(--c-primary-soft);
  color: var(--c-primary);
  font-weight: 500;
}

.u-tree__toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border: none;
  background: transparent;
  color: var(--c-text-muted);
  cursor: pointer;
  border-radius: var(--radius-sm);
  transition: color var(--dur-fast) var(--ease), background var(--dur-fast) var(--ease);
  flex-shrink: 0;
}

.u-tree__toggle:hover {
  background: var(--c-surface-2);
  color: var(--c-text);
}

.u-tree__toggle:focus-visible {
  outline: 2px solid var(--c-primary);
  outline-offset: 2px;
}

.u-tree__icon {
  font-size: 16px;
  line-height: 1;
  flex-shrink: 0;
}

.u-tree__label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.u-tree__children {
  overflow: hidden;
  transition: max-height 0.2s ease-out, opacity 0.15s ease-out;
}

.u-tree__children--collapsed {
  max-height: 0;
  opacity: 0;
  padding-left: var(--sp-3);
}

.u-tree__children--expanded {
  max-height: 1000px;
  opacity: 1;
}

/* Transition for tree children */
.tree-children-enter-active,
.tree-children-leave-active {
  transition: max-height 0.2s ease-out, opacity 0.15s ease-out;
}

.tree-children-enter-from,
.tree-children-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>

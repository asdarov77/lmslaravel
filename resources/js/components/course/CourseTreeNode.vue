<!--
  CourseTreeNode — рекурсивный узел дерева (role="treeitem").

  Клавиатура (Д10):
   ArrowDown/Up — перемещение по видимым узлам (roving tabindex),
   ArrowRight — раскрыть группу / перейти к первому ребёнку,
   ArrowLeft  — свернуть группу / уйти к родителю,
   Enter/Space — открыть материал,
   Home/End   — первый/последний видимый узел.
-->
<template>
  <div
    :id="'tree-node-' + node.id"
    class="ctn"
    :class="{ 'ctn--material': node.is_material, 'ctn--active': node.id === activeId, 'ctn--visited': node.visited }"
    :role="node.is_material ? 'treeitem' : 'group'"
    :aria-level="depth + 1"
    :aria-selected="node.is_material && node.id === activeId ? 'true' : undefined"
    :aria-expanded="!node.is_material && hasChildren ? String(open) : undefined"
    :tabindex="tabId === node.id ? 0 : -1"
    @click="onClick"
    @keydown.stop="onKeydown"
  >
    <div class="ctn__row" :style="{ paddingLeft: depth * 14 + 'px' }">
      <button
        v-if="hasChildren"
        class="ctn__twisty"
        tabindex="-1"
        :aria-label="open ? 'Свернуть' : 'Развернуть'"
        @click.stop="open = !open"
      >{{ open ? '▾' : '▸' }}</button>
      <span v-else class="ctn__twisty ctn__twisty--ph" aria-hidden="true"></span>

      <span v-if="node.visited" class="ctn__check" title="Просмотрено">✓</span>
      <span class="ctn__title">{{ node.title }}</span>
    </div>

    <div v-if="!node.is_material && open && hasChildren" role="group">
      <CourseTreeNode
        v-for="child in children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :active-id="activeId"
        :by-id="byId"
        :tab-id="tabId"
        @select="$emit('select', $event)"
        @set-tab="$emit('set-tab', $event)"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'CourseTreeNode',
  props: {
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    activeId: { type: Number, default: null },
    tabId: { type: Number, default: null },
    byId: { type: Map, required: true },
  },
  emits: ['select', 'set-tab'],
  data() {
    return { open: true } // темы раскрыты по умолчанию (небольшой курс)
  },
  computed: {
    children() {
      // дети определяются по parent_id (Д4), а не по позиции в массиве
      return [...this.byId.values()].filter((n) => (n.parent_id ?? 0) === this.node.id)
    },
    hasChildren() {
      return this.children.length > 0
    },
  },
  watch: {
    // активный материал должен быть виден: раскрываем цепочку предков
    activeId: {
      immediate: true,
      handler(id) {
        if (id == null) return
        let cur = this.byId.get(id)
        while (cur) {
          const pid = cur.parent_id ?? 0
          if (pid === cur.id) break
          if (pid !== 0 && this.containsDescendant(pid, id)) {
            // этот ли нас компонент? проверяем принадлежность поддерева
            if (this.containsNode(this.node.id, id)) this.open = true
          }
          cur = this.byId.get(pid)
        }
      },
    },
  },
  methods: {
    containsDescendant(pid, id) {
      const n = this.byId.get(id)
      return !!n && n.parent_id === pid
    },
    containsNode(rootId, id) {
      let cur = this.byId.get(id)
      const guard = new Set()
      while (cur) {
        if (cur.id === rootId) return true
        if (guard.has(cur.id)) return false
        guard.add(cur.id)
        cur = this.byId.get(cur.parent_id ?? 0)
      }
      return false
    },
    onClick() {
      if (this.node.is_material) {
        this.$emit('select', this.node.id)
        this.$emit('set-tab', this.node.id)
      } else if (this.hasChildren) {
        this.open = !this.open
      }
    },
    visibleList() {
      // обход «как видит пользователь» для ArrowUp/Down
      const out = []
      const walk = (n) => {
        out.push(n)
        if (!n.is_material && this.containsNode(n.id, null)) { /* noop */ }
      }
      void walk
      // простой вариант: все материалы+группы этого поддерева в DOM-порядке
      const collect = (node) => {
        const list = [node]
        if (!node.is_material && this.open && this.hasChildren) {
          for (const c of this.children) list.push(...collect(c))
        }
        return list
      }
      return collect(this.node)
    },
    focusSelf() {
      this.$emit('set-tab', this.node.id)
      this.$nextTick(() => {
        const el = document.getElementById('tree-node-' + this.node.id)
        if (el) el.focus()
      })
    },
    onKeydown(e) {
      const flat = this.visibleList()
      const idx = flat.findIndex((n) => n.id === this.node.id)
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault()
          if (idx < flat.length - 1) flat[idx + 1].__focus = true
          this.moveFocus(flat, idx + 1)
          break
        case 'ArrowUp':
          e.preventDefault()
          this.moveFocus(flat, idx - 1)
          break
        case 'ArrowRight':
          e.preventDefault()
          if (!this.node.is_material && this.hasChildren) {
            if (!this.open) this.open = true
            else this.focusNode(this.children[0])
          }
          break
        case 'ArrowLeft':
          e.preventDefault()
          if (!this.node.is_material && this.hasChildren && this.open) this.open = false
          else if ((this.node.parent_id ?? 0) !== 0) {
            const parent = this.byId.get(this.node.parent_id)
            if (parent) this.focusNode(parent)
          }
          break
        case 'Home':
          e.preventDefault()
          this.moveFocus(flat, 0)
          break
        case 'End':
          e.preventDefault()
          this.moveFocus(flat, flat.length - 1)
          break
        case 'Enter':
        case ' ':
          e.preventDefault()
          this.onClick()
          break
      }
    },
    moveFocus(flat, to) {
      if (to >= 0 && to < flat.length) this.focusNode(flat[to])
    },
    focusNode(node) {
      const el = document.getElementById('tree-node-' + node.id)
      if (el) {
        this.$emit('set-tab', node.id)
        this.$nextTick(() => el.focus())
      }
    },
  },
}
</script>

<style scoped>
.ctn {
  outline: none;
  border-radius: 6px;
}
.ctn:focus-visible {
  outline: 2px solid var(--v-primary-base, #1976d2);
  outline-offset: -2px;
}
.ctn__row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  padding: 4px 6px;
  border-radius: 6px;
  word-break: break-word;
}
.ctn--material .ctn__row {
  cursor: pointer;
}
.ctn--material:hover .ctn__row {
  background: rgba(0, 0, 0, 0.05);
}
.ctn--active .ctn__row {
  background: rgba(25, 118, 210, 0.14);
  font-weight: 600;
}
.ctn--visited .ctn__title {
  color: rgba(0, 0, 0, 0.6);
}
.ctn__check {
  color: #2e7d32;
  font-size: 12px;
}
.ctn__twisty {
  border: none;
  background: transparent;
  cursor: pointer;
  width: 16px;
  flex: 0 0 16px;
  padding: 0;
  color: inherit;
}
.ctn__twisty--ph {
  visibility: hidden;
}
</style>

<!--
  CourseTreeNodePremium — узел дерева «Плеера 2.0» (design-manifest §2.1).

  Отличия от базового CourseTreeNode.vue:
   - статус узла показан ЗНАЧКОМ + текстом для aria (не только цветом):
     done → mdi-check-circle, current → mdi-play-circle, locked → mdi-lock-outline,
     overdue → mdi-alert-circle-outline;
   - тип контента → собственная иконка (video/article/pdf/quiz/file/ai);
   - активный материал — «пилюля» с акцентной шпилькой слева (см. стили ниже),
     а не серая полоса Material;
   - сохранён контракт рекурсии базового дерева: плоский список nodes + parent_id
     (source of truth сервера, blueprint Д3/Д4), кликабельны только материалы.

  Данные узла: { id, title, is_material, content_type, visited, completed,
                 locked?, lock_reason?, due_at?, duration_sec?, children?[] }.
  Поля locked/due_at появляются вместе с Drip Content (M4) — сейчас мягко
  деградируют до undefined.

  Клавиатура (Д10): тот же roving-tabindex, что в CourseTreeNode.vue; здесь
  компонент принимает tabId и эмитит set-tab, навигацию стрелками ведёт родитель.
-->
<template>
  <div
    :id="'ctnp-' + node.id"
    class="ctnp"
    :class="{
      'ctnp--material': node.is_material,
      'ctnp--group': !node.is_material,
      'ctnp--active': isActive,
      'ctnp--done': status === 'done',
      'ctnp--locked': status === 'locked',
    }"
    :role="node.is_material ? 'treeitem' : 'group'"
    :aria-level="depth + 1"
    :aria-selected="isActive || undefined"
    :aria-expanded="!node.is_material && hasChildren ? String(open) : undefined"
    :tabindex="tabId === node.id ? 0 : -1"
    @click="onClick"
    @keydown.stop="onKeydown"
  >
    <!-- строка узла: twisty | иконка типа | заголовок | meta+статус -->
    <div class="ctnp__row" :style="{ paddingLeft: 8 + depth * 14 + 'px' }">
      <button
        v-if="hasChildren"
        class="ctnp__twisty"
        tabindex="-1"
        :aria-label="open ? $t('courseManifest.collapsePanel') : $t('courseManifest.showTree')"
        @click.stop="open = !open"
      >
        <v-icon size="16">{{ open ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
      </button>
      <span v-else class="ctnp__twisty ctnp__twisty--ph" aria-hidden="true"></span>

      <v-icon v-if="node.is_material" size="17" class="ctnp__type" aria-hidden="true">
        {{ typeIcon(node.content_type) }}
      </v-icon>
      <v-icon v-else size="17" class="ctnp__type" aria-hidden="true">
        {{ open ? 'mdi-folder-open-outline' : 'mdi-folder-outline' }}
      </v-icon>

      <span class="ctnp__title text-truncate">{{ node.title }}</span>

      <span class="ctnp__meta">
        <span v-if="!cinema && node.duration_sec && node.is_material" class="ctnp__dur">
          {{ Math.round(node.duration_sec / 60) }}′
        </span>
        <!-- Статус: значок + sr-only текст. Цвет — вторичный канал, не единственный. -->
        <v-icon
          v-if="status !== 'todo'"
          size="16"
          :color="statusColor"
          class="ctnp__status"
          aria-hidden="true"
        >{{ statusIcon }}</v-icon>
        <span class="u-sr-only">{{ statusLabel }}</span>
      </span>
    </div>

    <!-- замок: причина и дата — inline, без модалки (Манифест §2.1 lock-state) -->
    <div v-if="status === 'locked' && open && node.lock_reason" class="ctnp__lockhint"
      :style="{ paddingLeft: 30 + depth * 14 + 'px' }">
      <v-icon size="12" class="mr-1" aria-hidden="true">mdi-calendar-lock-outline</v-icon>
      {{ $t(node.lock_reason) }}
    </div>

    <div v-if="!node.is_material && open && hasChildren" role="group">
      <CourseTreeNodePremium
        v-for="child in children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :active-id="activeId"
        :by-id="byId"
        :tab-id="tabId"
        :cinema="cinema"
        @select="$emit('select', $event)"
        @set-tab="$emit('set-tab', $event)"
        @nav="$emit('nav', $event)"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'CourseTreeNodePremium',
  props: {
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    activeId: { type: Number, default: null },
    tabId: { type: Number, default: null },
    byId: { type: Map, required: true },
    cinema: { type: Boolean, default: false }, // в кино-режиме прячем длительности — меньше шума
  },
  emits: ['select', 'set-tab', 'nav'],
  data() {
    return { open: true } // темы раскрыты по умолчанию (как в базовом дереве)
  },
  computed: {
    children() {
      return [...this.byId.values()].filter((n) => (n.parent_id ?? 0) === this.node.id)
    },
    hasChildren() {
      return this.children.length > 0
    },
    isActive() {
      return this.node.is_material && this.node.id === this.activeId
    },
    /** Приоритет: locked > done > current(активный непройденный) > overdue > todo. */
    status() {
      const n = this.node
      if (!n.is_material) return 'group'
      if (n.locked) return 'locked'
      if (n.completed || n.visited) return 'done'
      if (this.isActive) return 'current'
      if (n.due_at && new Date(n.due_at) < new Date()) return 'overdue'
      return 'todo'
    },
    statusIcon() {
      return {
        done: 'mdi-check-circle',
        current: 'mdi-play-circle',
        locked: 'mdi-lock-outline',
        overdue: 'mdi-alert-circle-outline',
      }[this.status] || ''
    },
    statusColor() {
      return {
        done: 'success',
        current: 'accent',
        locked: undefined, // on-surface-variant: замок не должен кричать красным
        overdue: 'warning',
      }[this.status]
    },
    statusLabel() {
      return {
        done: this.$t('coursePlayer.treeStatus.done'),
        current: this.$t('coursePlayer.treeStatus.current'),
        locked: this.$t('coursePlayer.treeStatus.locked'),
        overdue: this.$t('coursePlayer.treeStatus.overdue'),
      }[this.status] || ''
    },
  },
  watch: {
    // активный материал должен быть виден: раскрываем цепочку предков
    activeId: {
      immediate: true,
      handler(id) {
        if (id == null) return
        let cur = this.byId.get(id)
        const guard = new Set()
        while (cur) {
          if (guard.has(cur.id)) break
          guard.add(cur.id)
          if (cur.id === this.node.id && this.containsNode(this.node.id, id)) this.open = true
          cur = this.byId.get(cur.parent_id ?? 0)
        }
      },
    },
  },
  methods: {
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
    typeIcon(t) {
      return {
        video: 'mdi-play-circle-outline',
        article: 'mdi-text-long',
        html: 'mdi-file-document-outline',
        pdf: 'mdi-file-pdf-box',
        file: 'mdi-download-outline',
        quiz: 'mdi-help-circle-outline',
        ai_widget: 'mdi-robot-outline',
      }[t] || 'mdi-file-outline'
    },
    onClick() {
      if (this.node.is_material) {
        if (this.node.locked) return // замок нематериален для клика: причина уже показана
        this.$emit('select', this.node.id)
        this.$emit('set-tab', this.node.id)
      } else if (this.hasChildren) {
        this.open = !this.open
      }
    },
    /** Плоский список «как видит пользователь» — для стрелок (тот же контракт, что в базе). */
    visibleList() {
      const collect = (node) => {
        const list = [node]
        if (!node.is_material && this.open) {
          for (const c of [...this.byId.values()].filter((n) => (n.parent_id ?? 0) === node.id)) {
            list.push(...collect(c))
          }
        }
        return list
      }
      return collect(this.node)
    },
    onKeydown(e) {
      switch (e.key) {
        case 'ArrowDown':
        case 'ArrowUp': {
          e.preventDefault()
          const flat = this.visibleList()
          const idx = flat.findIndex((n) => n.id === this.node.id)
          const next = flat[idx + (e.key === 'ArrowDown' ? 1 : -1)]
          if (next) {
            this.$emit('set-tab', next.id)
            this.$emit('nav', next.id)
          }
          break
        }
        case 'ArrowRight':
          if (!this.node.is_material && this.hasChildren && !this.open) {
            e.preventDefault()
            this.open = true
          } else {
            const first = this.children[0]
            if (first) { this.$emit('set-tab', first.id); this.$emit('nav', first.id) }
          }
          break
        case 'ArrowLeft': {
          const pid = this.node.parent_id ?? 0
          if (pid && this.byId.has(pid)) {
            e.preventDefault()
            this.$emit('set-tab', pid)
            this.$emit('nav', pid)
          }
          break
        }
        case 'Home':
        case 'End': {
          e.preventDefault()
          const flat = this.visibleList()
          const target = e.key === 'Home' ? flat[0] : flat[flat.length - 1]
          if (target) { this.$emit('set-tab', target.id); this.$emit('nav', target.id) }
          break
        }
        case 'Enter':
        case ' ':
          e.preventDefault()
          this.onClick()
          break
      }
    },
  },
}
</script>

<style scoped>
/*
  Узел = строка 40px; hover — лёгкая подложка; активный — пилюля (--radius-pill)
  с акцентной шпилькой: «вы здесь» читается за 0.5с даже боковым зрением.
  Zero-elevation: никаких теней внутри drawer, только цвет-слои (Манифест §1.1.1).
*/
.ctnp { position: relative; }
.ctnp__row {
  display: flex; align-items: center; gap: 8px;
  min-height: 40px; padding-right: 8px; cursor: default;
  border-radius: var(--radius-pill);
  transition: background var(--dur-fast) ease;
}
.ctnp--material .ctnp__row { cursor: pointer; }
.ctnp--material .ctnp__row:hover { background: var(--c-highlight); }
.ctnp__row:focus-visible { outline: 2px solid var(--c-primary); outline-offset: -2px; }

.ctnp--active .ctnp__row { background: var(--c-primary-soft); }
.ctnp--active::before {
  content: ''; position: absolute; left: 2px; top: 22%; bottom: 22%; width: 3px;
  border-radius: 2px; background: var(--c-accent);
}
.ctnp--active .ctnp__title { font-weight: 600; color: var(--c-text); }

.ctnp--group > .ctnp__row .ctnp__title { font-weight: 600; font-size: var(--fs-sm); color: var(--c-text-secondary); }
.ctnp--locked .ctnp__title { color: var(--c-text-muted); }

.ctnp__twisty {
  border: 0; background: transparent; padding: 2px; cursor: pointer;
  color: var(--c-text-secondary); display: inline-flex; border-radius: 50%;
}
.ctnp__twisty--ph { width: 20px; cursor: default; }
.ctnp__type { color: var(--c-text-secondary); flex: none; }
.ctnp--active .ctnp__type { color: var(--c-accent); }
.ctnp__title { flex: 1; min-width: 0; font-size: var(--fs-base); }
.ctnp__meta { display: inline-flex; align-items: center; gap: 6px; flex: none; }
.ctnp__dur { font-size: var(--fs-2xs); color: var(--c-text-muted); font-variant-numeric: tabular-nums; }
.ctnp__lockhint {
  font-size: var(--fs-2xs); color: var(--c-text-muted);
  display: flex; align-items: center; padding-bottom: 6px;
}

@media (prefers-reduced-motion: reduce) {
  .ctnp__row { transition: none; }
}
</style>

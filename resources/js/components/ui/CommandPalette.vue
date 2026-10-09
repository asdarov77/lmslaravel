<template>
  <v-dialog
    v-model="dialog"
    max-width="var(--modal-width)"
    scrollable
    class="u-cmdk"
    @after-enter="focusInput"
  >
    <v-card class="u-cmdk__card">
      <div class="u-cmdk__input-row">
        <v-icon icon="mdi-console-line" size="20" aria-hidden="true" />
        <input
          ref="input"
          v-model="query"
          type="text"
          class="u-cmdk__input"
          :placeholder="placeholder"
          :aria-label="placeholder"
          data-test="cmdk-input"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="runActive"
          @keydown.esc.prevent="close"
        />
        <kbd class="u-cmdk__kbd">Esc</kbd>
      </div>

      <div class="u-cmdk__results">
        <p v-if="!filtered.length" class="u-cmdk__empty">{{ emptyText }}</p>

        <template v-for="group in grouped" :key="group.key">
          <h3 class="u-cmdk__group-title">{{ group.title }}</h3>
          <button
            v-for="cmd in group.items"
            :key="cmd.id"
            type="button"
            class="u-cmdk__item"
            :class="{ 'is-active': cmd.id === activeId }"
            @mouseenter="activeId = cmd.id"
            @click="run(cmd)"
          >
            <v-icon :icon="cmd.icon || 'mdi-chevron-right'" size="18" aria-hidden="true" />
            <span class="u-cmdk__item-text">
              <span class="u-cmdk__item-title">{{ cmd.title }}</span>
              <span v-if="cmd.subtitle" class="u-cmdk__item-subtitle">{{ cmd.subtitle }}</span>
            </span>
            <kbd v-if="cmd.shortcut" class="u-cmdk__kbd">{{ cmd.shortcut }}</kbd>
          </button>
        </template>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Fuse from 'fuse.js'

/**
 * Палитра команд (Ctrl/Cmd+K).
 *
 * GlobalSearch ищет по данным (курсы, пользователи), но по интерфейсу
 * всё равно приходится ходить мышью по дереву меню. CommandPalette —
 * быстрый путь: «создать курс», «перейти к экзаменам», «открыть
 * настройки» набираются с клавиатуры.
 *
 * Поиск нечёткий (fuse.js уже в проекте для GlobalSearch), поэтому
 * «курс» найдёт «Курсы» и «Создать курс», даже если набрано с опечаткой
 * или в другом порядке слов.
 *
 * Команда — объект { id, title, subtitle, icon, group, keywords,
 * action, route, shortcut }. Если задан route — выполняется переход,
 * иначе вызывается action().
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /**
   * Команды. Если не переданы — берётся список по умолчанию
   * (заглушка с навигацией приложения).
   */
  commands: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Введите команду…' },
  emptyText: { type: String, default: 'Ничего не найдено' },
  /**
   * Включить глобальный хоткей Ctrl/Cmd+K. По умолчанию выключен,
   * чтобы не конфликтовать с GlobalSearch: приложение решает само,
   * какой из двух палитр владеет этим сочетанием.
   */
  enableHotkey: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'run'])

const dialog = ref(props.modelValue)
const query = ref('')
const input = ref(null)
const activeId = ref('')

watch(() => props.modelValue, (v) => {
  dialog.value = v
  if (v) query.value = ''
})

watch(dialog, (v) => emit('update:modelValue', v))

const fuse = computed(() => new Fuse(props.commands, {
  keys: [
    { name: 'title', weight: 0.6 },
    { name: 'subtitle', weight: 0.25 },
    { name: 'keywords', weight: 0.15 },
  ],
  threshold: 0.4,
  ignoreLocation: true,
}))

const filtered = computed(() => {
  const q = query.value.trim()
  if (!q) return props.commands
  return fuse.value.search(q).map((r) => r.item)
})

const grouped = computed(() => {
  const groups = new Map()
  filtered.value.forEach((cmd) => {
    const key = cmd.group || 'Общее'
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key).push(cmd)
  })
  return Array.from(groups, ([key, items]) => ({ key, title: key, items }))
})

/** Плоский порядок — для перехода стрелками. */
const flat = computed(() => grouped.value.flatMap((g) => g.items))

watch(filtered, () => {
  activeId.value = flat.value[0]?.id ?? ''
})

function move(delta) {
  if (!flat.value.length) return
  const index = flat.value.findIndex((c) => c.id === activeId.value)
  const next = (index + delta + flat.value.length) % flat.value.length
  activeId.value = flat.value[next].id
}

function runActive() {
  const cmd = flat.value.find((c) => c.id === activeId.value) ?? flat.value[0]
  if (cmd) run(cmd)
}

function run(cmd) {
  emit('run', cmd)
  if (cmd.action) cmd.action()
  close()
}

function close() {
  dialog.value = false
}

function focusInput() {
  requestAnimationFrame(() => input.value?.focus())
}

function onHotkey(event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    dialog.value ? close() : (dialog.value = true)
  }
}

onMounted(() => {
  if (props.enableHotkey) window.addEventListener('keydown', onHotkey)
})

onBeforeUnmount(() => {
  if (props.enableHotkey) window.removeEventListener('keydown', onHotkey)
})

defineExpose({ close, open: () => (dialog.value = true) })
</script>

<style scoped>
.u-cmdk__card {
  overflow: hidden;
}

.u-cmdk__input-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border-bottom: 1px solid var(--c-border);
}

.u-cmdk__input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  font: inherit;
  font-size: var(--fs-md);
  color: var(--c-text);
}

.u-cmdk__kbd {
  padding: 2px var(--sp-2);
  border: 1px solid var(--c-border-strong);
  border-radius: var(--radius-sm);
  font-size: var(--fs-2xs);
  color: var(--c-text-muted);
  white-space: nowrap;
}

.u-cmdk__results {
  max-height: 420px;
  overflow-y: auto;
  padding: var(--sp-2);
}

.u-cmdk__empty {
  margin: 0;
  padding: var(--sp-6);
  text-align: center;
  color: var(--c-text-muted);
}

.u-cmdk__group-title {
  margin: var(--sp-2) var(--sp-2) var(--sp-1);
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--c-text-muted);
}

.u-cmdk__item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  width: 100%;
  padding: var(--sp-2) var(--sp-3);
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--c-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.u-cmdk__item.is-active {
  background: var(--c-primary-soft);
}

.u-cmdk__item-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.u-cmdk__item-title {
  font-size: var(--fs-sm);
}

.u-cmdk__item-subtitle {
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}
</style>

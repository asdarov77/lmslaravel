<template>
  <div class="u-repeatable" :class="{ 'u-repeatable--disabled': disabled }">
    <div
      v-for="(item, index) in modelValue"
      :key="keyFor(item, index)"
      class="u-repeatable__row"
      :class="{ 'is-dragging': dragIndex === index, 'is-over': overIndex === index && dragIndex !== index }"
      draggable="true"
      @dragstart="onDragStart(index)"
      @dragover.prevent="onDragOver(index)"
      @drop.prevent="onDrop(index)"
      @dragend="resetDrag"
    >
      <button
        type="button"
        class="u-repeatable__handle"
        :aria-label="dragLabel"
        aria-roledescription="Перетаскивание"
        @keydown.up.prevent="move(index, index - 1)"
        @keydown.down.prevent="move(index, index + 1)"
      >
        <v-icon icon="mdi-drag-vertical" size="18" aria-hidden="true" />
      </button>

      <div class="u-repeatable__body">
        <slot
          :item="item"
          :index="index"
          :replace="(value) => replace(index, value)"
          :remove="() => remove(index)"
          :duplicate="() => duplicate(index)"
        />
      </div>

      <div class="u-repeatable__controls">
        <v-btn
          icon="mdi-content-duplicate"
          variant="text"
          size="small"
          :disabled="disabled"
          :aria-label="duplicateLabel"
          type="button"
          @click="duplicate(index)"
        />
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          :disabled="disabled || (modelValue.length <= min)"
          :aria-label="removeLabel"
          type="button"
          @click="remove(index)"
        />
      </div>
    </div>

    <v-btn
      variant="text"
      prepend-icon="mdi-plus"
      type="button"
      :disabled="disabled || modelValue.length >= max"
      @click="add"
    >
      {{ addLabel }}
    </v-btn>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { cloneItem, moveItem, removeAt } from '../../../utils/array'

/**
 * Повторяемые поля (варианты ответа, расписание).
 *
 * Зачем: списки добавлялись кнопкой «+», но переставить или удалить
 * строку было нельзя, а дубликат делил ссылку с оригиналом (правка
 * одного меняла оба). Здесь: immutable-операции над массивом,
 * drag&drop и перестановка стрелками с клавиатуры.
 *
 * `blank` — шаблон новой строки (объект или функция).
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  blank: { type: [Object, Function], default: () => ({}) },
  addLabel: { type: String, default: 'Добавить строку' },
  dragLabel: { type: String, default: 'Перетащите, чтобы изменить порядок' },
  removeLabel: { type: String, default: 'Удалить строку' },
  duplicateLabel: { type: String, default: 'Дублировать строку' },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const dragIndex = ref(-1)
const overIndex = ref(-1)

const keyFor = (item, index) => item?.id ?? item?.key ?? index

const makeBlank = () => (typeof props.blank === 'function' ? props.blank() : cloneItem(props.blank))

const commit = (next) => emit('update:modelValue', next)

const add = () => {
  if (props.modelValue.length >= props.max) return
  commit([...props.modelValue, makeBlank()])
}

const remove = (index) => {
  if (props.modelValue.length <= props.min) return
  commit(removeAt(props.modelValue, index))
}

const duplicate = (index) => {
  if (props.modelValue.length >= props.max) return
  const copy = cloneItem(props.modelValue[index])
  if (copy && typeof copy === 'object') {
    // Дубликат не должен нести id оригинала — иначе бэкенд примет его
    // за существующую запись.
    delete copy.id
  }
  const next = props.modelValue.slice()
  next.splice(index + 1, 0, copy)
  commit(next)
}

const replace = (index, value) => {
  const next = props.modelValue.slice()
  next[index] = value
  commit(next)
}

const move = (from, to) => {
  if (to < 0 || to >= props.modelValue.length) return
  commit(moveItem(props.modelValue, from, to))
}

const onDragStart = (index) => {
  dragIndex.value = index
}

const onDragOver = (index) => {
  overIndex.value = index
}

const onDrop = (index) => {
  if (dragIndex.value > -1 && dragIndex.value !== index) move(dragIndex.value, index)
  resetDrag()
}

const resetDrag = () => {
  dragIndex.value = -1
  overIndex.value = -1
}
</script>

<style scoped>
.u-repeatable {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.u-repeatable__row {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  padding: var(--sp-2);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  background: var(--c-surface-2);
  transition: border-color var(--dur-fast) var(--ease), opacity var(--dur-fast) var(--ease);
}

.u-repeatable__row.is-dragging {
  opacity: 0.5;
}

.u-repeatable__row.is-over {
  border-color: var(--c-accent);
}

.u-repeatable__handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 40px;
  border: none;
  background: transparent;
  color: var(--c-text-muted);
  cursor: grab;
}

.u-repeatable__handle:focus-visible {
  outline: 2px solid var(--c-accent);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

.u-repeatable__body {
  flex: 1 1 auto;
  min-width: 0;
}

.u-repeatable__controls {
  display: flex;
  align-items: center;
}
</style>

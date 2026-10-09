<template>
  <div class="u-dropzone">
    <div
      class="u-dropzone__area"
      :class="{ 'is-dragging': dragging, 'is-disabled': disabled }"
      role="button"
      tabindex="0"
      @click="openPicker"
      @keydown.enter.prevent="openPicker"
      @keydown.space.prevent="openPicker"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <v-icon class="u-dropzone__icon" :icon="dragging ? 'mdi-tray-arrow-down' : 'mdi-cloud-upload-outline'" size="40" aria-hidden="true" />
      <p class="u-dropzone__title">{{ dragging ? 'Отпустите файлы' : title }}</p>
      <p class="u-dropzone__hint">{{ hint || acceptHint }}</p>

      <input
        ref="input"
        class="u-dropzone__input"
        type="file"
        :accept="accept"
        :multiple="multiple"
        @change="onPick"
      />
    </div>

    <!-- Очередь загрузки. Полосу и статус каждая задача знает от
         движка chunkUpload: компонент только рисует. -->
    <ul v-if="tasks.length" class="u-dropzone__list">
      <li
        v-for="task in tasks"
        :key="task.id"
        class="u-dropzone__item"
        :class="`is-${task.status}`"
      >
        <v-icon class="u-dropzone__item-icon" :icon="iconFor(task)" size="20" aria-hidden="true" />

        <div class="u-dropzone__item-main">
          <div class="u-dropzone__item-top">
            <span class="u-dropzone__item-name u-truncate">{{ task.name }}</span>
            <span class="u-dropzone__item-size">{{ sizeLabel(task) }}</span>
          </div>

          <v-progress-linear
            v-if="task.status === 'uploading' || task.status === 'queued'"
            :model-value="percent(task)"
            height="4"
            rounded
            color="primary"
            class="u-dropzone__progress"
          />
          <p v-else-if="task.status === 'error'" class="u-dropzone__item-error">
            {{ task.error || 'Ошибка загрузки' }}
          </p>
        </div>

        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          :aria-label="`Убрать ${task.name}`"
          @click="remove(task)"
        />
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { STATUS } from '../../services/chunkUpload'

/**
 * Зона загрузки файлов с drag&drop.
 *
 * Раньше загрузка была отдельной страницей файлового менеджера: чтобы
 * приложить обложку к курсу или работу к заданию, пользователь уходил
 * из формы, терял заполненное и возвращался. Dropzone принимает файлы
 * там, где они нужны, и показывает прогресс по каждому.
 *
 * Сама отправка — не здесь: движок chunkUpload режет файл на части,
 * докачивает и повторяет при обрыве. Компонент только перетаскивает
 * файлы в очередь и рисует её состояние — поэтому загрузка больших
 * файлов работает так же, как в файловом менеджере.
 */
const props = defineProps({
  /**
   * Очередь из createUploadQueue() с РЕАКТИВНЫМ массивом tasks.
   * Движок пишет в переданный массив; если передать сырой, интерфейс
   * не увидит прогресс (см. комментарий в chunkUpload.js).
   */
  queue: { type: Object, required: true },
  title: { type: String, default: 'Перетащите файлы сюда или нажмите' },
  hint: { type: String, default: '' },
  /** Как у input accept: ".pdf,.doc" или "application/pdf". */
  accept: { type: String, default: '' },
  multiple: { type: Boolean, default: true },
  /** Папка файлового менеджера, если файлы кладутся в дерево. */
  folderId: { type: [Number, String], default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['added', 'completed'])

const input = ref(null)
const dragging = ref(false)
let dragDepth = 0

const tasks = computed(() => props.queue.tasks)

const acceptHint = computed(() => {
  if (!props.accept) return 'Любой формат'
  return `Форматы: ${props.accept.replace(/\./g, '').replace(/,/g, ', ')}`
})

function openPicker() {
  if (props.disabled) return
  input.value?.click()
}

function onPick(event) {
  handleFiles(event.target.files)
  event.target.value = ''
}

function onDragLeave() {
  dragDepth -= 1
  if (dragDepth <= 0) {
    dragDepth = 0
    dragging.value = false
  }
}

function onDrop(event) {
  dragDepth = 0
  dragging.value = false
  if (props.disabled) return
  handleFiles(event.dataTransfer?.files)
}

function handleFiles(fileList) {
  const files = Array.from(fileList || [])
  if (files.length === 0) return

  const added = props.queue.addAll(files, { folderId: props.folderId })

  // Движок сообщает только через onChange; завершение ловим
  // подпиской на изменение статуса уже готовой задачи.
  added.forEach((task) => watchTask(task))

  emit('added', added)
}

function watchTask(task) {
  const stop = setInterval(() => {
    if (task.status === STATUS.DONE) {
      clearInterval(stop)
      emit('completed', task)
    } else if (task.status === STATUS.ERROR || task.status === STATUS.CANCELLED) {
      clearInterval(stop)
    }
  }, 400)
}

function remove(task) {
  props.queue.remove(task)
}

function percent(task) {
  if (!task.size) return 0
  return Math.min(100, Math.round((task.sent / task.size) * 100))
}

function iconFor(task) {
  if (task.status === STATUS.DONE) return 'mdi-check-circle'
  if (task.status === STATUS.ERROR) return 'mdi-alert-circle'
  if (task.status === STATUS.CANCELLED) return 'mdi-cancel'
  return 'mdi-file-upload-outline'
}

function sizeLabel(task) {
  const bytes = task.size || 0
  if (bytes < 1024) return `${bytes} Б`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} КБ`
  return `${(bytes / (1024 * 1024)).toFixed(1)} МБ`
}
</script>

<style scoped>
.u-dropzone {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.u-dropzone__area {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-8) var(--sp-4);
  border: 2px dashed var(--c-border-strong);
  border-radius: var(--radius-lg);
  background: var(--c-surface-2);
  text-align: center;
  cursor: pointer;
  transition: border-color var(--dur-base) var(--ease), background-color var(--dur-base) var(--ease);
}

.u-dropzone__area:hover,
.u-dropzone__area.is-dragging {
  border-color: var(--c-primary);
  background: var(--c-primary-soft);
}

.u-dropzone__area.is-disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.u-dropzone__icon {
  color: var(--c-primary);
}

.u-dropzone__title {
  margin: 0;
  font-size: var(--fs-base);
  font-weight: var(--fw-medium);
  color: var(--c-text);
}

.u-dropzone__hint {
  margin: 0;
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}

.u-dropzone__input {
  display: none;
}

.u-dropzone__list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.u-dropzone__item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2) var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  background: var(--c-surface);
}

.u-dropzone__item.is-done .u-dropzone__item-icon { color: var(--c-success); }
.u-dropzone__item.is-error .u-dropzone__item-icon { color: var(--c-danger); }
.u-dropzone__item.is-uploading .u-dropzone__item-icon,
.u-dropzone__item.is-queued .u-dropzone__item-icon { color: var(--c-primary); }

.u-dropzone__item-main {
  flex: 1;
  min-width: 0;
}

.u-dropzone__item-top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--sp-2);
}

.u-dropzone__item-name {
  font-size: var(--fs-sm);
  color: var(--c-text);
}

.u-dropzone__item-size {
  font-size: var(--fs-2xs);
  color: var(--c-text-muted);
  white-space: nowrap;
}

.u-dropzone__progress {
  margin-top: var(--sp-1);
}

.u-dropzone__item-error {
  margin: var(--sp-1) 0 0;
  font-size: var(--fs-xs);
  color: var(--c-danger);
}
</style>

<!--
  CoursePlayerToolbar — компактная панель над кадром: статус загрузки,
  счётчик подсветки поиска с ▲▼ (Д5), полноэкранный режим через Fullscreen API (Д10),
  прогресс курса.
-->
<template>
  <div class="cpt" role="toolbar" aria-label="Панель инструментов плеера">
    <span v-if="loading" class="cpt__status" aria-live="polite">Загрузка материала…</span>
    <span v-else-if="failed" class="cpt__status cpt__status--error">Не удалось загрузить материал</span>

    <v-spacer />

    <template v-if="hasMarks">
      <v-btn-group dense aria-label="Навигация по совпадениям">
        <v-btn size="small" @click="$emit('prev-mark')" :aria-label="'Предыдущее совпадение'">▲</v-btn>
        <v-btn size="small" @click="$emit('next-mark')" :aria-label="'Следующее совпадение'">▼</v-btn>
      </v-btn-group>
      <span class="cpt__counter" aria-live="polite">{{ counterLabel }}</span>
    </template>

    <v-btn icon size="small" :aria-label="isFullscreen ? 'Выйти из полноэкранного режима' : 'Полноэкранный режим'"
      @click="toggleFullscreen">
      <v-icon size="20">{{ isFullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen' }}</v-icon>
    </v-btn>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  container: { type: Object, default: null }, // HTMLElement для Fullscreen API
  loading: Boolean,
  failed: Boolean,
  hasMarks: Boolean,
  counterLabel: { type: String, default: '' },
})
defineEmits(['prev-mark', 'next-mark'])

const isFullscreen = ref(false)

function onFsChange() {
  isFullscreen.value = !!document.fullscreenElement
}

function toggleFullscreen() {
  const el = props.container || document.getElementById('course-player-root')
  if (!el) return
  if (document.fullscreenElement) document.exitFullscreen().catch(() => {})
  else el.requestFullscreen?.().catch(() => {})
}

onMounted(() => document.addEventListener('fullscreenchange', onFsChange))
onBeforeUnmount(() => document.removeEventListener('fullscreenchange', onFsChange))
</script>

<style scoped>
.cpt {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  min-height: 40px;
}
.cpt__status {
  font-size: 13px;
  color: rgba(0, 0, 0, 0.65);
}
.cpt__status--error {
  color: #c62828;
}
.cpt__counter {
  font-variant-numeric: tabular-nums;
  font-size: 13px;
  min-width: 40px;
  text-align: center;
}
</style>

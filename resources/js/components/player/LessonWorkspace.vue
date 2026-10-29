<!--
  LessonWorkspace — рабочая область урока плеера v2 (S1, blueprint §2.2).

  Контракт: компонент ПЕРЕЖИВАЕТ смену урока (его не перемонтируют), но
  внутренняя <iframe> пересоздаётся по :key="epoch" — epoch инкрементирует
  только победитель гонки в useLessonLoader. Это гарантирует полное
  уничтожение DOM/слушателей старого рендера без ручных reset-вызовов.

  Данные приходят через props от Pages/CoursePlayer.vue (URL-as-truth):
  loader уже выполнил detail → документ/подпись и выставил статус.
  Здесь только отображение + хелперы соседнего урока (prev/next).
-->
<template>
  <section class="lw" :aria-busy="status === 'loading' ? 'true' : 'false'" role="region" :aria-label="lesson?.title || ''">
    <!-- Заголовок + статусная строка -->
    <header class="lw__head">
      <h2 class="lw__title text-h6 mb-0">{{ lesson?.title || '—' }}</h2>
      <div class="lw__nav">
        <v-btn size="small" variant="text" icon="mdi-chevron-left" :disabled="!prevId"
          :aria-label="$t('courseManifest.prevLesson')" @click="go(prevId)" />
        <v-btn size="small" variant="text" icon="mdi-chevron-right" :disabled="!nextId"
          :aria-label="$t('courseManifest.nextLesson')" @click="go(nextId)" />
      </div>
    </header>

    <!-- Состояние: загрузка -->
    <div v-if="status === 'loading'" class="lw__state" data-test="lw-loading">
      <v-progress-circular indeterminate size="32" />
      <span class="ml-3">{{ $t('coursePlayer.loading') }}</span>
    </div>

    <!-- Состояние: доступ закрыт (403 / access.allowed === false) -->
    <v-alert v-else-if="status === 'locked'" type="warning" variant="tonal" data-test="lw-locked" icon="mdi-lock-outline">
      {{ $t(errorKey || 'coursePlayer.locked') }}
    </v-alert>

    <!-- Состояние: ошибка + повтор -->
    <v-alert v-else-if="status === 'error'" type="error" variant="tonal" data-test="lw-error">
      <div>{{ $t(errorKey || 'coursePlayer.loadError') }}</div>
      <v-btn size="small" variant="text" prepend-icon="mdi-refresh" class="mt-2" @click="$emit('retry')">
        {{ $t('courseManifest.retry') }}
      </v-btn>
    </v-alert>

    <!-- Готово: контент по типу -->
    <template v-else-if="status === 'ready' && lesson">
      <!-- html/article: iframe-кадр, пересоздаётся по epoch (remount-key) -->
      <iframe
        v-if="isFrameContent"
        :key="epoch"
        ref="frameEl"
        class="lw__frame"
        :src="frameSrc"
        :style="{ height: frameHeightPx }"
        title=""
        data-test="lw-frame"
        @load="onFrameLoad"
      />

      <!-- video/pdf/file: подписанная доставка (S2) -->
      <div v-else-if="lesson.delivery?.url" class="lw__media" data-test="lw-media">
        <video v-if="lesson.content_type === 'video'" controls :src="lesson.delivery.url" class="lw__video" />
        <object v-else :data="lesson.delivery.url" type="application/pdf" class="lw__pdf">
          <v-btn variant="text" :href="lesson.delivery.url" target="_blank" rel="noopener">
            {{ $t('coursePlayer.openFile') }}
          </v-btn>
        </object>
      </div>

      <!-- Тип контента ещё не доставляется сервером (501 до S2) -->
      <div v-else class="lw__state" data-test="lw-empty">
        {{ $t('coursePlayer.contentTypePending') }}
      </div>
    </template>

    <!-- idle: урок не выбран -->
    <div v-else class="lw__state" data-test="lw-idle">
      {{ $t('coursePlayer.selectLesson') }}
    </div>
  </section>
</template>

<script>
import { contentFrameUrl } from '../../api/player.api'

export default {
  name: 'LessonWorkspace',
  props: {
    courseId: { type: [Number, String], required: true },
    // payload урока из useLessonLoader (shallowRef.value)
    lesson: { type: Object, default: null },
    status: { type: String, default: 'idle' }, // idle | loading | ready | error | locked
    errorKey: { type: String, default: null },
    // remount-счётчик победителя гонки — вешается на :key кадра
    epoch: { type: Number, default: 0 },
    materialIds: { type: Array, default: () => [] },
    currentItemId: { type: Number, default: null },
  },
  emits: ['retry', 'select', 'frame-ready'],
  data() {
    return {
      // fit-режим высоты: авто по содержимому после load (best-effort, same-origin)
      autoHeight: 480,
    }
  },
  computed: {
    isFrameContent() {
      const t = this.lesson?.content_type
      return t === 'html' || t === 'article' || !t
    },
    frameSrc() {
      if (!this.isFrameContent || !this.lesson) return ''
      // docCache уже кладёт documentHtml в payload; src остаётся серверным URL —
      // iframe сам авторизуется через ?t= (см. player.api.contentFrameUrl).
      return contentFrameUrl(this.courseId, this.lesson.id)
    },
    frameHeightPx() {
      return `${this.autoHeight}px`
    },
    prevId() {
      return this.neighborId(-1)
    },
    nextId() {
      return this.neighborId(1)
    },
  },
  watch: {
    // при смене урока высота сбрасывается до базовой до onLoad нового кадра
    epoch() {
      this.autoHeight = 480
    },
  },
  methods: {
    neighborId(step) {
      const ids = this.materialIds.map(Number)
      const i = ids.indexOf(Number(this.currentItemId))
      if (i === -1) return null
      const n = ids[i + step]
      return n ?? null
    },
    go(id) {
      if (id != null) this.$emit('select', id)
    },
    onFrameLoad(e) {
      try {
        const doc = e.target.contentDocument
        if (doc?.body) {
          this.autoHeight = Math.min(8000, Math.max(480, doc.body.scrollHeight + 24))
        }
      } catch (_) {
        /* кросс-домен — оставляем базовую высоту */
      }
      this.$emit('frame-ready', e.target)
    },
  },
}
</script>

<style scoped>
.lw { display: flex; flex-direction: column; gap: 8px; min-height: 300px; }
.lw__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.lw__title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lw__state { padding: 24px; text-align: center; color: rgba(0, 0, 0, 0.6); }
.lw__frame { width: 100%; border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 8px; background: #fff; }
.lw__media { width: 100%; }
.lw__video { width: 100%; max-height: 70vh; background: #000; border-radius: 8px; }
.lw__pdf { width: 100%; height: 70vh; border: 0; }
</style>

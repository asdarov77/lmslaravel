<!--
  CoursePlayerPremium — референсный шаблон «Плеера 2.0» (Дизайн-Манифест §2.1–2.2, Часть 3 запроса).

  Демонстрирует: cinema/focus mode, кастомное дерево уроков со статусами и иконками
  типов контента, TutorDock с context-pill, premium-стилизация на токенах design-system
  (resources/css/tokens.css) вместо коробочного Material-elevation.

  Контракты сознательно совпадают с уже живущими в репозитории:
   - props/эмиты LessonWorkspace.vue (courseId, lesson, status, errorKey, epoch, …);
   - URL-as-truth (?item=) и useCoursePlayer/useLessonLoader из Pages/CoursePlayer.vue;
   - узлы дерева: { id, title, is_material, content_type, status: done|active|locked|overdue,
     lock_reason, duration_sec, children[] } — контракт bootstrap-эндпоинта
     GET /api/course/{id}/player (docs/course-player-blueprint.md §2.1).

  Статус: UI-референс для M1 (design-manifest §3). Интеграция = заменить тонкую
  разметку Pages/CoursePlayer.vue на эту композицию под флагом ui.course_player_v2.
  Никаких hex-цветов в шаблонах (правило lint-design-pillars): только var(--c-*) и тема Vuetify.
-->
<template>
  <div class="cpp" :class="{ 'cpp--cinema': cinemaMode }">

    <!-- live-region: смена урока озвучивается AT без визуального шума (§Манифест 1.4) -->
    <span class="u-sr-only" role="status" aria-live="polite">{{ lessonAnnounce }}</span>

    <!-- ══════════ TOP BAR: 56px, progress встроен в нижнюю кромку («нет-фреймовая» строка) ══════════ -->
    <header class="cpp__bar" v-show="!cinemaMode || barPeek">
      <v-btn
        icon="mdi-arrow-left" variant="text" size="small" class="ml-1"
        :aria-label="$t('courseManifest.backToCourses')" @click="goBack"
      />
      <div class="cpp__crumbs text-truncate">
        <span class="cpp__crumb-course text-truncate">{{ courseTitle }}</span>
        <v-icon size="12" class="mx-1 cpp__crumb-sep" aria-hidden="true">mdi-chevron-right</v-icon>
        <span class="cpp__crumb-module text-truncate">{{ activeModuleTitle }}</span>
      </div>

      <v-spacer />

      <!-- Геймификация: стрик рядом с прогрессом — «не бросай» без давления -->
      <v-chip v-if="streak > 0" size="small" variant="flat" class="cpp__streak mr-2" label>
        <v-icon start size="14">mdi-fire</v-icon>{{ streak }}
        <span class="u-sr-only">{{ $t('gamification.streakAria', { days: streak }) }}</span>
      </v-chip>

      <div class="cpp__progress d-none d-sm-flex align-center mr-3">
        <v-progress-circular
          :model-value="stats.percent" size="26" width="3" color="accent"
          :aria-label="$t('courseManifest.progressAria', { percent: stats.percent })"
        >
          <span class="cpp__progress-num">{{ stats.percent }}</span>
        </v-progress-circular>
      </div>

      <v-btn icon variant="text" size="small" :aria-label="$t('coursePlayer.search')" @click="searchOpen = !searchOpen">
        <v-icon>mdi-magnify</v-icon>
      </v-btn>
      <v-btn
        icon variant="text" size="small" data-test="btn-cinema"
        :aria-pressed="String(cinemaMode)"
        :aria-label="cinemaMode ? $t('coursePlayer.exitCinema') : $t('coursePlayer.enterCinema')"
        @click="toggleCinema"
      >
        <v-icon>{{ cinemaMode ? 'mdi-fullscreen-exit' : 'mdi-fullscreen' }}</v-icon>
      </v-btn>

      <!-- тонкая линия остатка курса по нижней кромке панели -->
      <div class="cpp__barline" aria-hidden="true">
        <v-progress-linear
          :model-value="stats.percent" height="2" color="accent"
          background-color="transparent" rounded
        />
      </div>
    </header>

    <!-- Поиск по курсу — overlay, не отдельный экран (deep-link: урок#фрагмент) -->
    <v-expand-transition>
      <div v-if="searchOpen" class="cpp__search pa-2">
        <v-text-field
          v-model="query" autofocus density="compact" variant="outlined"
          append-icon="mdi-close" :loading="searching" hide-details
          :label="$t('coursePlayer.searchPlaceholder')"
          @click:append="searchOpen = false"
        />
      </div>
    </v-expand-transition>

    <!-- ══════════ BODY: drawer ↔ workspace ↔ tutor dock ══════════ -->
    <div class="cpp__body">

      <!-- ─── Левое дерево курса ─── -->
      <v-navigation-drawer
        v-model="treeOpen" :rail="cinemaMode || railMode" width="320" rail-width="56"
        :temporary="$display.width < 768" elevation="0"
        class="cpp__tree" :aria-label="$t('courseManifest.tree')"
      >
        <!-- Шапка drawer: ring прогресса + sticky-заголовок активного модуля -->
        <div class="cpp__tree-head">
          <div class="d-flex align-center ga-3 px-3 pt-3 pb-2">
            <v-progress-circular
              v-if="!railMode && !cinemaMode"
              :model-value="moduleStats.done" :max="moduleStats.total"
              size="34" width="3" color="primary"
            >
              <span class="text-caption font-weight-medium">{{ moduleStats.done }}</span>
            </v-progress-circular>
            <div v-if="!railMode && !cinemaMode" class="cpp__tree-title">
              <div class="text-subtitle-2 text-truncate">{{ $t('coursePlayer.contents') }}</div>
              <div class="text-caption u-muted text-truncate">
                {{ $t('coursePlayer.itemsDone', { done: stats.done, total: stats.total }) }}
              </div>
            </div>
            <v-spacer v-if="!railMode && !cinemaMode" />
            <v-btn
              v-if="!cinemaMode" icon variant="text" size="x-small" class="d-none d-md-inline-flex"
              :aria-label="railMode ? $t('coursePlayer.expandTree') : $t('coursePlayer.collapseTree')"
              @click="railMode = !railMode"
            >
              <v-icon>{{ railMode ? 'mdi-backup-restore' : 'mdi-minus-circle-outline' }}</v-icon>
            </v-btn>
          </div>
          <!-- resume-hero: «Продолжить с …» прямо над деревом -->
          <v-card
            v-if="resumeCard && !railMode && !cinemaMode" variant="flat" rounded="lg"
            class="cpp__resume ma-3 pa-3" data-test="resume-hero"
            @click="select(resumeCard.id)"
          >
            <div class="d-flex align-center ga-3">
              <v-avatar color="primary-soft" size="40"><v-icon color="primary">mdi-play</v-icon></v-avatar>
              <div class="text-body-2 font-weight-medium u-truncate">{{ resumeCard.title }}</div>
            </div>
            <v-progress-linear class="mt-2" :model-value="resumeCard.percent" height="4" color="accent" rounded />
          </v-card>
        </div>

        <v-divider aria-hidden="true" />

        <!-- Рекурсивные узлы: тип контента → иконка, статус → значок + aria.
             byId строится из плоского списка (контракт nodes+parent_id). -->
        <div v-bind="treeProps" @keydown="onTreeNav">
          <CourseTreeNodePremium
            v-for="node in rootNodes" :key="node.id"
            :node="node" :depth="0" :active-id="currentItemId"
            :by-id="byId" :tab-id="treeTabId" :cinema="cinemaMode"
            @select="select" @set-tab="treeTabId = $event" @nav="focusTreeNode"
          />
          <AppSkeleton v-if="loading" variant="text" :count="8" class="pa-3" />
        </div>
      </v-navigation-drawer>

      <!-- scrim для overlay-дерева в cinema-mode: hover у левого края высвобождает дерево -->
      <div
        v-if="cinemaMode" class="cpp__edge-trigger" aria-hidden="true"
        @mouseenter="barPeek = true" @mouseleave="barPeek = false"
      />

      <!-- ─── Рабочая область ─── -->
      <main class="cpp__main" :aria-busy="status === 'loading' ? 'true' : 'false'">
        <transition name="cpp-fade" mode="out-in">
          <!-- :key=epoch — remount победителем гонки (useLessonLoader), старый DOM умирает целиком -->
          <section :key="epoch" class="cpp__stage">

            <!-- Заголовок урока + мета (кегель — основной декор, §Манифест 1.1.2) -->
            <div class="cpp__lesson-head" v-if="lesson && !cinemaMode">
              <h1 class="cpp__lesson-title">{{ lesson.title }}</h1>
              <div class="d-flex align-center ga-3 mt-1 text-caption u-muted">
                <span><v-icon size="14" class="mr-1">{{ typeIcon(lesson.content_type) }}</v-icon>{{ typeName(lesson.content_type) }}</span>
                <span v-if="lesson.duration_sec">~{{ Math.round(lesson.duration_sec / 60) }} мин</span>
                <v-chip v-if="lesson.due_at" size="x-small" :color="isOverdue(lesson) ? 'error' : 'warning'" variant="flat" label>
                  <v-icon start size="12">mdi-calendar-clock</v-icon>{{ formatDate(lesson.due_at) }}
                </v-chip>
              </div>
            </div>

            <!-- Состояние: загрузка — скелетон ПОД тип контента, а не generic-спиннер -->
            <div v-if="status === 'loading'" class="cpp__state" role="status" aria-live="polite">
              <AppSkeleton :variant="skeletonFor(lesson?.content_type)" />
              <div class="text-caption u-muted mt-2">{{ $t('coursePlayer.loadingType', { type: typeName(lesson?.content_type) }) }}</div>
            </div>

            <!-- Состояние: замок (drip/prerequisite) — empty-state с ПРИЧИНОЙ и датой -->
            <div v-else-if="status === 'locked'" class="cpp__state cpp__state--locked">
              <v-card variant="flat" rounded="xl" class="cpp__lockcard pa-8 text-center">
                <v-avatar color="surface-3" size="64" class="mb-4"><v-icon size="34" color="on-surface-variant">mdi-lock-outline</v-icon></v-avatar>
                <div class="text-h6 mb-1">{{ $t('coursePlayer.lockedTitle') }}</div>
                <div class="text-body-2 u-muted mb-4">{{ $t(errorKey || 'coursePlayer.lockedDefault') }}</div>
                <v-btn variant="tonal" rounded="pill" prepend-icon="mdi-check-circle-outline" @click="select(prevId)">
                  {{ $t('coursePlayer.goToPrevious') }}
                </v-btn>
              </v-card>
            </div>

            <!-- Состояние: ошибка + retry (конвенция 4-состояний экрана) -->
            <div v-else-if="status === 'error'" class="cpp__state" role="alert">
              <v-alert type="error" variant="tonal" rounded="lg" class="mx-auto" style="max-width: 560px">
                <div>{{ $t(errorKey || 'coursePlayer.loadError') }}</div>
                <v-btn size="small" variant="text" prepend-icon="mdi-refresh" class="mt-2" @click="$emit('retry')">
                  {{ $t('courseManifest.retry') }}
                </v-btn>
              </v-alert>
            </div>

            <!-- Готово: диспетчер рендеров. Здесь показаны video/quiz; полный набор — LessonWorkspace.vue -->
            <template v-else-if="status === 'ready' && lesson">

              <!-- ВИДЕО: poster → reserving (X-Accel) → streaming. Signed-url НЕ перевыпускается при перемотке -->
              <div v-if="lesson.content_type === 'video'" class="cpp__media" :data-phase="videoPhase">
                <div class="cpp__video-shell">
                  <video
                    ref="videoEl" :src="lesson.delivery?.url" :poster="lesson.poster_url"
                    controlslist="nodownload" preload="metadata"
                    @waiting="videoPhase = 'buffering'" @playing="videoPhase = 'playing'"
                    @timeupdate="onTick"
                  />
                  <transition name="cpp-fade">
                    <div v-if="videoPhase !== 'playing'" class="cpp__video-veil">
                      <v-progress-circular indeterminate size="34" width="3" color="accent" />
                      <div class="text-caption mt-2">
                        {{ videoPhase === 'reserving' ? $t('coursePlayer.reservingAccess') : $t('coursePlayer.buffering') }}
                      </div>
                    </div>
                  </transition>
                </div>
                <!-- заметки, привязанные к таймкоду (G6): клик = seek -->
                <div v-if="notes.length && !cinemaMode" class="cpp__notes d-flex flex-wrap ga-2 mt-3">
                  <v-chip v-for="n in notes" :key="n.id" size="small" variant="outlined" label
                    prepend-icon="mdi-bookmark-outline" @click="seek(n.ts_offset)">
                    {{ formatTs(n.ts_offset) }} · {{ n.text.slice(0, 32) }}
                  </v-chip>
                </div>
              </div>

              <!-- ТЕСТ внутри плеера: verdict приходит сервером; pass → FLIP ✓ в дерево (§Манифест 2.2) -->
              <div v-else-if="lesson.content_type === 'quiz'" class="cpp__quiz">
                <InlineQuizRunner :schema="lesson.payload" @submit="onQuizSubmit" />
                <transition name="cpp-pop">
                  <div v-if="quizVerdict === 'pass'" class="cpp__success" role="status">
                    <v-overlay v-model="celebrate" contained persistent class="align-center justify-center">
                      <div class="cpp__success-card text-center pa-8">
                        <v-progress-circular :model-value="100" :size="72" :width="5" color="success" />
                        <div class="text-h5 mt-4">{{ $t('coursePlayer.quizPassed') }}</div>
                        <div class="text-body-2 u-muted mt-1">{{ $t('coursePlayer.xpStreak', { xp: quizVerdictXp, streak: streak + 1 }) }}</div>
                        <v-btn class="mt-5" color="primary" rounded="pill" size="large" append-icon="mdi-arrow-right" @click="go(nextId)">
                          {{ $t('courseManifest.nextLesson') }}
                        </v-btn>
                      </div>
                    </v-overlay>
                  </div>
                </transition>
              </div>

              <!-- html/article/pdf/file — нативные рендереры поверх существующего LessonWorkspace -->
              <component
                v-else :is="workspaceComponent"
                :course-id="courseId" :lesson="lesson" :status="status"
                :epoch="epoch" @retry="$emit('retry')" @select="select"
              />
            </template>

            <div v-else class="cpp__state">
              <EmptyState icon="mdi-play-box-outline" :title="$t('coursePlayer.selectLesson')" />
            </div>
          </section>
        </transition>

        <!-- Bottom CTA-панель: desktop — плавающая пилюля; mobile — фикс-бар во всю ширину -->
        <transition name="cpp-slide-up">
          <div class="cpp__cta" :class="{ 'cpp__cta--idle': controlsIdle }" v-show="!cinemaMode || barPeek">
            <v-btn variant="text" icon="mdi-chevron-left" :disabled="!prevId"
              :aria-label="$t('courseManifest.prevLesson')" @click="go(prevId)" />
            <v-divider vertical class="mx-1" />
            <v-btn
              :color="lessonCompleted ? 'success' : 'primary'" rounded="pill" size="large"
              :loading="completing" class="cpp__cta-main px-6"
              :prepend-icon="lessonCompleted ? 'mdi-check-circle' : 'mdi-check'"
              @click="completeLesson"
            >
              {{ lessonCompleted ? $t('coursePlayer.completed') : $t('coursePlayer.markComplete') }}
            </v-btn>
            <v-divider vertical class="mx-1" />
            <v-btn variant="text" icon="mdi-chevron-right" :disabled="!nextId"
              :aria-label="$t('courseManifest.nextLesson')" @click="go(nextId)" />
          </div>
        </transition>
      </main>

      <!-- ─── AI-Tutor Dock (overlay, layout не толкает) ─── -->
      <aside v-if="tutorOpen" class="cpp__tutor" aria-label="AI Tutor">
        <div class="cpp__tutor-head d-flex align-center pa-3">
          <v-avatar color="surface-3" size="32" icon="mdi-robot-outline" />
          <span class="text-subtitle-2 ml-2">AI&nbsp;Tutor</span>
          <v-spacer />
          <v-btn icon variant="text" size="small" aria-label="Close tutor" @click="tutorOpen = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </div>

        <!-- Context pill: явная маркировка подмешанного фрагмента урока -->
        <div v-if="tutorContext" class="px-3">
          <v-chip closable color="accent" variant="flat" size="small" class="cpp__ctx-pill"
            prepend-icon="mdi-file-document-edit-outline" @click:close="tutorContext = null"
            @click="scrollToQuote(tutorContext)">
            <span class="u-truncate" style="max-width: 24ch">{{ tutorContext.excerpt }}</span>
          </v-chip>
        </div>

        <!-- Подсказки трёх уровней — segmented control, уровень → telemetry -->
        <v-btn-toggle v-model="hintLevel" density="compact" mandatory class="ma-3 w-100" color="primary" divided>
          <v-btn value="nudge" size="small">{{ $t('tutor.hintNudge') }}</v-btn>
          <v-btn value="partial" size="small">{{ $t('tutor.hintPartial') }}</v-btn>
          <v-btn value="full" size="small">{{ $t('tutor.hintFull') }}</v-btn>
        </v-btn-toggle>

        <div ref="tutorLog" class="cpp__tutor-log pa-3" aria-live="polite">
          <div v-for="m in tutorMessages" :key="m.id" class="mb-3" :class="m.role === 'assistant' ? 'cpp__msg--ai' : 'cpp__msg--user'">
            <blockquote v-if="m.quote" class="cpp__quote">{{ m.quote }}</blockquote>
            <div class="text-body-2">{{ m.text }}<span v-if="m.streaming" class="cpp__caret">▍</span></div>
            <div v-if="m.error" class="text-caption cpp__msg-error">{{ m.error }}</div>
            <div v-else-if="m.truncated" class="text-caption text-medium-emphasis">{{ $t('tutor.truncated') }}</div>
          </div>
        </div>

        <v-form class="pa-3" @submit.prevent="askTutor">
          <v-text-field
            v-model="tutorInput" :loading="tutorStreaming" hide-details
            :placeholder="$t('tutor.askAboutLesson')" variant="outlined" density="compact" rounded="lg"
            :append-icon="tutorStreaming ? 'mdi-stop-circle-outline' : 'mdi-send'"
            :aria-label="tutorStreaming ? $t('tutor.stop') : $t('tutor.askAboutLesson')"
            data-test="tutor-input"
            @click:append="tutorStreaming ? stopTutorStream() : askTutor()"
            @keyup.enter="tutorStreaming || askTutor()"
          >
            <template #append-inner>
              <v-tooltip v-if="rateLimited" :text="$t('tutor.rateLimit', { s: cooldown })">
                <template #activator="{ props: tp }">
                  <v-icon v-bind="tp" size="18" color="warning">mdi-timer-sand</v-icon>
                </template>
              </v-tooltip>
            </template>
          </v-text-field>
        </v-form>
      </aside>
      <v-btn
        v-else icon variant="elevated" color="primary" class="cpp__tutor-fab"
        :aria-label="$t('tutor.open')" @click="tutorOpen = true"
      >
        <v-badge :content="tutorUnread" :model-value="tutorUnread > 0" dot color="accent">
          <v-icon>mdi-robot-outline</v-icon>
        </v-badge>
      </v-btn>
    </div>

    <!-- Mobile bottom bar (Duolingo-style): одна большая CTA -->
    <nav v-if="isMobile && !cinemaMode" class="cpp__mobilebar" aria-label="Lesson navigation">
      <v-btn variant="text" :disabled="!prevId" prepend-icon="mdi-chevron-left" @click="go(prevId)">
        {{ $t('courseManifest.prevShort') }}
      </v-btn>
      <v-btn color="primary" block rounded="pill" size="large" :loading="completing" @click="completeLesson">
        {{ lessonCompleted ? $t('coursePlayer.completed') : $t('coursePlayer.continue') }}
      </v-btn>
    </nav>
  </div>
</template>

<script>
/**
 * Логика-скелет демонстрационного уровня: state cinema/rail/idle, keyboard shortcuts,
 * idle-timer контролов, фазы X-Accel видео. Загрузка урока НЕ дублируется здесь —
 * она остаётся за useCoursePlayer/useLessonLoader (URL-as-truth), как в Pages/CoursePlayer.vue.
 */
import CourseTreeNodePremium from '../components/course/CourseTreeNodePremium.vue'
import InlineQuizRunner from '../components/player/InlineQuizRunner.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LessonWorkspace from '../components/player/LessonWorkspace.vue'
import { streamTutorChat } from '../api/tutor.api'

const FOCUSABLE_HINT = 'input, textarea, select, [contenteditable]'

let tutorMsgSeq = 0

export default {
  name: 'CoursePlayerPremium',
  // LessonWorkspace — рендерер html/article/pdf/file: подключается явно
  // (строковый <component :is="'LessonWorkspace'"> без регистрации резолвится
  // только через глобальный реестр и в dev падает warning'ом).
  components: { CourseTreeNodePremium, InlineQuizRunner, AppSkeleton, EmptyState, LessonWorkspace },
  props: {
    courseId: { type: [Number, String], required: true },
    courseTitle: { type: String, default: '' },
    nodes: { type: Array, default: () => [] },        // плоский список (source of truth)
    lesson: { type: Object, default: null },
    status: { type: String, default: 'idle' },
    errorKey: { type: String, default: null },
    epoch: { type: Number, default: 0 },
    stats: { type: Object, default: () => ({ percent: 0, done: 0, total: 0 }) },
    streak: { type: Number, default: 0 },
    currentItemId: { type: Number, default: null },
    prevId: { type: Number, default: null },
    nextId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
    resume: { type: Object, default: null },           // { last_item_id, last_anchor, percent }
  },
  emits: ['retry', 'select', 'back', 'complete', 'quiz-submit', 'tutor-ask'],
  data() {
    return {
      cinemaMode: false, railMode: false, treeOpen: true, barPeek: false,
      searchOpen: false, searching: false, query: '',
      treeTabId: null,                                 // roving tabindex дерева (Д10)
      tutorOpen: false, tutorInput: '', tutorMessages: [], tutorContext: null,
      hintLevel: 'nudge', tutorStreaming: false, tutorUnread: 0, rateLimited: false, cooldown: 0,
      tutorAbort: null, cooldownTimer: null,
      videoPhase: 'reserving', notes: [], completing: false, celebrate: false,
      quizVerdict: null, quizVerdictXp: 0, controlsIdle: false, idleTimer: null,
    }
  },
  computed: {
    isMobile() { return typeof window !== 'undefined' && window.innerWidth < 768 },
    lessonCompleted() { return this.lesson?.completed === true },
    byId() { return new Map(this.nodes.map((n) => [n.id, n])) },
    rootNodes() {
      // Д4: корни = parent_id 0/null ИЛИ висящие на отсутствующем родителе (как в CourseTree.vue)
      return this.nodes.filter((n) => {
        const pid = n.parent_id ?? 0
        return pid === 0 || !this.byId.has(pid)
      })
    },
    resumeCard() {
      if (!this.resume?.last_item_id) return null
      const node = this.byId.get(Number(this.resume.last_item_id))
      if (!node) return null
      return { id: node.id, title: node.title, percent: this.resume.percent ?? 0 }
    },
    activeModuleTitle() {
      // parent-chain активного узла до level-0 — заголовок модуля для хлебной крошки
      let cur = this.byId.get(Number(this.currentItemId))
      const guard = new Set()
      while (cur) {
        if (guard.has(cur.id)) break
        guard.add(cur.id)
        const pid = cur.parent_id ?? 0
        if (pid === 0 || !this.byId.has(pid)) return cur.title
        cur = this.byId.get(pid)
      }
      return ''
    },
    moduleStats() {
      const moduleId = this.moduleIdOf(this.currentItemId)
      if (moduleId == null) return { done: 0, total: 0 }
      const inModule = this.nodes.filter((n) => n.is_material && this.moduleIdOf(n.id) === moduleId)
      return { done: inModule.filter((n) => n.completed || n.visited).length, total: inModule.length }
    },
    workspaceComponent() { return LessonWorkspace },
    /**
     * ARIA-оболочка дерева: CourseTreeNodePremium отдаёт treeitem/group, но
     * контейнер role="tree" был потерян — AT не объявлял структуру (регрессия
     * Д10 против v2-CourseTree). data-test — точка входа e2e-спеков.
     */
    treeProps() {
      return {
        class: 'cpp__tree-scroll',
        role: 'tree',
        'aria-label': this.$t('courseManifest.contents'),
        'data-test': 'premium-tree',
      }
    },
    /** Озвучка смены урока для live-region (только победитель гонки, status=ready). */
    lessonAnnounce() {
      if (this.status !== 'ready' || !this.lesson) return ''
      const done = this.lessonCompleted ? ` · ${this.$t('coursePlayer.completed')}` : ''
      return `${this.lesson.title}${done}`
    },
  },
  mounted() {
    window.addEventListener('keydown', this.onKeydown)
    document.addEventListener('mousemove', this.resetIdleTimer)
    this.startIdleTimer()
  },
  beforeUnmount() {
    window.removeEventListener('keydown', this.onKeydown)
    document.removeEventListener('mousemove', this.resetIdleTimer)
    clearTimeout(this.idleTimer)
    clearInterval(this.cooldownTimer)
    // blueprint §3.3: закрытый урок обязан обрывать SSE-генерацию,
    // иначе док продолжает жечь CPU модели на мёртвой странице.
    if (this.tutorAbort) this.tutorAbort.abort()
  },
  methods: {
    /* ── Keyboard layer (§Манифест 1.4): шорткаты молчат, пока фокус в поле ввода ── */
    onKeydown(e) {
      if (e.target.closest(FOCUSABLE_HINT)) return
      if (e.metaKey || e.ctrlKey || e.altKey) return
      switch (e.key) {
        case 'f': e.preventDefault(); this.toggleCinema(); break
        case 't': e.preventDefault(); this.tutorOpen = !this.tutorOpen; break
        case 'Escape': this.cinemaMode ? (this.cinemaMode = false) : (this.searchOpen = false); break
        case 'ArrowRight': if (this.nextId) this.go(this.nextId); break
        case 'ArrowLeft': if (this.prevId) this.go(this.prevId); break
      }
    },
    toggleCinema() {
      this.cinemaMode = !this.cinemaMode
      // В cinema drawer уходит в rail; выход сохраняет позицию скролла/playback — состояние не сбрасываем.
      if (document.fullscreenEnabled && this.cinemaMode) {
        document.documentElement.requestFullscreen?.().catch(() => {})
      } else if (!this.cinemaMode && document.fullscreenElement) {
        document.exitFullscreen?.().catch(() => {})
      }
    },
    go(id) { this.$emit('select', id) },           // → router.push({query:{item}}) — URL-as-truth
    select(id) { this.$emit('select', id) },
    goBack() { this.$emit('back') },
    /* ── roving focus дерева: родитель держит tabId, узел эмитит nav(id) ── */
    focusTreeNode(id) {
      this.$nextTick(() => {
        const el = document.getElementById('ctnp-' + id)
        if (el) el.focus()
      })
    },
    onTreeNav(e) {
      // Home/End на уровне контейнера — первый/последний корень (узлы обрабатывают свои стрелки)
      if (e.key !== 'Home' && e.key !== 'End') return
      const roots = this.rootNodes
      if (!roots.length) return
      const target = e.key === 'Home' ? roots[0] : roots[roots.length - 1]
      e.preventDefault()
      this.treeTabId = target.id
      this.focusTreeNode(target.id)
    },
    /** level-0 предок узла (id модуля) — для moduleStats и крошек. */
    moduleIdOf(id) {
      let cur = this.byId.get(Number(id))
      const guard = new Set()
      while (cur) {
        if (guard.has(cur.id)) break
        guard.add(cur.id)
        const pid = cur.parent_id ?? 0
        if (pid === 0 || !this.byId.has(pid)) return cur.id
        cur = this.byId.get(pid)
      }
      return null
    },
    startIdleTimer() { this.idleTimer = setTimeout(() => { this.controlsIdle = true }, 3000) },
    resetIdleTimer() { if (this.cinemaMode) { this.controlsIdle = false; clearTimeout(this.idleTimer); this.startIdleTimer() } },
    skeletonFor(type) {
      // варианты AppSkeleton.vue: text | cards | form (медиа-скелетон = 'cards')
      return { video: 'cards', quiz: 'form', pdf: 'text', file: 'cards', article: 'text' }[type] || 'text'
    },
    typeIcon(t) {
      return { video: 'mdi-play-circle-outline', article: 'mdi-text-long', pdf: 'mdi-file-pdf-box',
        file: 'mdi-download', quiz: 'mdi-help-circle-outline', ai_widget: 'mdi-robot-outline',
        html: 'mdi-code-tags' }[t] || 'mdi-file-outline'
    },
    typeName(t) { return t ? this.$t(`coursePlayer.types.${t}`) : '—' },
    isOverdue(l) { return l?.due_at && new Date(l.due_at) < new Date() && !l.completed },
    formatDate(d) { return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' }).format(new Date(d)) },
    formatTs(s) { const m = Math.floor(s / 60); return `${m}:${String(Math.floor(s % 60)).padStart(2, '0')}` },
    seek(offset) { const v = this.$refs.videoEl; if (v) { v.currentTime = offset; v.play() } },
    onTick(e) { /* → useProgressTracker: tick 1/10с, debounce 2с, flush pagehide — без toast'ов */ void e },
    async completeLesson() {
      this.completing = true
      try { this.$emit('complete', this.lesson?.id) } finally { this.completing = false }
    },
    /**
     * verdict из InlineQuizRunner → родитель (route-page) отправляет ответы на сервер
     * (exam.api / player endpoint) и возвращает результат колбэком; здесь только хореография:
     * pass → celebrate-оверлей 2.6с → FLIP ✓ в дерево (Манифест §2.2).
     */
    onQuizSubmit(payload, serverVerdict = null) {
      this.$emit('quiz-submit', payload, (verdict) => this.applyVerdict(verdict))
      if (serverVerdict) this.applyVerdict(serverVerdict)
    },
    applyVerdict(verdict) {
      if (!verdict) return
      this.quizVerdict = verdict.passed ? 'pass' : 'fail'
      this.quizVerdictXp = verdict.xp ?? 0
      if (verdict.passed) {
        this.celebrate = true
        setTimeout(() => { this.celebrate = false }, 2600) // авто-продолжение: pulse на next-узле
      }
    },
    /**
     * Вопрос в TutorDock → SSE-стрим /api/v1/tutor/chat (blueprint §3.3).
     *
     * Правила дока:
     *  — один активный поток: повторный Enter во время стрима не плодит
     *    параллельную генерацию (CPU-модель), а молча игнорируется;
     *  — AbortController живёт в scope компонента и обрывается в
     *    beforeUnmount: закрытый урок не должен жечь модель дальше;
     *  — контекст явный: context_off = !tutorContext (снятая пилюля
     *    отключает подмешивание фрагментов, скрытых контекстов нет);
     *  — уровень подсказки уходит в запрос и в telemetry ('tutor-ask');
     *  — 429 обрабатывается кулдауном (useRateLimitCountdown), чтобы
     *    студент видел, сколько ждать, а не долбил лимит.
     */
    async askTutor() {
      const question = this.tutorInput.trim()
      if (!question || this.tutorStreaming || this.cooldown > 0) return

      this.tutorInput = ''
      this.tutorStreaming = true

      const controller = new AbortController()
      this.tutorAbort = controller

      const answerMsg = { id: ++tutorMsgSeq, role: 'assistant', text: '', streaming: true }
      this.tutorMessages.push({ id: ++tutorMsgSeq, role: 'user', text: question })
      this.tutorMessages.push(answerMsg)
      this.scrollLogToBottom()

      // Пакетная подсказка из открытого урока — та же пилюля, что видит
      // пользователь (контекст всегда видим, никогда не скрытый).
      if (!this.tutorContext && this.lesson?.title) {
        this.tutorContext = { excerpt: this.lesson.title, anchor: null }
      }

      try {
        await streamTutorChat(
          {
            course_id: Number(this.courseId),
            item_id: this.currentItemId ?? null,
            question: question.slice(0, 600),
            hint_level: this.hintLevel,
            context_off: !this.tutorContext,
          },
          {
            signal: controller.signal,
            onMeta: (m) => { answerMsg.grounded = m?.grounded ?? false },
            onToken: (t) => {
              answerMsg.text += t
              this.scrollLogToBottom()
            },
            onDone: (d) => { if (d?.truncated) answerMsg.truncated = true },
            onError: (message, info = {}) => {
              answerMsg.error = message || 'Ошибка движка'
              if (info.status === 429) {
                this.rateLimited = true
                this.startCooldown(info.retryAfter ?? 60)
              }
            },
          },
        )
      } catch (e) {
        // Сетевой сбой (не отмена): док обязан остаться рабочим.
        if (e?.name !== 'AbortError') answerMsg.error = String(e?.message || e)
      } finally {
        answerMsg.streaming = false
        if (this.tutorAbort === controller) this.tutorAbort = null
        this.tutorStreaming = false
        if (!this.tutorOpen) this.tutorUnread += 1
        this.$emit('tutor-ask', { question, hintLevel: this.hintLevel, grounded: !!answerMsg.grounded, error: answerMsg.error ?? null })
        this.scrollLogToBottom()
      }
    },
    stopTutorStream() {
      // Кнопка «стоп» в доке: обрыв без ошибки — сообщение остаётся с тем, что успело прийти.
      if (this.tutorAbort) this.tutorAbort.abort()
    },
    startCooldown(seconds) {
      clearInterval(this.cooldownTimer)
      this.cooldown = Math.max(1, Math.ceil(Number(seconds) || 60))
      this.cooldownTimer = setInterval(() => {
        this.cooldown -= 1
        if (this.cooldown <= 0) {
          clearInterval(this.cooldownTimer)
          this.cooldownTimer = null
          this.rateLimited = false
        }
      }, 1000)
    },
    scrollLogToBottom() {
      this.$nextTick(() => {
        const el = this.$refs.tutorLog
        if (el) el.scrollTop = el.scrollHeight
      })
    },
    scrollToQuote(ctx) { /* deep-link в кадр: anchor + подсветка (уже есть в useContentFrame) */ void ctx },
  },
}
</script>

<style scoped>
/*
  Premium-слой поверх токенов. Правила манифеста: zero elevation → layered shadows;
  радиусы 20/10/pill; вход fade+slide 8px, выход только fade; reduced-motion → static.
*/

.cpp {
  display: grid;
  grid-template-rows: 56px 1fr;
  height: 100dvh;                       /* мобильный браузер-URL-bar safe */
  background: var(--c-bg);
  --cpp-ease-enter: cubic-bezier(0.2, 0, 0, 1);   /* MD3 emphasized */
  --cpp-ease-exit: cubic-bezier(0.4, 0, 1, 1);
}
.cpp--cinema { background: var(--c-cinema-bg); grid-template-rows: 56px 1fr; }

/* ── Top bar ── */
.cpp__bar {
  position: relative; z-index: 2;
  display: flex; align-items: center; gap: 4px; padding: 0 8px;
  background: var(--c-surface);
  border-bottom: 1px solid var(--c-border);
}
.cpp--cinema .cpp__bar { background: var(--c-cinema-scrim); border-bottom-color: var(--c-cinema-border); backdrop-filter: blur(8px); }
.cpp__barline { position: absolute; left: 0; right: 0; bottom: -1px; }
.cpp__crumbs { min-width: 0; }
.cpp__crumb-course { font-size: var(--fs-sm); color: var(--c-text-secondary); }
.cpp__crumb-module { font-size: var(--fs-sm); font-weight: 600; color: var(--c-text); }
.cpp__streak { background: var(--c-accent-soft, rgba(13, 148, 136, 0.12)); color: var(--c-accent); }
.cpp__progress-num { font-size: 10px; font-weight: 700; }

/* ── Body / tree ── */
.cpp__body { position: relative; display: flex; min-height: 0; }
.cpp__tree { border-right: 1px solid var(--c-border); background: var(--c-surface); transition: width var(--dur-base) var(--cpp-ease-enter); }
.cpp--cinema .cpp__tree { background: var(--c-cinema-scrim); border-right-color: var(--c-cinema-border); }
.cpp__tree-scroll { overflow-y: auto; overscroll-behavior: contain; }   /* колонки скроллятся независимо (урок проходов redizain-plan) */
.cpp__resume { cursor: pointer; background: var(--c-primary-soft); transition: box-shadow var(--dur-fast) var(--cpp-ease-enter), transform var(--dur-fast); }
.cpp__resume:hover { box-shadow: var(--shadow-2); transform: translateY(-1px); }
.cpp__edge-trigger { position: absolute; left: 0; top: 0; bottom: 0; width: 24px; z-index: 3; }

/* Узел дерева (в CourseTreeNodePremium): активный — пилюля, а не полоса */
:deep(.ctnp--active) { background: var(--c-primary-soft); border-radius: var(--radius-pill); }
:deep(.ctnp--active)::before { content: ''; position: absolute; left: 4px; top: 20%; bottom: 20%; width: 3px; border-radius: 2px; background: var(--c-accent); }
:deep(.ctnp--locked) { opacity: 0.55; }
:deep(.ctnp__dot--overdue) { width: 7px; height: 7px; border-radius: 50%; background: var(--c-danger); }

/* ── Workspace ── */
.cpp__main { position: relative; flex: 1; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; }
.cpp__stage { width: 100%; max-width: 920px; margin: 0 auto; padding: 24px clamp(16px, 4vw, 40px) 120px; }
.cpp--cinema .cpp__stage { max-width: none; }
.cpp__lesson-title { font-size: var(--fs-xl); font-weight: 600; letter-spacing: -0.02em; line-height: 1.25; margin: 0; }

/* Видео: shell с виньеткой; veil-фазы reserving/buffering поверх poster */
.cpp__video-shell { position: relative; border-radius: var(--radius-xl) var(--radius-xl) 0 0; overflow: hidden; box-shadow: var(--shadow-3); }
.cpp__video-shell video { display: block; width: 100%; aspect-ratio: 16/9; background: var(--c-cinema-bg); }
.cpp__video-veil { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--c-text-inverse); background: radial-gradient(circle at center, rgb(0 0 0 / 0.35), rgb(0 0 0 / 0.7)); }
[data-phase="reserving"] .cpp__video-shell::after { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: linear-gradient(90deg, transparent, var(--c-accent), transparent); animation: cpp-shimmer 1.1s linear infinite; }
@keyframes cpp-shimmer { from { transform: translateX(-100%); } to { transform: translateX(100%); } }

/* Lock card */
.cpp__lockcard { background: var(--c-surface); box-shadow: var(--shadow-2); max-width: 480px; margin: 0 auto; }

/* CTA-пилюля / mobile bar */
.cpp__cta {
  position: sticky; bottom: 20px; align-self: center; z-index: 4;
  display: flex; align-items: center; gap: 2px; padding: 6px 10px;
  background: var(--c-surface); border: 1px solid var(--c-border);
  border-radius: var(--radius-pill); box-shadow: var(--shadow-3);
  transition: opacity var(--dur-slow) var(--cpp-ease-exit), transform var(--dur-slow) var(--cpp-ease-exit);
}
.cpp__cta--idle { opacity: 0; transform: translateY(12px); pointer-events: none; }
.cpp__mobilebar { position: fixed; left: 0; right: 0; bottom: 0; display: flex; gap: 8px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: var(--c-surface); border-top: 1px solid var(--c-border); z-index: 5; }

/* Tutor dock */
.cpp__tutor { position: absolute; top: 0; right: 0; bottom: 0; width: min(360px, 92vw); z-index: 6; display: flex; flex-direction: column; background: var(--c-surface); border-left: 1px solid var(--c-border); box-shadow: var(--c-glass-shadow); }
.cpp__tutor-log { flex: 1; overflow-y: auto; }
.cpp__msg--ai { border-left: 2px solid var(--c-accent); background: var(--c-surface-3); border-radius: 0 var(--radius-md) var(--radius-md) 0; padding: 10px 12px; }
.cpp__msg--user { padding: 10px 12px; }
.cpp__quote { margin: 0 0 6px; padding-left: 10px; border-left: 3px dotted var(--c-border-strong); font-style: italic; color: var(--c-text-secondary); font-size: var(--fs-sm); }
.cpp__caret { animation: cpp-blink 1s steps(2) infinite; }
.cpp__msg-error { color: var(--c-danger); }
@keyframes cpp-blink { 50% { opacity: 0; } }
.cpp__tutor-fab { position: absolute; right: 20px; bottom: 88px; z-index: 6; }

/* Success overlay (pass quiz) */
.cpp__success-card { background: var(--c-surface); border-radius: var(--radius-xl); box-shadow: var(--shadow-4); }

/* Transition-словарь: вход fade+slide, выход fade */
.cpp-fade-enter-active { transition: opacity var(--dur-base) var(--cpp-ease-enter), transform var(--dur-base) var(--cpp-ease-enter); }
.cpp-fade-leave-active { transition: opacity var(--dur-fast) var(--cpp-ease-exit); }
.cpp-fade-enter-from { opacity: 0; transform: translateY(8px); }
.cpp-fade-leave-to { opacity: 0; }
.cpp-slide-up-enter-active, .cpp-slide-up-leave-active { transition: opacity var(--dur-base), transform var(--dur-base) var(--cpp-ease-enter); }
.cpp-slide-up-enter-from, .cpp-slide-up-leave-to { opacity: 0; transform: translateY(12px); }
.cpp-pop-enter-active { transition: transform 400ms cubic-bezier(0.34, 1.56, 0.64, 1); }  /* spring */
.cpp-pop-enter-from { transform: scale(0.92); }

/* WCAG 2.2 SC 2.3.3 / reduce-motion: вся хореография → статика */
@media (prefers-reduced-motion: reduce) {
  .cpp *, .cpp__cta, .cpp__resume { transition-duration: 0.01ms !important; animation: none !important; }
  .cpp__video-shell::after { animation: none; }
}

/* Focus-visible: белое кольцо в cinema (contrast-safe) */
.cpp__main :focus-visible { outline: 2px solid var(--c-primary); outline-offset: 2px; border-radius: 4px; }
.cpp--cinema .cpp__main :focus-visible { outline-color: var(--c-text-inverse); }

@media (max-width: 767.98px) {
  .cpp { grid-template-rows: 48px 1fr; }
  .cpp__stage { padding-bottom: 160px; }
  .cpp__cta { display: none; }
}
</style>

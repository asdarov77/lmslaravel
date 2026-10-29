<!--
  CoursePlayerPremiumPage — роут-страница «Плеера 2.0» (design-manifest §3, M1).

  Наследует ЗАГРУЗОЧНЫЙ каркас Pages/CoursePlayer.vue через `extends`
  (URL-as-truth ?item=, race-guard useLessonLoader, remount :key="epoch",
  resume-point) и заменяет presentation на CoursePlayerPremium.vue.
  Никакой собственной загрузки урока здесь нет — единый путь:
    клик по дереву → router.push({query:{item}}) → watch(route) → loader.load().

  Собственные premium-надстройки (best-effort, не роняют основной путь):
    usePremiumPlayer  — completed-флаги узлов, exam-подвязка, стрик;
    InlineQuizRunner  — тест внутри плеера, verdict сервера → celebrate;
    sendViewEvent     — отметка «изучено» без нового бэкенда.

  Маршруты (регистрируются ТОЛЬКО при ui.course_player_v2, REWORK R3):
    /courses/:id/play     — v2-плеер (S1 blueprint);
    /courses/:id/premium  — он же в premium-оболочке (эта страница);
    /courses/itemmani     — legacy-аварийный откат не затронут.

  Двойной watch на загрузку урока НЕ создаётся: родительский слушает
  [name, id, item] с guard'ом name === 'courses.play'. syncUrl ниже
  route-name-aware (пушит в текущий маршрут), поэтому URL меняется только
  когда открыт courses.premium — там родитель молчит и работает дочерний
  watch. На courses.play эта страница вообще не смонтирована.
-->
<template>
  <CoursePlayerPremium
    :course-id="courseIdNum"
    :course-title="playerState.course?.title || ''"
    :nodes="premiumNodes"
    :lesson="premiumLesson"
    :status="loader.status.value"
    :error-key="loader.errorKey.value"
    :epoch="loader.epoch.value"
    :stats="premiumStats"
    :streak="streak"
    :current-item-id="currentItemId"
    :prev-id="prevId"
    :next-id="nextId"
    :loading="playerState.loading"
    :resume="playerState.resume"
    @retry="onRetry"
    @select="onSelect"
    @back="goBack"
    @complete="completeLesson"
    @quiz-submit="onQuizSubmit"
  />
</template>

<script>
/**
 * Состояние базовой страницы (route, router, courseIdNum, playerState,
 * materialIds, loader, currentItemId, stats, init, onSelect, openItem,
 * goBack) приходит из setup() CoursePlayer.vue через extends-мердж —
 * обращение к нему из computed/methods этой страницы идёт через this.*.
 */
import { computed } from 'vue'
import CoursePlayerPremium from './CoursePlayerPremium.vue'
import CoursePlayer from './CoursePlayer.vue'
import { usePremiumPlayer } from '../composables/usePremiumPlayer'
import { sendViewEvent } from '../api/player.api'
import { submitExam } from '../api/exam.api'

export default {
  name: 'CoursePlayerPremiumPage',
  // ESM-импорт вместо require(): сборка Vite-only, require() здесь — Error
  // на этапе mount. extends-мердж наследует setup()-состояние базовой
  // страницы (route/router/courseIdNum/playerState/loader/materialIds/
  // currentItemId/init/onSelect/openItem/goBack) — см. шапку файла.
  extends: CoursePlayer,
  components: { CoursePlayerPremium },

  data() {
    return {
      // completed-флаг узла после успешного /view: сервер помечает visited,
      // answeredLocally дописывает completed локально (полный reload дерева — M4).
      locallyCompleted: new Set(),
      quizSchema: null,
    }
  },

  computed: {
    premiumNodes() {
      return this.premium.premiumNodes.map((n) =>
        this.locallyCompleted.has(Number(n.id)) ? { ...n, completed: true } : n,
      )
    },
    // lesson из loader + премиум-надстройка payload для InlineQuizRunner
    // ({ title, questions[] }); схема теста грузится лениво при открытии quiz-узла.
    premiumLesson() {
      const l = this.loader.lesson.value
      if (!l) return null
      if (l.content_type === 'quiz') return { ...l, payload: this.quizSchema }
      return l
    },
    // stats: базовый контракт сервера { materials_total, materials_visited, percent }
    // → premium-контракт оболочки { total, done, percent }.
    // Ref из usePremiumPlayer — наружу как plain-number для оболочки.
    streak() {
      return this.premium.streak.value
    },
    premiumStats() {
      const s = this.stats
      return { total: s.materials_total ?? 0, done: s.materials_visited ?? 0, percent: s.percent ?? 0 }
    },
    // prev/next по упорядоченному списку материалов (nodes идут в порядке обхода дерева)
    prevId() {
      const ids = this.materialIds.map(Number)
      const i = ids.indexOf(Number(this.currentItemId))
      return i > 0 ? ids[i - 1] : null
    },
    nextId() {
      const ids = this.materialIds.map(Number)
      const i = ids.indexOf(Number(this.currentItemId))
      return i >= 0 && i < ids.length - 1 ? ids[i + 1] : null
    },
  },

  watch: {
    // Единственный триггер загрузки урока на ЭТОМ маршруте: родительский watch
    // guard'ит name === 'courses.play' и здесь молчит (см. шапку файла).
    '$route.query.item': {
      handler(item) {
        if (this.$route.name !== 'courses.premium') return
        const id = Number(item)
        if (Number.isFinite(id) && id > 0 && id !== this.currentItemId) {
          this.openItem(id, this.$route.query.anchor ? String(this.$route.query.anchor) : null)
        }
      },
    },
    // Ленивая схема теста при открытии quiz-узла.
    'loader.lesson.value': {
      async handler(l) {
        this.quizSchema = null
        if (!l || l.content_type !== 'quiz') return
        const node = this.premiumNodes.find((n) => Number(n.id) === Number(l.id))
        this.quizSchema = await this.premium.loadQuizSchema(node?.exam_id ?? null)
      },
      immediate: true,
    },
  },

  created() {
    // usePremiumPlayer требует реактивных источников — доступны уже в created().
    this.premium = usePremiumPlayer(
      computed(() => this.courseIdNum),
      computed(() => this.playerState.nodes),
    )
    this.premium.loadStreak()
  },

  methods: {
    /**
     * Route-name-aware синхронизация URL: родительский syncUrl пушит в
     * 'courses.play' и увёл бы пользователя с premium-маршрута. Здесь —
     * push в ТЕКУЩИЙ маршрут, watch выше подхватит смену ?item=.
     */
    async syncUrl(itemId, anchor = null) {
      if (Number(this.$route.query.item) === Number(itemId)) {
        await this.openItem(itemId, anchor)
        return
      }
      const query = { ...this.$route.query, item: String(itemId) }
      if (anchor) query.anchor = String(anchor)
      else delete query.anchor
      await this.router.push({ name: this.$route.name, params: { id: this.courseIdNum }, query })
    },

    onRetry() {
      // retry урока + повторная попытка стрика (best-effort внутри composable)
      this.loader.retry()
      this.premium.loadStreak()
    },


    /** Отметка «изучено»: POST /view (sendViewEvent) + локальный completed-флаг. */
    async completeLesson(itemId) {
      const id = Number(itemId ?? this.currentItemId)
      if (!Number.isFinite(id) || id <= 0) return
      try {
        const res = await sendViewEvent(this.courseIdNum, { aukstructureId: id, secondsViewed: 0, scrollPercent: 100 })
        // новый Set — гарантированный реактивный триггер (мутация Set не трекается)
        this.locallyCompleted = new Set(this.locallyCompleted).add(id)
        // visited-флаг дерева обновляем ТОЛЬКО если сервер подтвердил отметку
        // (useCoursePlayer.markVisited меняет исходный узел, а premiumNodes —
        // производный computed; completed выше покрывает статус ✓ в дереве).
        const confirmed = res?.data?.data?.completed ?? res?.data?.data?.visited
        if (confirmed) {
          const node = this.playerState.nodes.find((n) => Number(n.id) === id)
          if (node) node.visited = true
        }
      } catch { /* сеть/403 — флаг не ставим; повтор кликом */ }
    },

    /**
     * Тест внутри плеера: InlineQuizRunner отдаёт { answers: number[] } (индексы
     * опций), сервер ждёт [{ question_id, answer_id }] — конвертация через
     * answerIds схемы. Колбэк verdict → CoursePlayerPremium играет celebrate
     * (§Манифест 2.2); pass дополнительно отмечает урок completed.
     */
    async onQuizSubmit(payload, cb) {
      const lesson = this.premiumLesson
      const node = this.premiumNodes.find((n) => Number(n.id) === Number(lesson?.id))
      const examId = node?.exam_id
      if (!examId || !payload?.answers?.length) {
        cb?.({ passed: false })
        return
      }
      const answers = payload.answers
        .map((choice, i) => {
          const q = this.quizSchema?.questions?.[i]
          const answerId = q?.answerIds?.[choice]
          return answerId == null ? null : { question_id: q.questionId, answer_id: answerId }
        })
        .filter(Boolean)
      try {
        const res = await submitExam(examId, answers)
        const d = res?.data?.data ?? {}
        const passed = Boolean(d.passed)
        if (passed) await this.completeLesson(lesson.id)
        cb?.({ passed, xp: passed ? 50 : 0, score: d.score ?? null })
      } catch {
        cb?.({ passed: false })
      }
    },
  },
}
</script>

/**
 * usePremiumPlayer — данные «Плеера 2.0», которых нет в базовом player-endpoint.
 *
 * Базовый payload (/api/course/{id}/player, CoursePlayerService) несёт узлы с
 * visited/prev_id/next_id, но НЕ несёт:
 *   - completed-флаг узла (он есть только в ответе POST /view);
 *   - экзамен, привязанный к модулю (контент quiz появится серверно в S2/M4);
 *   - стрик геймификации для топ-бара.
 * Здесь эти три вещи собираются поверх существующих эндпоинтов без нового бэкенда:
 *   GET /api/my/exams          → exam по module_id (aukstructure_id) курса;
 *   GET /api/gamification/me   → streak (тот же источник, что TraineeDashboard);
 *   GET /api/exams/{id}/questions → схема теста для InlineQuizRunner
 *                                  (is_correct сервер не отдаёт — оценка только на сдаче).
 *
 * Все запросы best-effort: premium-данные украшают плеер, но не смеют ронять
 * основной путь загрузки урока. Ошибки глотаются молча (деградация до html-плеера).
 */
import { computed, ref, unref, watch } from 'vue'
import $api from '../api/httpClient'
import { unwrapArray } from '../api/envelope'

// Типы АУК: 3 = материал (CoursePlayerService::TYPE_MATERIAL), 4 = тест.
// 1/2 трактуются как группы conservatively: неизвестный тип не материал.
const TYPE_TEST = 4

export function usePremiumPlayer(courseIdRef, nodesRef) {
  const exams = ref([])
  const streak = ref(0)

  // itemId → exam (present() отдаёт module_id = aukstructure_id).
  const examByModule = computed(() => {
    const m = new Map()
    for (const e of exams.value) {
      if (e?.module_id) m.set(Number(e.module_id), e)
    }
    return m
  })

  // Дерево premium-узлов: мягкое расширение контракта базового payload.
  // completed пока === visited (серверный completed приходит из ответа /view —
  // страница дописывает флаг локально); locked/due_at/deadline появляются с M4.
  const premiumNodes = computed(() => {
    const nodes = unref(nodesRef) || []
    return nodes.map((n) => {
      const out = { ...n, completed: n.completed ?? n.visited }
      if (!n.is_material && n.type === TYPE_TEST) {
        out.is_material = true
        out.content_type = 'quiz'
        out.exam_id = examByModule.value.get(Number(n.id))?.id ?? null
      } else if (n.is_material) {
        out.content_type = n.content_type || 'html'
      }
      return out
    })
  })

  let lastExamFetchKey = ''
  watch(
    () => [unref(courseIdRef), (unref(nodesRef) || []).length].join(':'),
    async ([courseId, count]) => {
      if (lastExamFetchKey === `${courseId}:${count}`) return
      lastExamFetchKey = `${courseId}:${count}`
      try {
        const all = unwrapArray(await $api.get('/api/my/exams'))
        // курс может быть не указан у экзамена (общий по группе) — тогда не подвязываем
        exams.value = all.filter((e) => Number(e.course_id) === Number(courseId))
      } catch { /* экзаменов нет или сеть недоступна — quiz-узлы останутся без ссылки */ }
    },
    { immediate: true },
  )

  let gamLoaded = false
  async function loadStreak() {
    if (gamLoaded) return
    gamLoaded = true
    try {
      const body = (await $api.get('/api/gamification/me'))?.data?.data ?? {}
      streak.value = Number(body.streak ?? 0)
    } catch { /* стрик — украшение бара, молча деградируем до 0 */ }
  }

  /**
   * Схема теста для InlineQuizRunner: вопросы экзамена без правильных ответов
   * (is_correct намеренно отсутствует на клиенте — контракт ExamController).
   * @returns {Promise<{title: string, questions: Array<{text: string, options: string[], questionId: number}>} | null>}
   */
  async function loadQuizSchema(examId) {
    if (examId == null) return null
    try {
      // Контракт ExamController::questions — payload { exam, questions }
      // (двойной конверт axios+envelope), а не голый массив: unwrapArray
      // вернул бы [] и тест «исчез» бы без единой ошибки в консоли.
      const body = (await $api.get(`/api/exams/${examId}/questions`))?.data ?? {}
      const qs = Array.isArray(body) ? body : (body.questions ?? [])
      const title = body.exam?.title ?? ''
      if (!qs.length) return null
      return {
        title,
        questions: qs.map((q) => ({
          text: q.question_text,
          options: (q.answers || []).map((a) => a.answer),
          questionId: q.id,
          answerIds: (q.answers || []).map((a) => a.id),
        })),
      }
    } catch {
      return null // экзамен не назначен/недоступен — родитель покажет lock/empty
    }
  }

  return { premiumNodes, streak, loadStreak, loadQuizSchema, examByModule }
}

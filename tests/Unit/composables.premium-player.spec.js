// @vitest-environment jsdom
/**
 * Контрактные юнит-тесты usePremiumPlayer (M2, «Плеер 2.0»).
 *
 * Моки повторяют РЕАЛЬНЫЕ ответы сервера 1-в-1, а не желаемую форму:
 *   GET /api/my/exams            → ExamController::mine → present()
 *   GET /api/exams/{id}/questions→ { data: { exam, questions } } + конверт
 *                                  ApiResponseEnvelope (двойной конверт!)
 *   GET /api/gamification/me     → { success, data: { streak, ... } }
 *
 * Регрессии, которые ловят эти тесты:
 *   Д-P1: loadQuizSchema читал ответ как голый массив — unwrapArray давал [],
 *         и тест внутри плеера «исчезал» без единой ошибки в консоли.
 *   Д-P2: quiz-узел (type=4) не помечался is_material/content_type=quiz —
 *         дерево не вело себя как урок.
 *   Д-P3: ошибка сети gamification роняла бы топ-бар вместо деградации в 0.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: api }))

import { usePremiumPlayer } from '../../resources/js/composables/usePremiumPlayer'

/* ------------------------------ фикстуры ---------------------------------- */

// Узел в формате CoursePlayerService: type 3 = материал, 4 = тест.
const node = (id, extra = {}) => ({
  id,
  parent_id: 0,
  title: `Узел ${id}`,
  type: 3,
  is_material: true,
  has_link: true,
  content_url: `/api/course/7/content/${id}`,
  visited: false,
  ...extra,
})

// ExamController::present() — ровно те поля, что читает composable.
const examFixture = (overrides = {}) => ({
  id: 55,
  title: 'Экзамен по модулю',
  course_id: 7,
  module_id: 2, // aukstructure_id
  state: 'open',
  available: true,
  attempts_left: 2,
  passing_score: 70,
  ...overrides,
})

// Ответ /my/exams: axios.data = конверт envelope, payload = список экзаменов.
const myExamsResponse = (exams) => ({ data: { success: true, data: exams, error: null, meta: null } })

// Ответ /api/exams/{id}/questions: ДВОЙНОЙ конверт — контроллер сам кладёт
// { data: { exam, questions } }, middleware видит ключ 'data' и не дублирует.
const questionsResponse = (exam, questions) => ({
  data: {
    success: true,
    data: { exam, questions },
    error: null,
    meta: null,
  },
})

const questionFixture = (id) => ({
  id,
  question_text: `Вопрос ${id}?`,
  // is_correct намеренно отсутствует — контракт ExamController (ответы
  // не уходят клиенту до сдачи).
  answers: [
    { id: id * 10 + 1, answer: `Вариант A` },
    { id: id * 10 + 2, answer: `Вариант B` },
  ],
})

// watch(immediate) при created() синхронно дёргает /my/exams (Д-P4):
// в тестах, где этот вызов не предмет проверки, глушим его заглушкой,
// иначе счётчики api.get.plaintext зависят от порядка mockResolvedValue.
const noExams = () => api.get.mockImplementation((url) =>
  url === '/api/my/exams'
    ? Promise.resolve(myExamsResponse([]))
    : Promise.reject(new Error(`unexpected call: ${url}`)),
)

beforeEach(() => {
  api.get.mockReset()
  api.post.mockReset()
})

/* ------------------------- premiumNodes (Д2/Д-P2) ------------------------- */

describe('usePremiumPlayer → premiumNodes', () => {
  it('мягко расширяет контракт: completed === visited, контент html', () => {
    const nodes = ref([node(1, { visited: true }), node(2)])
    const { premiumNodes } = usePremiumPlayer(ref(7), nodes)

    expect(premiumNodes.value[0]).toMatchObject({ completed: true, content_type: 'html' })
    expect(premiumNodes.value[1].completed).toBe(false)
    // исходный массив не мутируем — базовый плеер читает те же объекты
    expect(nodes.value[0].completed).toBeUndefined()
  })

  it('quiz-узел type=4 получает is_material + content_type=quiz + exam_id (Д-P2)', async () => {
    api.get.mockResolvedValue(myExamsResponse([examFixture()]))
    const nodes = ref([
      node(1),
      node(2, { type: 4, is_material: false, has_link: false, content_url: null, title: 'Тест' }),
    ])
    const { premiumNodes } = usePremiumPlayer(ref(7), nodes)
    await new Promise((r) => setTimeout(r, 0)) // дать watch'im отработать

    const quizNode = premiumNodes.value.find((n) => n.id === 2)
    expect(quizNode).toMatchObject({ is_material: true, content_type: 'quiz', exam_id: 55 })
  })

  it('quiz без подвязанного экзамена остаётся узлом с exam_id=null', async () => {
    api.get.mockResolvedValue(myExamsResponse([examFixture({ course_id: 999 })]))
    const nodes = ref([node(2, { type: 4, is_material: false })])
    const { premiumNodes } = usePremiumPlayer(ref(7), nodes)
    await new Promise((r) => setTimeout(r, 0))

    expect(premiumNodes.value[0].exam_id).toBeNull()
  })
})

/* --------------------------- loadQuizSchema (Д-P1) ------------------------ */

describe('usePremiumPlayer → loadQuizSchema', () => {
  it('разбирает двойной конверт { exam, questions } и нормализует вопросы', async () => {
    noExams()
    api.get.mockImplementation((url) =>
      url === '/api/exams/55/questions'
        ? Promise.resolve(questionsResponse(examFixture(), [questionFixture(11), questionFixture(12)]))
        : Promise.reject(new Error(`unexpected: ${url}`)),
    )
    const { loadQuizSchema } = usePremiumPlayer(ref(7), ref([]))

    const schema = await loadQuizSchema(55)

    expect(schema).not.toBeNull()
    expect(schema.title).toBe('Экзамен по модулю')
    expect(schema.questions).toHaveLength(2)
    expect(schema.questions[0]).toEqual({
      text: 'Вопрос 11?',
      options: ['Вариант A', 'Вариант B'],
      questionId: 11,
      answerIds: [111, 112],
    })
    // InlineQuizRunner отправляет индексы ответов — id ответов должны быть сохранены
    expect(api.get).toHaveBeenCalledWith('/api/exams/55/questions')
  })

  it('пустой список вопросов → null (родитель покажет lock/empty, а не битый тест)', async () => {
    noExams()
    api.get.mockImplementation((url) =>
      url === '/api/exams/55/questions'
        ? Promise.resolve(questionsResponse(examFixture(), []))
        : Promise.reject(new Error(`unexpected: ${url}`)),
    )
    const { loadQuizSchema } = usePremiumPlayer(ref(7), ref([]))
    await expect(loadQuizSchema(55)).resolves.toBeNull()
  })

  it('403 «экзамен не назначен» → null, проказ не летит наружу', async () => {
    noExams()
    api.get.mockRejectedValue(Object.assign(new Error('Forbidden'), { response: { status: 403 } }))
    const { loadQuizSchema } = usePremiumPlayer(ref(7), ref([]))
    await expect(loadQuizSchema(55)).resolves.toBeNull()
  })

  it('examId == null → null без запроса к эндпоинту вопросов (сам /my/exams — Д-P4, см. ниже)', async () => {
    noExams()
    const { loadQuizSchema } = usePremiumPlayer(ref(7), ref([]))
    await expect(loadQuizSchema(null)).resolves.toBeNull()
    expect(api.get).not.toHaveBeenCalledWith('/api/exams/null/questions')
    expect(api.get.mock.calls.filter((c) => String(c[0]).startsWith('/api/exams/'))).toHaveLength(0)
  })
})

/* ------------------------------- streak (Д-P3) ---------------------------- */

describe('usePremiumPlayer → streak', () => {
  it('берёт streak из /api/gamification/me и кэширует один запрос', async () => {
    noExams()
    api.get.mockImplementation((url) =>
      url === '/api/gamification/me'
        ? Promise.resolve({ data: { success: true, data: { streak: 9, xp: 120 } } })
        : Promise.reject(new Error(`unexpected: ${url}`)),
    )
    const { streak, loadStreak } = usePremiumPlayer(ref(7), ref([]))

    await loadStreak()
    await loadStreak()

    expect(streak.value).toBe(9)
    expect(api.get.mock.calls.filter((c) => c[0] === '/api/gamification/me')).toHaveLength(1)
  })

  it('сетевая ошибка gamification деградирует до 0, не роняя плеер (Д-P3)', async () => {
    noExams()
    api.get.mockRejectedValue(new Error('network down'))
    const { streak, loadStreak } = usePremiumPlayer(ref(7), ref([]))

    await expect(loadStreak()).resolves.toBeUndefined()
    expect(streak.value).toBe(0)
  })
})

/* ------------------------- загрузка /my/exams (best-effort) --------------- */

describe('usePremiumPlayer → загрузка экзаменов курса', () => {
  it('фильтрует экзамены по course_id, чужие отсекаются', async () => {
    api.get.mockResolvedValue(
      myExamsResponse([examFixture(), examFixture({ id: 66, course_id: 8, module_id: 3 })]),
    )
    const { examByModule } = usePremiumPlayer(ref(7), ref([node(1)]))
    await new Promise((r) => setTimeout(r, 0))

    expect(examByModule.value.get(2)?.id).toBe(55)
    expect(examByModule.value.has(3)).toBe(false)
  })

  it('ошибка /my/exams глотается: premium-данные не смеют ронять основной путь', async () => {
    api.get.mockRejectedValue(new Error('500'))
    const { premiumNodes } = usePremiumPlayer(ref(7), ref([node(1)]))
    await new Promise((r) => setTimeout(r, 0))

    expect(premiumNodes.value).toHaveLength(1) // дерево живое, несмотря на отказ
  })

  it('не перевызывает экзамены при том же курсе и числе узлов', async () => {
    api.get.mockResolvedValue(myExamsResponse([]))
    const nodes = ref([node(1)])
    usePremiumPlayer(ref(7), nodes)
    await new Promise((r) => setTimeout(r, 0))

    nodes.value = [...nodes.value, node(3)] // новое число узлов → ревалидация
    await new Promise((r) => setTimeout(r, 0))

    const calls = api.get.mock.calls.filter((c) => c[0] === '/api/my/exams')
    expect(calls).toHaveLength(2)
  })
})

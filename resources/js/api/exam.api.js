import httpClient from './httpClient'
import { unwrapField, unwrapArray, unwrapResponse } from './envelope'

/**
 * Экзамены.
 *
 * Отдельный файл от вопросов: у экзамена другой жизненный цикл
 * (назначение → окно доступности → попытки → результат) и другой
 * источник вопросов — /exams/{id}/questions, который НЕ отдаёт
 * is_correct. Проверка ответов — на сервере.
 */

// Мои экзамены (кабинет обучаемого).
const fetchMyExams = () => httpClient.get('/api/my/exams')

// Все экзамены — для методиста.
const fetchExams = () => httpClient.get('/api/exams')

// Вопросы экзамена без правильных ответов.
const fetchExamQuestions = examId =>
  httpClient.get(`/api/exams/${examId}/questions`)

// Сдача: отправляем выбранные answer_id, а не «правильно/неправильно».
const submitExam = (examId, answers) =>
  httpClient.post(`/api/exams/${examId}/attempts`, { answers })

// История попыток текущего пользователя.
const fetchAttempts = () => httpClient.get('/api/exam-attempts')

export default {
  fetchMyExams,
  fetchExams,
  fetchExamQuestions,
  submitExam,
  fetchAttempts,

  myExams: response => unwrapArray(response),

  /** { exam, questions } — questions без is_correct. */
  examBundle: response => ({
    exam: unwrapField(response, 'exam') || {},
    questions: unwrapArray({ data: unwrapField(response, 'questions') }),
  }),

  /**
   * Результат попытки, посчитанный сервером.
   *
   * Именно unwrapResponse: он разворачивает конверт и возвращает payload
   * целиком ({score, passed, correct_count, ...}). Обращение через
   * unwrapField(response, 'score') искало бы вложенное поле и вернуло бы
   * undefined — результат сдачи показывался бы как пустой.
   */
  attemptResult: response => unwrapResponse(response),
}

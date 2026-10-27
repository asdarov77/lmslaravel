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
export const fetchMyExams = () => httpClient.get('/api/my/exams')

// Все экзамены — для методиста.
export const fetchExams = (params = {}) => httpClient.get('/api/exams', { params })

// Создание экзамена.
export const createExam = data => httpClient.post('/api/exams', data).then(response => response.data.data)

// Обновление экзамена.
export const updateExam = (id, data) => httpClient.put(`/api/exams/${id}`, data).then(response => response.data.data)

// Получение экзамена.
export const fetchExam = id => httpClient.get(`/api/exams/${id}`).then(response => response.data.data)

// Вопросы экзамена без правильных ответов.
export const fetchExamQuestions = examId =>
  httpClient.get(`/api/exams/${examId}/questions`)

// Сдача: отправляем выбранные answer_id, а не «правильно/неправильно».
export const submitExam = (examId, answers) =>
  httpClient.post(`/api/exams/${examId}/attempts`, { answers })

// История попыток текущего пользователя.
export const fetchAttempts = () => httpClient.get('/api/exam-attempts')

// Статистика вопросов по модулям для категории.
export const fetchModuleStats = categoryId =>
  httpClient.get('/api/questions/statistics', { params: { category_id: categoryId } })
    .then(response => response.data.data)

// Курсы по категории.
export const fetchCoursesByCategory = categoryId =>
  httpClient.get('/api/questions/courses-by-category', { params: { category_id: categoryId } })
    .then(response => response.data.data ?? [])

// Трансформеры ответов.
export const myExams = response => unwrapArray(response)

/** { exam, questions } — questions без is_correct. */
export const examBundle = response => ({
  exam: unwrapField(response, 'exam') || {},
  questions: unwrapArray({ data: unwrapField(response, 'questions') }),
})

/**
 * Результат попытки, посчитанный сервером.
 *
 * Именно unwrapResponse: он разворачивает конверт и возвращает payload
 * целиком ({score, passed, correct_count, ...}). Обращение через
 * unwrapField(response, 'score') искало бы вложенное поле и вернуло бы
 * undefined — результат сдачи показывался бы как пустой.
 */
export const attemptResult = response => unwrapResponse(response)

export const moduleStats = response => unwrapResponse(response)
export const coursesByCategory = response => unwrapArray(response)

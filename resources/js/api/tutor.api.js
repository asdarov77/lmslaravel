import httpClient from './httpClient'

/**
 * Тренажёр: локальная генерация вопросов по материалам курсов.
 *
 * Все ответы приходят в конверте {success,data,error,meta}, поэтому
 * компоненты разбирают их через unwrapResponse (см. api/envelope).
 *
 * Отдельно от course.api: тренажёр не имеет отношения к экзаменам и не
 * должен попадать в модуль курсов — иначе «историю обучения» придётся
 * отделять от «истории экзаменов», а это разные вещи по определению.
 */

export const fetchTutorHealth = () => httpClient.get('/api/v1/tutor/health')

export const fetchTutorMaterials = () => httpClient.get('/api/v1/tutor/materials')

export const startTutorSession = (materialId, count) =>
  httpClient.post('/api/v1/tutor/sessions', { material_id: materialId, count })

export const fetchTutorSession = (sessionId) => httpClient.get(`/api/v1/tutor/sessions/${sessionId}`)

export const fetchNextQuestion = (sessionId) =>
  httpClient.get(`/api/v1/tutor/sessions/${sessionId}/next-question`)

export const answerQuestion = (itemId, payload) =>
  httpClient.post(`/api/v1/tutor/items/${itemId}/answer`, payload)

export const finishTutorSession = (sessionId) =>
  httpClient.post(`/api/v1/tutor/sessions/${sessionId}/finish`)

export const fetchTutorStats = () => httpClient.get('/api/v1/tutor/stats')

// Методистские методы.
export const fetchAdminMaterials = () => httpClient.get('/api/v1/tutor/admin/materials')

export const indexCourseMaterial = (courseId) =>
  httpClient.post('/api/v1/tutor/admin/materials/index', { course_id: courseId })

export default {
  fetchTutorHealth,
  fetchTutorMaterials,
  startTutorSession,
  fetchTutorSession,
  fetchNextQuestion,
  answerQuestion,
  finishTutorSession,
  fetchTutorStats,
  fetchAdminMaterials,
  indexCourseMaterial,
}
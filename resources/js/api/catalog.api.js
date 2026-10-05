import httpClient from './httpClient'

/**
 * Витрина курсов и самостоятельная запись.
 *
 * Отдельный файл от course.api: витрина отвечает на вопрос «что можно
 * выбрать», а учебный план — «что уже назначено». Формат ответа у них
 * разный, и смешивать их в одном модуле значило бы тащить в стейт поля,
 * которые тут не нужны.
 */
export const fetchCatalog = (params = {}) => httpClient.get('/api/catalog', { params })

export const enrollCourse = (courseId) => httpClient.post(`/api/catalog/${courseId}/enroll`)

export const unenrollCourse = (courseId) => httpClient.delete(`/api/catalog/${courseId}/enroll`)

export default { fetchCatalog, enrollCourse, unenrollCourse }

import httpClient from './httpClient'

/**
 * Глобальный поиск по LMS.
 *
 * Отдельный файл: поиск возвращает сгруппированные результаты со своей
 * формой ответа (groups/total), а не плоский список. Область видимости
 * считает сервер: клиент не должен решать, что пользователю можно найти.
 */
export const searchGlobal = (q, limit) =>
  httpClient.get('/api/search', {
    params: { q, ...(limit ? { limit } : {}) },
  })

export default { searchGlobal }

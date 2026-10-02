import httpClient from './httpClient'
import { unwrapField, metaField, numericQuery } from './envelope'

/**
 * Календарь учебного процесса.
 *
 * Отдельный файл: лента календаря — не каталог курсов, у неё своя
 * область видимости (периоды обучения групп) и свой формат ответа
 * (события FullCalendar).
 */

// Лента периодов обучения. Нулевые и пустые значения фильтров
// отбрасывает numericQuery — иначе axios отправил бы ?group_id=null,
// и Laravel ответил бы 422 на валидацию exists:groups,id.
const fetchEvents = (params = {}) => httpClient.get('/api/calendar', { params: numericQuery(params) })

export default {
  fetchEvents,

  /** Массив событий для FullCalendar. */
  events: response => unwrapField(response, 'events') || [],

  /** Варианты фильтров: группы, курсы, категории. */
  filters: response => unwrapField(response, 'filters') || { groups: [], courses: [], categories: [] },

  /**
   * Общее число событий.
   *
   * Именно metaField: счётчик приходит в meta конверта, а не в data,
   * где лежат events/filters. unwrapField искал бы его в data и вернул
   * undefined — счётчик молча показывал бы 0.
   */
  total: response => metaField(response, 'total') ?? null,
}

import httpClient from './httpClient'
import { unwrapArray, unwrapResponse } from './envelope'

/**
 * Личный кабинет обучаемого: учебный план и данные дашборда.
 *
 * Отдельный файл, потому что это НЕ админский раздел: методы читают
 * только собственные данные пользователя и доступны любому
 * авторизованному. Права на «просмотр своего плана» не существует —
 * ограничение не в правах, а в области данных на сервере.
 */

// Учебный план: назначения курсов/модулей с датами и статусом.
const fetchMyLearning = () => httpClient.get('/api/my/learning')

// Сводка для дашборда: счётчики, ближайшие дедлайны, экзамены, прогресс.
const fetchMyDashboard = () => httpClient.get('/api/my/dashboard')

export default {
  fetchMyLearning,
  fetchMyDashboard,

  /**
   * Разворачивает конверт в массив записей плана.
   * Раньше здесь обращались к res.data.data, что ломалось о самый конверт
   * envelope: при ошибке data === null и страница падала на .map.
   */
  planItems: response => unwrapArray(response),

  /**
   * Сводка дашборда.
   *
   * Именно unwrapResponse, а не unwrapField(..., 'data'): unwrapResponse
   * УЖЕ разворачивает конверт и возвращает payload, поэтому поиск поля
   * 'data' внутри payload давал undefined — дашборд показывал нули
   * вместо реальных счётчиков, притом что список курсов рядом
   * отображался нормально.
   */
  dashboard: response => unwrapResponse(response) || {},
}

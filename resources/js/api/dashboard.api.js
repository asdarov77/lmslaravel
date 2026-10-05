import httpClient from './httpClient'

/**
 * Сводка для администратора и инструктора.
 *
 * Отдельный файл от плана обучения: у кабинета управляющего другая
 * форма ответа (показатели + список требующего внимания) и другая
 * область видимости — она считается на сервере.
 */
export const fetchDashboardSummary = () => httpClient.get('/api/dashboard/summary')

export default { fetchDashboardSummary }

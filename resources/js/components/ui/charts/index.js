/**
 * ChartsKit — лёгкий набор SVG-графиков в цветах токенов.
 *
 * Внешний движок графиков (ECharts и подобные) в проект не добавлен
 * намеренно: нужны линии, столбцы и heatmap, а это компактно рисуется
 * на SVG. Плюс — никакого второго API поверх дизайн-токенов: сетка,
 * подписи и цвета берутся из тех же переменных, что и остальной UI,
 * поэтому тёмная тема работает без отдельной настройки графиков.
 *
 * Импорт одной строкой:
 *   import { LineChart, BarChart, Sparkline, Heatmap } from '@/components/ui/charts'
 */
export { default as LineChart } from './LineChart.vue'
export { default as BarChart } from './BarChart.vue'
export { default as Sparkline } from './Sparkline.vue'
export { default as Heatmap } from './Heatmap.vue'

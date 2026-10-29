/**
 * useUiFlags — чтение UI feature-flags (REWORK R3) из window.__UI_FLAGS__.
 *
 * Значения сервер кладёт в blade-шаблон (config/ui.php), фронт читает без
 * отдельного запроса. Отсутствие окна (SSR, юнит-тесты вне jsdom) трактуется
 * как «флаг выключен» — безопасный дефолт: legacy-путь всегда доступен.
 */
export function getUiFlag(name, fallback = false) {
  const flags = typeof window !== 'undefined' ? window.__UI_FLAGS__ : null
  if (!flags || typeof flags !== 'object') return fallback
  return name in flags ? Boolean(flags[name]) : fallback
}

export function coursePlayerV2Enabled() {
  return getUiFlag('course_player_v2', false)
}

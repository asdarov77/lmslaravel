/**
 * White-label composable: загрузка и применение настроек бренда.
 *
 * Использование:
 *   const { load, apply } = useWhiteLabel()
 *   await load() // при старте приложения
 *
 * После сохранения настроек в админке вызывать apply() с новыми переменными.
 */

import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import $api from '../api/httpClient'
import { applyBrandCss, isDarkMode, watchThemeChange, readBrandCss } from './whiteLabelCss'

const loaded = ref(false)
const cssVariables = ref({})
const error = ref(null)

/**
 * Загружает настройки бренда с бэкенда и применяет CSS-переменные.
 */
export async function loadBrand() {
  if (loaded.value) return

  try {
    // optional: true — декоративный ресурс. Общий обработчик httpClient
    // на ЛЮБОЙ 403 уводит всё приложение на страницу «В доступе отказано».
    // Брендинг не вправе быть причиной, по которой человек теряет
    // доступ ко всему интерфейсу: при отказе остаются дефолтные цвета.
    const response = await $api.get('/api/white-label', { optional: true })
    const data = response?.data?.data ?? {}

    cssVariables.value = data.css_variables ?? {}
    applyBrandCss(cssVariables.value, isDarkMode())

    loaded.value = true
    error.value = null
  } catch (e) {
    error.value = e?.response?.data?.message || 'Не удалось загрузить настройки бренда'
    console.warn('[white-label]', error.value)
  }
}

/**
 * Применяет переданные CSS-переменные (после сохранения в админке).
 */
export function applyBrand(vars) {
  cssVariables.value = vars ?? {}
  applyBrandCss(cssVariables.value, isDarkMode())
}

/**
 * Хук для использования в компонентах/страницах.
 */
export function useWhiteLabel() {
  const { t } = useI18n()

  return {
    loaded,
    error,
    cssVariables,
    load: loadBrand,
    apply: applyBrand,
    t,
  }
}

/**
 * Инициализация при старте приложения.
 *
 * Вызывать в main.js или App.vue mounted():
 *   import { initWhiteLabel } from '@/utils/whiteLabel'
 *   initWhiteLabel()
 */
let themeUnsub = null

export function initWhiteLabel() {
  loadBrand()

  // Слушаем смену темы и переприменяем dark overrides
  themeUnsub = watchThemeChange((vars, dark) => {
    applyBrandCss(vars, dark)
  })
}

export function destroyWhiteLabel() {
  if (themeUnsub) {
    themeUnsub()
    themeUnsub = null
  }
}
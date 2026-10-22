/**
 * White-label: применение CSS-переменных бренда.
 *
 * Вызывается при загрузке приложения и после сохранения настроек.
 * Переменные записываются в :root (document.documentElement.style),
 * что мгновенно обновляет все компоненты, использующие эти токены.
 *
 * Цветовые токены:
 *  - Vuetify core: --v-theme-primary, --v-theme-primary-lighten, --v-theme-primary-darken
 *  - Custom accent: --c-accent, --c-accent-lighten, --c-accent-darken
 *  - Brand gradients: --c-brand-gradient-1, --c-brand-gradient-2
 *  - Dark overrides: --v-theme-bg, --v-theme-surface, --v-theme-primary, --c-accent
 *
 * Важно: тёмная тема переопределяет --v-theme-primary и --c-accent
 * при активном dark mode. Поэтому при смене темы мы снова применяем
 * соответствующие переменные из настроек.
 */

export const BRAND_CSS_KEYS = [
  // Vuetify core
  '--v-theme-primary',
  '--v-theme-primary-lighten',
  '--v-theme-primary-darken',
  // Custom accent
  '--c-accent',
  '--c-accent-lighten',
  '--c-accent-darken',
  // Brand gradients
  '--c-brand-gradient-1',
  '--c-brand-gradient-2',
  // Dark theme overrides (применяются через media query или класс .theme--dark)
  '--v-theme-bg',
  '--v-theme-surface',
  '--v-theme-primary', // override в dark
  '--c-accent', // override в dark
]

/**
 * Применяет CSS-переменные бренда к :root.
 *
 * @param {Record<string, string>} vars — объект { '--v-theme-primary': '#1976d2', ... }
 * @param {boolean} isDark — применяются ли dark-переопределения прямо сейчас
 */
export function applyBrandCss(vars, isDark = false) {
  const root = document.documentElement

  // Базовые переменные (всегда)
  const base = [
    '--v-theme-primary',
    '--v-theme-primary-lighten',
    '--v-theme-primary-darken',
    '--c-accent',
    '--c-accent-lighten',
    '--c-accent-darken',
    '--c-brand-gradient-1',
    '--c-brand-gradient-2',
  ]

  base.forEach(key => {
    if (vars[key]) {
      root.style.setProperty(key, vars[key])
    }
  })

  // Dark overrides — применяются если тема тёмная
  // (Vuetify переключает класс .theme--dark на <html> или <body>)
  if (isDark) {
    const dark = [
      '--v-theme-bg',
      '--v-theme-surface',
      '--v-theme-primary',
      '--c-accent',
    ]
    dark.forEach(key => {
      if (vars[key]) {
        root.style.setProperty(key, vars[key])
      }
    })
  }
}

/**
 * Читает актуальные CSS-переменные из :root (для отладки/превью).
 */
export function readBrandCss() {
  const root = document.documentElement
  const out = {}
  BRAND_CSS_KEYS.forEach(key => {
    out[key] = root.style.getPropertyValue(key).trim() || getComputedStyle(root).getPropertyValue(key).trim()
  })
  return out
}

/**
 * Сбрасывает брендовые переменные к дефолтам (при выходе из админки
 * или если настройки не загружены).
 */
export function resetBrandCss() {
  const root = document.documentElement
  BRAND_CSS_KEYS.forEach(key => {
    root.style.removeProperty(key)
  })
}

/**
 * Проверяет, активна ли тёмная тема сейчас.
 */
export function isDarkMode() {
  // Vuetify 3 добавляет класс theme--dark на <html> или <body>
  return document.documentElement.classList.contains('theme--dark')
    || document.body.classList.contains('theme--dark')
    || window.matchMedia('(prefers-color-scheme: dark)').matches
}

/**
 * Слушатель смены темы — переприменяет dark overrides.
 *
 * Вызывать один раз при старте приложения.
 */
export function watchThemeChange(applyFn) {
  // MutationObserver на класс theme--dark
  const observer = new MutationObserver(() => {
    applyFn(readBrandCss(), isDarkMode())
  })
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
  observer.observe(document.body, { attributes: true, attributeFilter: ['class'] })

  // Также слушаем системный prefers-color-scheme
  const mql = window.matchMedia('(prefers-color-scheme: dark)')
  mql.addEventListener('change', () => {
    applyFn(readBrandCss(), isDarkMode())
  })

  return () => {
    observer.disconnect()
    mql.removeEventListener('change', () => {})
  }
}
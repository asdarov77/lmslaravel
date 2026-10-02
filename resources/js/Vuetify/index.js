import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

/**
 * Тема Vuetify собрана на тех же токенах, что и наши CSS-классы.
 *
 * Зачем читать значения из CSS-переменных, а не задавать цвета
 * константами прямо здесь: пока тема жила отдельно от макета, цвет
 * приходилось дублировать в двух местах. Теперь
 * `primary` в v-btn и `.u-btn--primary` — физически одно значение.
 *
 * Токены лежат в resources/css/tokens.css, который подключён в app.js
 * раньше этой темы. Читаем их уже на этапе создания vuetify — DOM к
 * этому моменту готов, потому что тема создаётся модулем при импорте.
 */
const cssVar = (name, fallback) => {
  if (typeof window === 'undefined' || !window.getComputedStyle) return fallback

  const value = window.getComputedStyle(document.documentElement)
    .getPropertyValue(name)
    .trim()

  return value || fallback
}

const token = (name, fallback) => cssVar(`--${name}`, fallback)

const vuetify = createVuetify({
  components,
  directives,

  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: token('c-primary', '#1a5fb4'),
          'primary-hover': token('c-primary-hover', '#17509b'),
          secondary: token('c-surface-3', '#f0f2f5'),
          surface: token('c-surface', '#ffffff'),
          background: token('c-bg', '#f4f6f8'),
          'surface-variant': token('c-surface-3', '#f0f2f5'),
          'on-surface-variant': token('c-text-secondary', '#5a626c'),
          error: token('c-danger', '#b3261e'),
          info: token('c-info', '#1a5fb4'),
          success: token('c-success', '#1e7a3c'),
          warning: token('c-warning', '#9a6400'),
          'on-primary': token('c-on-primary', '#ffffff'),
          'on-error': token('c-text-inverse', '#ffffff'),
          'on-success': token('c-text-inverse', '#ffffff'),
          'on-warning': token('c-text-inverse', '#ffffff'),
        },
        variables: {
          'border-color': token('c-border', '#dde1e6'),
          'border-opacity': 1,
          'high-emphasis-opacity': 1,
          'medium-emphasis-opacity': 0.72,
          'disabled-opacity': 0.45,
        },
      },

      // Тёмная тема объявлена, чтобы переключение было возможно без
      // перекраски страниц: токены описаны в tokens.css под .v-theme--dark.
      dark: {
        dark: true,
        colors: {
          primary: token('c-primary', '#7aa7e8'),
          secondary: token('c-surface-3', '#2a2f35'),
          surface: token('c-surface', '#1c2024'),
          background: token('c-bg', '#14171a'),
          'surface-variant': token('c-surface-3', '#2a2f35'),
          'on-surface-variant': token('c-text-secondary', '#b0b7bf'),
          error: token('c-danger', '#f2857c'),
          info: token('c-info', '#7aa7e8'),
          success: token('c-success', '#6fce8f'),
          warning: token('c-warning', '#e3b341'),
          'on-primary': token('c-on-primary', '#0f1620'),
          'on-error': '#1c2024',
          'on-success': '#1c2024',
          'on-warning': '#1c2024',
        },
        variables: {
          'border-color': token('c-border', '#363c43'),
          'border-opacity': 1,
        },
      },
    },
  },

  /**
   * Значения по умолчанию для компонентов.
   *
   * Здесь задаётся то, что раньше повторялось по шаблонам вручную:
   * одинаковый variant полей, одинаковые радиусы, одна плотность.
   * Благодаря этому новый v-text-field выглядит как остальные,
   * даже если автор забудет про variant.
   */
  defaults: {
    VCard: {
      elevation: 0,
      rounded: 'md',
    },
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VTextarea: {
      variant: 'outlined',
      color: 'primary',
    },
    VSelect: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VCombobox: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VAutocomplete: {
      variant: 'outlined',
      density: 'comfortable',
      color: 'primary',
    },
    VBtn: {
      rounded: 'sm',
      elevation: 0,
    },
    VAlert: {
      rounded: 'md',
      variant: 'tonal',
    },
    VChip: {
      rounded: 'sm',
    },
    VTable: {
      density: 'comfortable',
    },
    VToolbar: {
      flat: true,
    },
    // В v2 был проп text, в v3 его нет: убрать default нельзя, но
    // фиксируем правильный variant для кнопок по умолчанию.
    VDialog: {
      scrollable: true,
    },
  },
})

export default vuetify

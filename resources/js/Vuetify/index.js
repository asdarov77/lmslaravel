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
/**
 * Чтение токенов из CSS.
 *
 * Для светлой темы берём значения из :root. Для тёмной — из элемента
 * с классом .v-theme--dark: tokens.css переопределяет токены именно под
 * этим селектором, а :root всегда содержит светлые значения. Раньше
 * тёмная тема читала :root, то есть получала СВЕТЛУЮ палитру при
 * `dark: true` — переключатель был бы включён, а цвета остались бы
 * светлыми.
 *
 * Единственный источник значений — tokens.css: константы здесь
 * продублировали бы его, и через месяц они бы разошлись.
 */
const readVar = (element, name, fallback) => {
  if (typeof window === 'undefined' || !window.getComputedStyle) return fallback

  const value = window.getComputedStyle(element)
    .getPropertyValue(`--${name}`)
    .trim()

  return value || fallback
}

const token = (name, fallback) =>
  readVar(typeof document === 'undefined' ? {} : document.documentElement, name, fallback)

/** Токены тёмной темы: временный элемент с её классом. */
const darkToken = (() => {
  const cache = {}

  return (name, fallback) => {
    if (cache[name] !== undefined) return cache[name]

    if (typeof document === 'undefined' || !document.body) return fallback

    const probe = document.createElement('div')
    probe.className = 'v-theme--dark'
    // Пробник не должен влиять на раскладку и не показываться.
    probe.style.display = 'none'
    document.body.appendChild(probe)

    const value = readVar(probe, name, fallback)

    probe.remove()
    cache[name] = value

    return value
  }
})()

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
          primary: darkToken('c-primary', '#7aa7e8'),
          secondary: darkToken('c-surface-3', '#2a2f35'),
          surface: darkToken('c-surface', '#1c2024'),
          background: darkToken('c-bg', '#14171a'),
          'surface-variant': darkToken('c-surface-3', '#2a2f35'),
          'on-surface-variant': darkToken('c-text-secondary', '#b0b7bf'),
          error: darkToken('c-danger', '#f2857c'),
          info: darkToken('c-info', '#7aa7e8'),
          success: darkToken('c-success', '#6fce8f'),
          warning: darkToken('c-warning', '#e3b341'),
          'on-primary': darkToken('c-on-primary', '#0f1620'),
          'on-error': darkToken('c-text-inverse', '#1c2024'),
          'on-success': darkToken('c-text-inverse', '#1c2024'),
          'on-warning': darkToken('c-text-inverse', '#1c2024'),
        },
        variables: {
          'border-color': darkToken('c-border', '#363c43'),
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

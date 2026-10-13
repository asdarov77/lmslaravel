<template>
  <v-btn
    icon
    variant="text"
    :title="label"
    :aria-label="label"
    :aria-pressed="isDark"
    data-test="theme-toggle"
    @click="toggle"
  >
    <v-icon :icon="icon" aria-hidden="true"></v-icon>
  </v-btn>
</template>

<script setup>
/**
 * Переключатель темы.
 *
 * Три состояния: светлая, тёмная и «как в системе». Два было бы
 * проще, но тогда пользователь, желающий следовать системной настройке,
 * должен был бы выбирать вручную при каждой её смене.
 *
 * Состояние берётся из модуля темы, а не из собственного: иначе после
 * перезагрузки кнопка показывала бы не то, что включено на самом деле.
 */
import { computed, ref } from 'vue'
import { useTheme } from 'vuetify'
import { useI18n } from 'vue-i18n'
import theme from '../../utils/theme'

const vuetifyTheme = useTheme()

// Режим хранится в ref, а не читается из localStorage внутри computed:
// у localStorage нет реактивности, поэтому после клика подпись и иконка
// оставались прежними до перезагрузки страницы.
const mode = ref(theme.read())
const isDark = computed(() => vuetifyTheme.global.name.value === 'dark')

const { t: $t } = useI18n()

/**
 * Подпись переключателя.
 *
 * Раньше здесь был `window.$t?.(...)`: в сборке Vite такого поля нет,
 * и вместо перевода человек видел «Тема (theme.auto)» — с ключом в
 * интерфейсе. Кроме того, suffix тоже оставался ключом. Оба значения
 * теперь берутся из локализации.
 */
const label = computed(() => {
  const suffixKey = {
    light: 'theme.light',
    dark: 'theme.dark',
    auto: 'theme.auto',
  }[mode.value] ?? 'theme.auto'

  return `${$t('theme.label')} (${$t(suffixKey)})`
})

const icon = computed(() => {
  if (mode.value === 'auto') return 'mdi-theme-light-dark'
  return isDark.value ? 'mdi-weather-night' : 'mdi-white-balance-sunny'
})

const toggle = () => {
  mode.value = theme.cycle(vuetifyTheme)
}
</script>

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
import theme from '../../utils/theme'

const vuetifyTheme = useTheme()

// Режим хранится в ref, а не читается из localStorage внутри computed:
// у localStorage нет реактивности, поэтому после клика подпись и иконка
// оставались прежними до перезагрузки страницы.
const mode = ref(theme.read())
const isDark = computed(() => vuetifyTheme.global.name.value === 'dark')

const label = computed(() => {
  const suffix = {
    light: 'theme.light',
    dark: 'theme.dark',
    auto: 'theme.auto',
  }[mode.value]

  return `${window.$t?.('theme.label') || 'Тема'} (${suffix})`
})

const icon = computed(() => {
  if (mode.value === 'auto') return 'mdi-theme-light-dark'
  return isDark.value ? 'mdi-weather-night' : 'mdi-white-balance-sunny'
})

const toggle = () => {
  mode.value = theme.cycle(vuetifyTheme)
}
</script>

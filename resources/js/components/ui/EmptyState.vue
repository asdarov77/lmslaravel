<template>
  <div class="u-empty">
    <v-icon class="u-empty__icon" :icon="icon" size="44" aria-hidden="true"></v-icon>
    <p class="u-empty__title">{{ title }}</p>
    <p v-if="text" class="u-empty__text">{{ text }}</p>
    <slot></slot>
  </div>
</template>

<script setup>
/**
 * Пустое состояние.
 *
 * Раньше пустой список был просто пустым: непонятно, нет данных,
 * фильтр ничего не нашёл или запрос упал. Здесь пустое состояние
 * объясняет причину и, если уместно, предлагает действие.
 */
defineProps({
  icon: { type: String, default: 'mdi-inbox-outline' },
  title: { type: String, default: 'Пока пусто' },
  text: { type: String, default: '' },
})
</script>

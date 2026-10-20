<template>
  <header class="u-page__head">
    <div class="u-page__head-text">
      <Breadcrumbs v-if="$route.meta.breadcrumbs" />
      <h1 v-if="title" class="u-page__title">{{ title }}</h1>
      <p v-if="subtitle" class="u-page__subtitle">{{ subtitle }}</p>
      <slot name="head" />
    </div>

    <div v-if="$slots.actions" class="u-page__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
import Breadcrumbs from './Breadcrumbs.vue'
import { defineProps } from 'vue'

/**
 * Заголовок страницы: название, хлебные крошки, пояснение и действия справа.
 *
 * Раньше каждая страница собирала шапку сама, по-разному: v-toolbar с
 * color="primary" на формах, h1 без отступов на списках, а на части
 * страниц заголовка не было вовсе. Из-за этого при переходе между
 * разделами «прыгала» высота шапки.
 */
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>

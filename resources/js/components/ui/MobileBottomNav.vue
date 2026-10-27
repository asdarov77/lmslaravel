<template>
  <nav v-if="loggedIn && isTrainee" class="u-bottom-nav" aria-label="Bottom navigation">
    <router-link
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      class="u-bottom-nav__item"
      :class="{ 'u-bottom-nav__item--active': isActive(item.to) }"
    >
      <v-icon :icon="item.icon" aria-hidden="true" />
      <span>{{ item.title }}</span>
    </router-link>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const props = defineProps({
  loggedIn: { type: Boolean, default: false },
  isTrainee: { type: Boolean, default: false },
})

const router = useRouter()
const route = useRoute()

/*
 * Адреса нижней навигации.
 *
 * Раньше стояло /exams/mine и /my/account — таких маршрутов в
 * Router/routes.js нет (есть /my/exams и /my). Нажатие уводило в
 * пустой экран, потому что catch-all появился только сейчас.
 * Пути взяты из routes.js: exams.mine → /my/exams, myaccount → /my.
 */
const items = computed(() => [
  { to: '/dashboard', icon: 'mdi-view-dashboard', title: 'Дашборд' },
  { to: '/catalog', icon: 'mdi-bookshelf', title: 'Курсы' },
  { to: '/my/exams', icon: 'mdi-clipboard-check', title: 'Тесты' },
  { to: '/my', icon: 'mdi-account', title: 'Профиль' },
])

const isActive = (to) => route.path === to || route.path.startsWith(to + '/')
</script>

<style scoped>
.u-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: var(--z-sticky, 100);
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: var(--c-surface);
  border-top: 1px solid var(--c-border);
  padding: var(--sp-1) 0;
  padding-bottom: env(safe-area-inset-bottom, var(--sp-1));
}

.u-bottom-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--sp-1) var(--sp-3);
  color: var(--c-text-muted);
  text-decoration: none;
  font-size: var(--fs-xs);
}

.u-bottom-nav__item--active {
  color: var(--c-primary);
}

.u-bottom-nav__item .v-icon {
  font-size: 20px;
}

@media (min-width: 769px) {
  .u-bottom-nav {
    display: none;
  }
}
</style>

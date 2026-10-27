<template>
  <div class="auth-layout">
    <!--
      Брендовая панель. Смыслы и цифры берутся из аргументов, а не из
      i18n внутри компонента: тем же каркасом пользуется восстановление
      пароля, у которого свой заголовок.
    -->
    <aside class="auth-layout__brand">
      <!-- Мягкие подвижные блики на бренд-панели — оживляют статичный градиент. -->
      <div class="auth-layout__glow"></div>
      <div class="auth-layout__brand-content">
        <div class="auth-layout__logo">
          <v-icon icon="mdi-graduation-cap" size="40" aria-hidden="true" />
          <span class="auth-layout__app-name">{{ appName }}</span>
        </div>

        <!--
          Заголовок остаётся белым (цвет наследуется от панели).
          Градиентный текст здесь неуместен: панель уже окрашена в
          primary→accent, и текст того же диапазона на ней не читается.
        -->
        <h1 class="auth-layout__headline">{{ headline }}</h1>
        <p class="auth-layout__tagline">{{ tagline }}</p>

        <div v-if="stats.length" class="auth-layout__stats">
          <div v-for="stat in stats" :key="stat.value" class="auth-layout__stat">
            <span class="auth-layout__stat-value">{{ stat.value }}</span>
            <span class="auth-layout__stat-label">{{ stat.label }}</span>
          </div>
        </div>

        <blockquote v-if="quote" class="auth-layout__quote">
          <p>{{ quote.text }}</p>
          <footer>{{ quote.author }}</footer>
        </blockquote>
      </div>
    </aside>

    <main class="auth-layout__form u-card--glass">
      <div class="auth-layout__form-inner">
        <!--
          Переключатель «Вход ⇄ Регистрация».

          Раньше это были две страницы: нажатие «Регистрация» уводило
          с формы входа, а возврат был только через кнопку «Назад» —
          человек терял введённый логин и лишний раз проходил всю
          цепочку. Теперь это один экран: форма меняется на месте,
          поля остаются в той же колонке, а состояние (какая вкладка
          активна) живёт в URL — ссылкой можно поделиться, и кнопка
          «назад» работает предсказуемо.
        -->
        <div v-if="tabs.length > 1" class="auth-switch" role="tablist" :aria-label="switchLabel">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            class="auth-switch__tab"
            :class="{ 'is-active': tab.key === active }"
            :aria-selected="tab.key === active"
            :data-test="`auth-tab-${tab.key}`"
            @click="$emit('update:active', tab.key)"
          >
            {{ tab.title }}
          </button>
        </div>

        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'

/**
 * Каркас экранов авторизации: слева брендовая панель, справа форма.
 *
 * Зачем отдельный компонент: вход, регистрация и восстановление пароля
 * — три страницы с одинаковой композицией, и раньше каждая рисовала
 * свой gradient, свои отступы и свой заголовок. Общий каркас даёт им
 * один бренд и место для переключателя «Вход ⇄ Регистрация».
 */
const props = defineProps({
  appName: { type: String, default: '' },
  headline: { type: String, default: '' },
  tagline: { type: String, default: '' },
  /** [{ value, label }] */
  stats: { type: Array, default: () => [] },
  quote: { type: Object, default: null },
  /** [{ key, title }] */
  tabs: { type: Array, default: () => [] },
  active: { type: String, default: '' },
  switchLabel: { type: String, default: '' },
})

defineEmits(['update:active'])

const active = computed(() => props.active)
</script>

<style scoped>
.auth-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  min-height: 100vh;
}

.auth-layout__brand {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--sp-6);
  background: linear-gradient(135deg, var(--c-primary) 0%, var(--c-primary-active) 60%, var(--c-accent) 100%);
  color: var(--c-on-primary);
  position: relative;
  overflow: hidden;
  isolation: isolate;
}

/* Подвижные размытые блики — создают глубину без анимированных картинок. */
.auth-layout__glow {
  position: absolute;
  inset: -20%;
  z-index: 0;
  filter: blur(60px);
  opacity: 0.55;
  background:
    radial-gradient(circle at 20% 30%, rgba(255 255 255 / 0.35) 0%, transparent 40%),
    radial-gradient(circle at 80% 70%, rgba(255 255 255 / 0.25) 0%, transparent 40%);
  animation:
    auth-glow-move 10s ease-in-out infinite alternate;
}

@keyframes auth-glow-move {
  0% {
    transform: translate(-8%, -8%) scale(1);
  }
  100% {
    transform: translate(8%, 12%) scale(1.15);
  }
}

.auth-layout__brand::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 30% 20%, rgb(255 255 255 / 10%) 0%, transparent 50%),
              radial-gradient(circle at 70% 80%, rgb(255 255 255 / 5%) 0%, transparent 40%);
  pointer-events: none;
  z-index: 0;
}

.auth-layout__brand-content {
  max-width: 480px;
  position: relative;
  z-index: 1;
}

.auth-layout__logo {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-bottom: var(--sp-8);
}

.auth-layout__app-name {
  font-size: var(--fs-xl);
  font-weight: var(--fw-bold);
}

.auth-layout__headline {
  font-size: var(--fs-3xl);
  font-weight: var(--fw-bold);
  line-height: 1.2;
  margin: 0 0 var(--sp-3);
}

.auth-layout__tagline {
  font-size: var(--fs-base);
  opacity: 0.9;
  margin: 0 0 var(--sp-8);
  line-height: 1.5;
}

.auth-layout__stats {
  display: flex;
  gap: var(--sp-6);
  margin-bottom: var(--sp-8);
}

.auth-layout__stat {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}

.auth-layout__stat-value {
  font-size: var(--fs-2xl);
  font-weight: var(--fw-bold);
}

.auth-layout__stat-label {
  font-size: var(--fs-sm);
  opacity: 0.8;
}

.auth-layout__quote {
  margin: 0;
  padding: var(--sp-4);
  border-left: 3px solid rgb(255 255 255 / 30%);
  font-style: italic;
  opacity: 0.9;
}

.auth-layout__quote p {
  margin: 0 0 var(--sp-2);
  font-size: var(--fs-sm);
  line-height: 1.5;
}

.auth-layout__quote footer {
  font-size: var(--fs-xs);
  opacity: 0.7;
}

.auth-layout__form {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--sp-6);
  background: var(--c-surface);
}

.auth-layout__form-inner {
  width: 100%;
  max-width: 400px;
}

/* Переключатель режима. Роли tab/tablist — не украшение: экранным
   диктором это читается как переключение между двумя формами. */
.auth-switch {
  display: flex;
  gap: var(--sp-1);
  padding: var(--sp-1);
  margin-bottom: var(--sp-4);
  background: var(--c-surface-3);
  border-radius: var(--radius-pill);
}

.auth-switch__tab {
  flex: 1 1 0;
  padding: var(--sp-2) var(--sp-3);
  border: none;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--c-text-secondary);
  font: inherit;
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  cursor: pointer;
  transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
}

.auth-switch__tab:hover {
  color: var(--c-text);
}

.auth-switch__tab.is-active {
  background: var(--c-surface);
  color: var(--c-primary);
  box-shadow: var(--shadow-1);
}

.auth-switch__tab:focus-visible {
  outline: 2px solid var(--c-accent);
  outline-offset: 2px;
}

@media (max-width: 960px) {
  .auth-layout {
    grid-template-columns: 1fr;
  }

  .auth-layout__brand {
    display: none;
  }

  .auth-layout__form {
    min-height: 100vh;
  }
}

@media (max-width: 600px) {
  .auth-layout__form {
    padding: var(--sp-4);
  }
}
</style>

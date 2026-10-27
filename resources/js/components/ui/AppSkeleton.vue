<template>
  <div class="u-skeleton" :class="`u-skeleton--${variant}`" role="status" :aria-label="label" aria-busy="true">
    <!-- Таблица: шапка + N строк. Высота строк одна, поэтому скелетон
         не «прыгает», когда приходят настоящие данные. -->
    <template v-if="variant === 'table'">
      <div class="u-skeleton__row u-skeleton__row--head">
        <span v-for="c in columns" :key="`h${c}`" class="u-skeleton__cell" />
      </div>
      <div v-for="r in count" :key="`r${r}`" class="u-skeleton__row">
        <span v-for="c in columns" :key="`r${r}c${c}`" class="u-skeleton__cell" />
      </div>
    </template>

    <!-- Карточки: сетка, как у каталога курсов. -->
    <div v-else-if="variant === 'cards'" class="u-skeleton__cards">
      <div v-for="i in count" :key="`c${i}`" class="u-skeleton__card">
        <span class="u-skeleton__block u-skeleton__block--cover" />
        <span class="u-skeleton__line u-skeleton__line--w80" />
        <span class="u-skeleton__line u-skeleton__line--w60" />
      </div>
    </div>

    <!-- Форма: подпись + поле, повтор. -->
    <template v-else-if="variant === 'form'">
      <div v-for="i in count" :key="`f${i}`" class="u-skeleton__field">
        <span class="u-skeleton__line u-skeleton__line--w40" />
        <span class="u-skeleton__block u-skeleton__block--input" />
      </div>
    </template>

    <!-- Текст: абзац из строк разной длины. -->
    <template v-else>
      <span
        v-for="i in count"
        :key="`t${i}`"
        class="u-skeleton__line"
        :class="i === count ? 'u-skeleton__line--w60' : 'u-skeleton__line--w80'"
      />
    </template>
  </div>
</template>

<script setup>
/**
 * Скелетон загрузки.
 *
 * Раньше на время запроса показывали v-progress-circular по центру
 * пустой области: страница «мигала» от спиннера к содержимому, а на
 * медленной сети пользователь не понимал, что вообще загрузится.
 * Скелетон рисует структуру будущего контента заранее — интерфейс
 * кажется быстрее и не смещает вёрстку, когда данные придут.
 *
 * variant выбирает форму: table — под DataTable, cards — под каталог,
 * form — под форму редактирования, text — под абзацы.
 */
defineProps({
  /** table | cards | form | text */
  variant: { type: String, default: 'text' },
  /** Сколько строк/карточек/полей рисовать. */
  count: { type: Number, default: 5 },
  /** Сколько колонок в таблице. */
  columns: { type: Number, default: 4 },
  label: { type: String, default: 'Загрузка' },
})
</script>

<style scoped>
.u-skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  width: 100%;
}

.u-skeleton__line,
.u-skeleton__block,
.u-skeleton__cell {
  display: block;
  background: var(--c-skeleton);
  animation: u-skeleton-pulse 1.4s ease-in-out infinite;
}

.u-skeleton__line {
  height: 12px;
  border-radius: var(--radius-sm);
}

.u-skeleton__line--w80 { width: 80%; }
.u-skeleton__line--w60 { width: 60%; }
.u-skeleton__line--w40 { width: 40%; }

.u-skeleton__block {
  border-radius: var(--radius-md);
}

.u-skeleton__block--cover {
  height: 120px;
}

.u-skeleton__block--input {
  height: 44px;
}

/* Таблица */
.u-skeleton__row {
  display: grid;
  grid-template-columns: repeat(v-bind(columns), minmax(0, 1fr));
  gap: var(--sp-4);
  align-items: center;
}

.u-skeleton__row--head {
  padding-bottom: var(--sp-2);
  border-bottom: 1px solid var(--c-border);
}

.u-skeleton__cell {
  height: 14px;
  border-radius: var(--radius-sm);
}

.u-skeleton__row--head .u-skeleton__cell {
  height: 10px;
  width: 60%;
}

/* Карточки */
.u-skeleton__cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--sp-4);
}

.u-skeleton__card {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
}

/* Форма */
.u-skeleton__field {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

@keyframes u-skeleton-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.45; }
}

@media (prefers-reduced-motion: reduce) {
  .u-skeleton__line,
  .u-skeleton__block,
  .u-skeleton__cell {
    animation: none;
  }
}
</style>

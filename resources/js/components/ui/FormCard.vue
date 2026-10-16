<template>
  <form class="u-card u-card--glass u-form" novalidate @submit.prevent="$emit('submit')">
    <div v-if="title" class="u-card__head">
      <!--
        Уровень заголовка настраивается. По умолчанию h2 — правило,
        когда над карточкой уже есть h1 страницы. Но на формах, где
        FormCard САМА является шапкой страницы (смена пароля, создание
        группы, редактирование пользователя), заголовка уровня 1 не
        было вовсе: страница начиналась с h2, и у неё не было имени —
        ни для человека, ни для навигации по заголовкам
        (WCAG 2.2 SC 1.3.1, 2.4.6).
      -->
      <component :is="`h${headingLevel}`" class="u-card__title">{{ title }}</component>
      <p v-if="subtitle" class="u-page__subtitle" style="margin: 0">{{ subtitle }}</p>
    </div>

    <div class="u-card__body">
      <!--
        Пока сущность грузится, полей нет — форма выглядит пустой, и
        пользователь не понимает, зависло ли что-то. Показываем
        индикатор и не даём ничего нажать.
      -->
      <div v-if="loading" class="d-flex justify-center py-8">
        <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
      </div>
      <slot v-else></slot>
    </div>

    <div v-if="!loading" class="u-card__foot">
      <slot name="actions">
        <FormActions
          :busy="busy"
          :submit-text="submitText"
          :cancel-text="cancelText"
          :show-cancel="showCancel"
          @submit="$emit('submit')"
          @cancel="$emit('cancel')"
        />
      </slot>
    </div>
  </form>
</template>

<script setup>
import FormActions from './FormActions.vue'

/**
 * Карточка формы создания/редактирования.
 *
 * Заменяет 12 разных `<v-card class="elevation-12 mx-auto" style="width:600px">`
 * с разной шириной (600px, 900px, 1200px), без подзаголовка и без
 * единого места под действия.
 *
 * Ширина задаётся классом, а не инлайном: узкая форма (смена пароля)
 * и широкая (курс с деревом разделов) отличаются размером, но не
 * структурой.
 */
defineProps({
  /**
   * Уровень заголовка карточки: 1, когда она сама — шапка страницы.
   */
  headingLevel: {
    type: [Number, String],
    default: 2,
    validator: (v) => ['1', '2', '3', 1, 2, 3].includes(v),
  },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  busy: { type: Boolean, default: false },
  /** Первичная загрузка сущности: полей ещё нет. */
  loading: { type: Boolean, default: false },
  // Пусто = перевод берётся в FormActions; раньше дефолт был русским.
  submitText: { type: String, default: '' },
  cancelText: { type: String, default: '' },
  showCancel: { type: Boolean, default: true },
})

defineEmits(['submit', 'cancel'])
</script>

<style scoped>
.u-form {
  width: 100%;
  max-width: var(--w-form);
  margin: 0 auto;
}
</style>

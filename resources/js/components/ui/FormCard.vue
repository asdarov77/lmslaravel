<template>
  <form class="u-card u-form" novalidate @submit.prevent="$emit('submit')">
    <div v-if="title" class="u-card__head">
      <h2 class="u-card__title">{{ title }}</h2>
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
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  busy: { type: Boolean, default: false },
  /** Первичная загрузка сущности: полей ещё нет. */
  loading: { type: Boolean, default: false },
  submitText: { type: String, default: 'Сохранить' },
  cancelText: { type: String, default: 'Отмена' },
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

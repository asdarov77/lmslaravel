<template>
  <v-col cols="12" sm="8" md="5">
    <div class="u-narrow-card">
      <!-- Шаг 1: ввод ФИО. Шаг 2: подтверждение отправки. Одна и та же
           страница, состояние переключается флагом: отдельный роут для
           «письмо отправлено» не нужен. -->
      <template v-if="!sent">
        <h2 class="u-card__title mb-1">{{ t('password.forgotTitle') }}</h2>
        <p class="u-page__subtitle">{{ t('password.forgotHint') }}</p>

        <form novalidate @submit.prevent="submit">
          <FormField :label="t('password.labelFio')" name="fio" :error="form.errors.fio" :required="form.isRequired('fio')">
            <v-text-field
              v-bind="form.bind('fio')"
              data-test="forgot-fio"
              prepend-inner-icon="mdi-account-outline"
              autocomplete="username"
            />
          </FormField>

          <div class="d-flex align-center mt-2">
            <router-link :to="{ name: 'login' }" class="text-caption">
              {{ t('password.backToLogin') }}
            </router-link>
            <v-spacer />
            <v-btn color="primary" variant="flat" type="submit" :loading="form.submitting.value" data-test="forgot-submit">
              {{ t('password.sendLink') }}
            </v-btn>
          </div>
        </form>
      </template>

      <div v-else class="text-center py-4" data-test="forgot-sent">
        <v-icon icon="mdi-email-check-outline" size="48" color="success" class="mb-4" />
        <p class="text-body-2">{{ t('password.forgotSent') }}</p>
        <v-btn variant="text" :to="{ name: 'login' }">{{ t('password.backToLogin') }}</v-btn>
      </div>
    </div>
  </v-col>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import $api from '../../api/httpClient'
import useForm from '../../composables/useForm'
import FormField from '../../components/ui/FormField.vue'
import { required } from '../../composables/validation/rules'

/**
 * Запрос ссылки восстановления пароля.
 *
 * Ответ намеренно не раскрывает, найдено ли ФИО: одинаковое сообщение
 * для существующего и несуществующего пользователя не даёт перебрать
 * фамилии через форму. Поэтому ошибка сервера показывается общим
 * текстом, а не «пользователь не найден».
 */
const { t } = useI18n()
const sent = ref(false)

const form = useForm({
  schema: {
    fio: { initial: '', rules: [required(t('login.fioRequired'))] },
  },

  onSubmit: async ({ values }) => {
    await $api.post('/api/password/forgot', { fio: values.fio })

    sent.value = true
  },
})

const submit = () => {
  form.submit()
}
</script>

<style scoped>
/* Узкая карточка без v-toolbar: тулбар внутри карточки формы — тот самый
   конструктив, который линтер Фазы 0 запрещает. */
.u-narrow-card {
  width: 100%;
  max-width: 480px;
  margin: 0 auto;
  padding: var(--sp-6);
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-xl);
}
</style>
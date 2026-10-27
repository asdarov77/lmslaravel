<template>
  <v-col cols="12" sm="8" md="5">
    <div class="u-narrow-card">
      <h2 class="u-card__title mb-1">{{ t('password.resetTitle') }}</h2>
      <p class="u-page__subtitle">
        {{ t('password.resetFor') }}
        <strong>{{ fio }}</strong>
      </p>

      <form novalidate @submit.prevent="submit">
        <FormField
          :label="t('password.newPassword')"
          name="password"
          :error="form.errors.password"
          :required="form.isRequired('password')"
        >
          <v-text-field
            v-bind="form.bind('password')"
            type="password"
            data-test="reset-password"
            prepend-inner-icon="mdi-lock-outline"
            autocomplete="new-password"
          />
        </FormField>

        <FormField
          :label="t('password.confirmPassword')"
          name="password_confirmation"
          :error="form.errors.password_confirmation"
          :required="form.isRequired('password_confirmation')"
        >
          <v-text-field
            v-bind="form.bind('password_confirmation')"
            type="password"
            data-test="reset-confirm"
            prepend-inner-icon="mdi-lock-check-outline"
            autocomplete="new-password"
          />
        </FormField>

        <div class="d-flex align-center mt-2">
          <router-link :to="{ name: 'login' }" class="text-caption">
            {{ t('password.backToLogin') }}
          </router-link>
          <v-spacer />
          <v-btn color="primary" variant="flat" type="submit" :loading="form.submitting.value" data-test="reset-submit">
            {{ t('password.savePassword') }}
          </v-btn>
        </div>
      </form>
    </div>
  </v-col>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import $api from '../../api/httpClient'
import { toast } from '../../composables/useToast'
import useForm from '../../composables/useForm'
import FormField from '../../components/ui/FormField.vue'
import { required, minLength, sameAs } from '../../composables/validation/rules'

/**
 * Установка нового пароля по токену из письма.
 *
 * Токен и ФИО приходят из query ссылки: так страница открывается прямо
 * из письма, без промежуточного шага ввода токена.
 *
 * Проверка «пароли совпадают» переехала в правила схемы: раньше её не
 * было вовсе, и пользователь отправлял несовпадающие пароли и получал
 * 422 вместо подсказки под полем.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const token = route.query.token || ''
const fio = route.query.fio || ''

const form = useForm({
  schema: {
    password: {
      initial: '',
      rules: [required(t('login.passwordRequired')), minLength(6, t('user.chpass.tooShort'))],
    },
    password_confirmation: {
      initial: '',
      rules: [required(t('login.passwordRequired')), sameAs('password', t('user.chpass.mismatch'))],
    },
  },

  onSubmit: async ({ values }) => {
    const response = await $api.post('/api/password/reset', {
      fio,
      token,
      password: values.password,
      password_confirmation: values.password_confirmation,
    })

    // Переход на вход с подтверждением: пароль уже изменён, и
    // пользователю нужно войти заново — молчаливый редирект без
    // сообщения оставил бы его непонятно где.
    if (response?.data?.message) toast.success(response.data.message)

    await router.push({ name: 'login', query: { reset: '1' } })
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
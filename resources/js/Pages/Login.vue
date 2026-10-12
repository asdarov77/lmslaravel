<template>
  <v-col cols="12" sm="8" md="4">
    <FormCard
      :title="t('login.formTitle')"
      :busy="form.submitting.value"
      :submit-text="t('login.submitBtn')"
      :show-cancel="false"
      @submit="submitForm"
    >
      <FormField :label="t('login.fio')" name="fio" :error="form.errors.fio" :required="form.isRequired('fio')">
        <v-text-field
          id="userLogin"
          v-bind="form.bind('fio')"
          name="login"
          :placeholder="t('login.fio')"
          prepend-inner-icon="mdi-account-outline"
          autocomplete="username"
        />
      </FormField>

      <FormField :label="t('login.password')" name="password" :error="form.errors.password" :required="form.isRequired('password')">
        <v-text-field
          id="password"
          v-bind="form.bind('password')"
          name="password"
          type="password"
          :placeholder="t('login.password')"
          prepend-inner-icon="mdi-lock-outline"
          autocomplete="current-password"
        />
      </FormField>

      <div class="d-flex justify-end mb-2">
        <router-link
          :to="{ name: 'password.forgot' }"
          class="text-caption"
          data-test="login-forgot"
        >
          {{ t('password.forgotTitle') }}
        </router-link>
      </div>
    </FormCard>
  </v-col>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import FormCard from '../components/ui/FormCard.vue'
import FormField from '../components/ui/FormField.vue'
import useForm from '../composables/useForm'
import { required } from '../composables/validation/rules'
import { toast } from '../composables/useToast'

/**
 * Вход в систему.
 *
 * Переведена на useForm (Фаза 2): вместо массива `errors`, который
 * никогда не наполнялся и в который шли строки на русском вперемешку с
 * переводами, — схема с локализованными правилами и подсветкой полей.
 * Разбор ответа по коду (401/429/5xx) сохранён: «неверный пароль» и
 * «сервис недоступен» требуют от пользователя разных действий.
 *
 * Карточка переехала с `elevation-12` и инлайновой шириной 600px на
 * FormCard с токенами (Phase 0 запрещает эти конструкции линтером).
 */
const { t } = useI18n()
const store = useStore()
const router = useRouter()

const form = useForm({
  schema: {
    fio: { initial: '', rules: [required(t('login.fioRequired'))] },
    password: { initial: '', rules: [required(t('login.passwordRequired'))] },
  },

  onSubmit: async ({ values }) => {
    await store.dispatch('Auth/login', { fio: values.fio, password: values.password })

    toast.success(t('login.success'))
    // После входа ведём в кабинет, а не на '/': корневой адрес
    // редиректит на /dashboard, и лишний редирект только показывал бы
    // в адресной строке '/', а не '/dashboard'.
    await router.push('/dashboard')
  },
})

const submitForm = async () => {
  const ok = await form.submit()
  if (ok) return

  // Разбор по коду ответа: раньше пользователь получал одну строку на
  // все случаи. Здесь ошибка приходит наружу из onSubmit-ошибки —
  // useForm уже разложил 422 по полям, но разбор кодов делаем здесь.
  const error = form.submitError.value
  if (!error) return

  const status = error?.response?.status

  if (!error?.response) {
    toast.error(t('login.networkError'))
  } else if (status === 401) {
    toast.error(t('login.wrongPassword'))
  } else if (status === 429) {
    toast.error(t('login.tooManyRequests'))
  } else if (status >= 500) {
    toast.error(t('login.serverError'))
  } else {
    toast.error(toast.fromError(error, t('login.unknownError')))
  }
}
</script>
<template>
  <v-col cols="12" sm="8" md="4">
    <FormCard
      :title="`${t('user.changePassword')} ${usernameEdit}`"
      :busy="form.submitting.value"
      @submit="submitForm"
      @cancel="cancel"
    >
      <FormField
        :label="t('user.labelParol')"
        name="password"
        :error="form.errors.password"
        :required="form.isRequired('password')"
        :hint="passwordHint"
      >
        <v-text-field
          id="password"
          v-bind="form.bind('password')"
          name="password"
          type="password"
          :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          autocomplete="new-password"
          @click:append-inner="showPassword = !showPassword"
        />
      </FormField>

      <FormField
        :label="t('user.labelPovtorite-parol')"
        name="password_confirmation"
        :error="form.errors.password_confirmation"
        :required="form.isRequired('password_confirmation')"
      >
        <v-text-field
          id="password_confirmation"
          v-bind="form.bind('password_confirmation')"
          name="password_confirmation"
          type="password"
          autocomplete="new-password"
        />
      </FormField>

      <!--
        Показывать/скрывать пароль: раньше поле всегда было type="password",
        и при опечатке в длинном пароле приходилось набирать заново вслепую.
      -->
      <v-switch
        v-model="showPassword"
        :label="t('user.showPassword')"
        density="compact"
        color="primary"
        hide-details
      />
    </FormCard>
  </v-col>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import useForm from '../../composables/useForm'
import { required, minLength, sameAs } from '../../composables/validation/rules'
import { toast } from '../../composables/useToast'

/**
 * Смена пароля пользователем.
 *
 * Переведена на useForm (Фаза 2). Раньше проверка выглядела так:
 * `if (this.password !== this.password_confirmation) errors.push('пароли
 * не совпадают')`, а дальше `if (!this.errors.length)` — массив ошибок
 * копился, но в него попадали строки вперемешку с переводами, а кнопка
 * сохранения не блокировалась: пользователь мог отправить несовпадающие
 * пароли и получить 422. Теперь правила в схеме, submit блокируется,
 * а несовпадение подсвечивает конкретное поле.
 */
const { t } = useI18n()
const store = useStore()
const router = useRouter()

const props = defineProps({
  idEdit: { type: [Number, String], required: true },
})

const showPassword = ref(false)

const usernameEdit = computed(() => store.state.User.user?.fio ?? '')

const form = useForm({
  schema: {
    password: {
      initial: '',
      rules: [
        required(t('login.passwordRequired')),
        minLength(6, t('user.chpass.tooShort')),
      ],
    },
    password_confirmation: {
      initial: '',
      rules: [
        required(t('login.passwordRequired')),
        sameAs('password', t('user.chpass.mismatch')),
      ],
    },
  },

  onSubmit: async ({ values }) => {
    await store.dispatch('User/chpassUser', {
      id: props.idEdit,
      data: { password: values.password },
    })

    toast.success(t('user.chpass.success'))
    /*
     * Возврат на страницу сделан сразу: раньше здесь стоял таймер,
     * чтобы тост успел отрисоваться на этой странице. Тост живёт в
     * общем стеке и переживает навигацию — ждать нечего, иначе
     * пользователь секунду смотрит на пустую страницу.
     */
    await router.back()
  },
})

const submitForm = () => {
  form.submit()
}

const cancel = () => {
  router.back()
}
</script>
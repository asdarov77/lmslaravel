<template>
  <v-col cols="12" sm="8" md="4">
    <FormCard
      :title="t('users.list.create')"
      :busy="form.submitting.value"
      @submit="submitForm"
      @cancel="cancel"
    >
      <FormField :label="t('register.fio')" name="fio" :error="form.errors.fio" :required="form.isRequired('fio')">
        <v-text-field
          id="userLogin"
          v-bind="form.bind('fio')"
          name="login"
          prepend-inner-icon="mdi-account-outline"
          autocomplete="username"
        />
      </FormField>

      <!--
        Роль выбирается только тем, кто имеет право создавать пользователей.
        Раньше список был всегда полным, включая публичную саморегистрацию,
        где любой мог отметить «Администратор» (бэкенд такое значение
        принимал без проверки). Теперь бэкенд всё равно понижает роль до
        «Обучаемого», а форма просто не предлагает недоступный выбор.
      -->
      <FormField
        :label="t('users.role')"
        name="role"
        :hint="mayAssignRole ? '' : t('users.roleHintPublic')"
      >
        <v-select
          v-bind="form.bind('role')"
          :items="roleOptions"
          item-value="rolename"
          item-title="rolename"
          prepend-inner-icon="mdi-shield-account-outline"
        />
      </FormField>

      <FormField v-if="form.values.role === 'Обучаемый' && mayAssignGroup" :label="t('register.group')" name="group_id">
        <v-select
          v-bind="form.bind('group_id')"
          :items="groups"
          item-value="id"
          item-title="groupname"
        />
      </FormField>

      <FormField
        :label="t('register.password')"
        name="password"
        :error="form.errors.password"
        :required="form.isRequired('password')"
      >
        <v-text-field
          id="password"
          v-bind="form.bind('password')"
          name="password"
          type="password"
          prepend-inner-icon="mdi-lock-outline"
          autocomplete="new-password"
        />
      </FormField>

      <FormField
        :label="t('register.passwordRepeat')"
        name="password_confirmation"
        :error="form.errors.password_confirmation"
        :required="form.isRequired('password_confirmation')"
      >
        <v-text-field
          id="password_confirmation"
          v-bind="form.bind('password_confirmation')"
          name="password_confirmation"
          type="password"
          prepend-inner-icon="mdi-lock-outline"
          autocomplete="new-password"
        />
      </FormField>
    </FormCard>
  </v-col>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import FormCard from '../components/ui/FormCard.vue'
import FormField from '../components/ui/FormField.vue'
import useForm from '../composables/useForm'
import { required, minLength, sameAs } from '../composables/validation/rules'
import { toast } from '../composables/useToast'

/**
 * Создание пользователя.
 *
 * Переведена на useForm (Фаза 2). Раньше проверка копила строки в
 * `errors` на русском языке прямо в компоненте, а `submitForm` вообще
 * игнорировал эти строки: при `errors.length > 0` форма просто молча
 * ничего не отправляла — пользователь не понимал, что произошло, и
 * кнопка выглядела «зависшей». Теперь правило-разница выводится под
 * конкретное поле, submit блокируется, а несовпадение паролей
 * подсвечивает повторный ввод.
 */
const { t } = useI18n()
const store = useStore()
const router = useRouter()

/**
 * Право назначать роль есть у администратора и инструктора.
 *
 * Геттер берётся по полному ключу 'Auth/can' — так же, как в
 * plugins/can.directive.js: в namespaced-модуле короткая запись
 * store.getters.Auth.can в этой сборке vuex не разрешается.
 */
const mayAssignRole = computed(() => store.getters['Auth/can']?.('users.create') ?? false)

/** Группу назначает тот же набор ролей — она определяет учебный план. */
const mayAssignGroup = computed(() => mayAssignRole.value)

const groups = computed(() => store.getters.User.groups || [])

const roleOptions = computed(() =>
  mayAssignRole.value ? ['Администратор', 'Инструктор', 'Обучаемый'] : ['Обучаемый']
)

onMounted(() => {
  // /api/groups закрыт авторизацией, а /reg — публичная страница.
  // Без catch() отклонённый запрос давал unhandled rejection и ронял
  // страницу регистрации. Пользователь всё равно может отправить форму.
  store.dispatch('User/fetchGroups').catch(() => {
    toast.warning(t('register.groupsUnavailable'))
  })
})

const form = useForm({
  schema: {
    fio: { initial: '', rules: [required(t('register.fioRequired'))] },
    role: { initial: '', rules: [] },
    group_id: { initial: null, rules: [] },
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
    // Роль и группу отправляем только если их можно назначать. Иначе
    // поля ушли бы «в пользу», а бэкенд их всё равно проигнорировал бы
    // — расхождение формы и поведения.
    const formData = {
      fio: values.fio,
      role: mayAssignRole.value ? values.role || 'Обучаемый' : 'Обучаемый',
      group_id: mayAssignGroup.value && values.group_id ? values.group_id : '',
      password: values.password,
      password_confirmation: values.password_confirmation,
    }

    await store.dispatch('User/createUser', formData)

    toast.success(t('register.created'))
    await router.back()
  },
})

const submitForm = async () => {
  const ok = await form.submit()

  // Клиентская невалидность не «ошибка отправки»: она уже показана под
  // полями. Тост нужен только когда запрос ушёл и его отвергли.
  if (!ok && form.submitError.value) {
    toast.error(toast.fromError(form.submitError.value, t('register.createFailed')))
  }
}

const cancel = () => {
  router.back()
}

// Открываем наружу для тестов и для вызывающих страниц: в <script setup>
// всё локальное, а старые тесты дёргали wrapper.vm.submitForm/fio.
defineExpose({ submitForm, cancel, form })
</script>
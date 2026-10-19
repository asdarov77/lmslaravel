<template>
  <AuthShell
    :app-name="t('app.title')"
    :headline="t('login.brandHeadline')"
    :tagline="t('login.brandTagline')"
    :stats="stats"
    :quote="quote"
    :tabs="tabs"
    :active="mode"
    :switch-label="t('login.switchLabel')"
    @update:active="setMode"
  >
    <FormCard
      v-if="mode === 'login'"
      :title="t('login.formTitle')"
      :busy="login.submitting.value"
      :submit-text="rateLimit.active.value ? t('login.retryIn', { seconds: rateLimit.seconds.value }) : t('login.submitBtn')"
      :show-cancel="false"
      @submit="submitLogin"
    >
      <FormField :label="t('login.fioOrEmail')" name="fio" :error="login.errors.fio" :required="login.isRequired('fio')">
        <v-text-field
          id="userLogin"
          v-bind="login.bind('fio')"
          name="login"
          :placeholder="t('login.fioOrEmailPlaceholder')"
          prepend-inner-icon="mdi-account-outline"
          autocomplete="username"
          autofocus
        />
        <template #hint>
          <span class="text-caption">{{ t('login.loginHint') }}</span>
        </template>
      </FormField>

      <FormField
        :label="t('login.password')"
        name="password"
        :error="login.errors.password"
        :required="login.isRequired('password')"
      >
        <v-text-field
          id="password"
          v-bind="login.bind('password')"
          name="password"
          :type="showLoginPassword ? 'text' : 'password'"
          :placeholder="t('login.password')"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showLoginPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          autocomplete="current-password"
          @click:append-inner="showLoginPassword = !showLoginPassword"
        />
      </FormField>

      <v-alert
        v-if="rateLimit.active.value"
        type="warning"
        variant="tonal"
        density="compact"
        class="mb-3"
        data-test="login-ratelimit"
        :text="t('login.rateLimitHint', { seconds: rateLimit.seconds.value })"
      />

      <div class="d-flex justify-end mb-2">
        <router-link :to="{ name: 'password.forgot' }" class="text-caption" data-test="login-forgot">
          {{ t('password.forgotTitle') }}
        </router-link>
      </div>
    </FormCard>

    <FormCard
      v-else
      :title="t('users.list.create')"
      :busy="register.submitting.value"
      :submit-text="t('login.register')"
      :show-cancel="false"
      @submit="submitRegister"
    >
      <FormField
        :label="t('register.fio')"
        name="fio"
        :error="register.errors.fio"
        :required="register.isRequired('fio')"
      >
        <v-text-field
          id="userLogin"
          v-bind="register.bind('fio')"
          name="login"
          prepend-inner-icon="mdi-account-outline"
          autocomplete="username"
          autofocus
        />
      </FormField>

      <!--
        Роль назначает только тот, у кого есть users.create. Публичному
        посетителю остаётся «Обучаемый»: раньше список был всегда полным,
        и саморегистрация позволяла выбрать «Администратор».
      -->
      <FormField
        v-if="mayAssignRole"
        :label="t('users.role')"
        name="role"
        :hint="t('users.roleHintReadonly')"
      >
        <v-select v-bind="register.bind('role')" :items="roleOptions" item-value="rolename" item-title="rolename" />
      </FormField>

      <FormField
        v-if="register.values.role === 'Обучаемый' && mayAssignGroup"
        :label="t('register.group')"
        name="group_id"
      >
        <v-select
          v-bind="register.bind('group_id')"
          :items="groups"
          item-value="id"
          item-title="groupname"
        />
      </FormField>

      <FormField
        :label="t('register.password')"
        name="password"
        :error="register.errors.password"
        :required="register.isRequired('password')"
      >
        <v-text-field
          id="password"
          v-bind="register.bind('password')"
          name="password"
          :type="showPassword ? 'text' : 'password'"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          autocomplete="new-password"
          @click:append-inner="showPassword = !showPassword"
        />
      </FormField>

      <FormField
        :label="t('register.passwordRepeat')"
        name="password_confirmation"
        :error="register.errors.password_confirmation"
        :required="register.isRequired('password_confirmation')"
      >
        <v-text-field
          id="password_confirmation"
          v-bind="register.bind('password_confirmation')"
          name="password_confirmation"
          :type="showPassword ? 'text' : 'password'"
          prepend-inner-icon="mdi-lock-check-outline"
          autocomplete="new-password"
        />
      </FormField>
    </FormCard>

    <p v-if="mode === 'login'" class="auth-hint">
      {{ t('login.noAccount') }}
      <button type="button" class="auth-hint__link" data-test="login-switch-to-register" @click="setMode('register')">
        {{ t('login.register') }}
      </button>
    </p>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRoute, useRouter } from 'vue-router'
import AuthShell from '../components/ui/AuthShell.vue'
import FormCard from '../components/ui/FormCard.vue'
import FormField from '../components/ui/FormField.vue'
import useForm from '../composables/useForm'
import { useRateLimitCountdown, retryAfterSeconds } from '../composables/useRateLimitCountdown'
import { required, minLength, sameAs } from '../composables/validation/rules'
import { toast } from '../composables/useToast'

/**
 * Вход и регистрация на одном экране.
 *
 * Раньше это были две страницы, и переход между ними терял введённое:
 * нажал «Регистрация» — оказался на другой форме, вернуться можно было
 * только «Назад». Теперь переключение меняет форму на месте, а
 * выбранный режим живёт в URL (?mode=register) — ссылкой можно
 * поделиться, и кнопка «назад» в браузере работает ожидаемо.
 *
 * Обе формы живут на общих правилах useForm: локализованные сообщения,
 * подсветка поля, блокировка повторной отправки.
 */
const { t } = useI18n()
const store = useStore()
const router = useRouter()
const route = useRoute()

const showPassword = ref(false)
const showLoginPassword = ref(false)

/*
 * Обратный отсчёт после 429.
 *
 * Сообщение «слишком много запросов» без времени бесполезно: человек
 * не знает, когда повторить, и либо жмёт снова (и снова получает 429),
 * либо уходит. Число секунд превращает паузу в ожидание.
 */
const rateLimit = useRateLimitCountdown()

const MODES = ['login', 'register']

const initialMode = () => (MODES.includes(String(route.query.mode ?? '')) ? String(route.query.mode) : 'login')

const mode = ref(initialMode())

// Подписи вкладок намеренно отличаются от подписей кнопок отправки:
// иначе на экране оказывалось два «Вход» подряд (вкладка и кнопка) —
// и вкладка перехватывала клик по кнопке.
const tabs = computed(() => [
  { key: 'login', title: t('login.submitBtn') },
  { key: 'register', title: t('login.registerTab') },
])

const setMode = (next) => {
  if (!MODES.includes(next) || next === mode.value) return

  mode.value = next

  // Режим по умолчанию в URL не пишем: чистая ссылка /login должна
  // открывать вход, а не «/login?mode=login».
  const query = { ...route.query }
  if (next === 'login') delete query.mode
  else query.mode = next

  router.replace({ query }).catch(() => {})

  // Возврат меняет высоту формы; фокус ставим после отрисовки.
  requestAnimationFrame(() => {
    document.getElementById('userLogin')?.focus()
  })
}

// Переход «назад» в браузере меняет query — держим вкладку в синхроне.
router.afterEach((to) => {
  const next = MODES.includes(String(to.query.mode ?? '')) ? String(to.query.mode) : 'login'
  if (next !== mode.value) mode.value = next
})

const stats = computed(() => [
  { value: t('login.statCourses'), label: t('login.statCoursesLabel') },
  { value: t('login.statExams'), label: t('login.statExamsLabel') },
  { value: t('login.statRating'), label: t('login.statRatingLabel') },
])

const quote = computed(() => ({ text: t('login.quoteText'), author: t('login.quoteAuthor') }))

const mayAssignRole = computed(() => store.getters['Auth/can']?.('users.create') ?? false)
const mayAssignGroup = computed(() => mayAssignRole.value)
const groups = computed(() => store.getters['User/groups'] || [])
const roleOptions = ['Администратор', 'Инструктор', 'Обучаемый']

// ------------------------------------------------------------------ вход
const login = useForm({
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

const submitLogin = async () => {
  // Пока тикает ожидание, повторная отправка только сожжёт лимит.
  if (rateLimit.active.value) return

  const ok = await login.submit()
  if (ok) return

  // Разбор по коду ответа: «неверный пароль», «слишком много запросов»
  // и «сервис недоступен» требуют от пользователя разных действий.
  const error = login.submitError.value
  if (!error) return

  const status = error?.response?.status

  if (!error?.response) {
    toast.error(t('login.networkError'))
  } else if (status === 401) {
    toast.error(t('login.wrongPassword'))
  } else if (status === 429) {
    // Время ожидания берём из заголовков ответа (Retry-After /
    // X-RateLimit-Reset), а не придумываем: сервер знает точнее.
    rateLimit.start(retryAfterSeconds(error))
    toast.error(t('login.tooManyRequests'))
  } else if (status >= 500) {
    toast.error(t('login.serverError'))
  } else {
    toast.error(toast.fromError(error, t('login.unknownError')))
  }
}

// ----------------------------------------------------------- регистрация
const register = useForm({
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
    await store.dispatch('User/registerUser', {
      fio: values.fio,
      // Роль и группу отправляем только если их можно назначать: иначе
      // поля ушли бы «в пользу», а бэкенд их всё равно проигнорировал бы.
      role: mayAssignRole.value ? values.role || 'Обучаемый' : 'Обучаемый',
      group_id: mayAssignGroup.value && values.group_id ? values.group_id : '',
      password: values.password,
      password_confirmation: values.password_confirmation,
    })

    toast.success(t('register.created'))

    /*
     * Куда уводить после регистрации.
     *
     * Раньше стоял '/users/list' — адреса такого в роутере нет вообще,
     * человек попадал на пустую страницу. И даже правильный /user/list
     * подходит не всем: у новичка-обучаемого нет users.view, и список
     * его бы отбил на «В доступе отказано». Поэтому адрес зависит от
     * того, кто регистрирует: администратор создаёт сотрудника и
     * возвращается к списку, обучаемый — в свой кабинет.
     */
    const mayListUsers = store.getters['Auth/can']?.('users.view');

    await router.push(mayListUsers ? '/user/list' : '/dashboard')
  },
})

const submitRegister = async () => {
  const ok = await register.submit()
  if (!ok && register.submitError.value) {
    toast.error(toast.fromError(register.submitError.value, t('register.createFailed')))
  }
}

onMounted(() => {
  // /api/groups закрыт авторизацией, а /reg — публичная страница.
  // Без catch() отклонённый запрос давал unhandled rejection.
  if (mayAssignRole.value) {
    store.dispatch('User/fetchGroups').catch(() => {
      toast.warning(t('register.groupsUnavailable'))
    })
  }
})

defineExpose({ mode, setMode, login, register, submitLogin, submitRegister, rateLimit })
</script>

<style scoped>
.auth-hint {
  margin-top: var(--sp-4);
  text-align: center;
  font-size: var(--fs-sm);
  color: var(--c-text-muted);
}

.auth-hint__link {
  padding: 0;
  border: none;
  background: none;
  color: var(--c-primary);
  font: inherit;
  font-weight: var(--fw-medium);
  cursor: pointer;
}

.auth-hint__link:hover {
  text-decoration: underline;
}

.auth-hint__link:focus-visible {
  outline: 2px solid var(--c-accent);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}
</style>

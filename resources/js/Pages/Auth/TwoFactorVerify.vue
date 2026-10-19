<template>
  <AuthShell
    :app-name="t('app.title')"
    :headline="t('login.brandHeadline')"
    :tagline="t('login.brandTagline')"
    :stats="stats"
    :quote="quote"
  >
    <FormCard
      :title="t('app.pages.twoFactor.verifyTitle')"
      :subtitle="t('app.pages.twoFactor.verifySubtitle', { email })"
      :busy="verifyForm.submitting.value"
      :submit-text="t('app.pages.twoFactor.verifyBtn')"
      @submit="submitVerify"
    >
      <FormField
        :label="t('app.pages.twoFactor.codeLabel')"
        name="code"
        :error="verifyForm.errors.code"
        :required="true"
      >
        <v-text-field
          v-bind="verifyForm.bind('code')"
          name="code"
          :type="showCode ? 'text' : 'password'"
          :placeholder="t('app.pages.twoFactor.codePlaceholder')"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showCode ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
          autocomplete="one-time-code"
          maxlength="6"
          @click:append-inner="showCode = !showCode"
        />
      </FormField>

      <v-alert
        v-if="rateLimit.active.value"
        type="warning"
        variant="tonal"
        density="compact"
        class="mb-3"
        :text="t('login.rateLimitHint', { seconds: rateLimit.seconds.value })"
      />

      <p v-if="showRecovery" class="text-caption text-center mt-3">
        {{ t('app.pages.twoFactor.recoveryHint') }}
        <router-link
          :to="{ name: 'password.forgot' }"
          class="ml-1"
        >
          {{ t('app.pages.twoFactor.useRecoveryCode') }}
        </router-link>
      </p>
    </FormCard>

    <p class="auth-hint">
      {{ t('app.pages.twoFactor.backToLogin') }}
      <button
        type="button"
        class="auth-hint__link"
        @click="goBackToLogin"
      >
        {{ t('login.submitBtn') }}
      </button>
    </p>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import AuthShell from '@/components/ui/AuthShell.vue'
import FormCard from '@/components/ui/FormCard.vue'
import FormField from '@/components/ui/FormField.vue'
import useForm from '@/composables/useForm'
import { required, minLength } from '@/composables/validation/rules'
import { useRateLimitCountdown, retryAfterSeconds } from '@/composables/useRateLimitCountdown'
import { toast } from '@/composables/useToast'

const { t } = useI18n()
const store = useStore()
const router = useRouter()
const route = useRoute()

const email = ref(route.query.email || '')
const showCode = ref(false)
const showRecovery = ref(false)

const rateLimit = useRateLimitCountdown()

const verifyForm = useForm({
  schema: {
    code: { initial: '', rules: [required(t('app.pages.twoFactor.codeRequired')), minLength(6, t('app.pages.twoFactor.codeTooShort'))] },
  },

  onSubmit: async ({ values }) => {
    try {
      await store.dispatch('Auth/verifyTwoFactor', {
        email: email.value,
        code: values.code,
      })

      toast.success(t('app.pages.twoFactor.verified'))
      await router.push('/dashboard')
    } catch (error) {
      // Error handled in the dispatch
      console.error('2FA verification failed:', error)
    }
  },
})

const submitVerify = async () => {
  if (rateLimit.active.value) return
  await verifyForm.submit()
}

const goBackToLogin = () => {
  router.push({ name: 'login' })
}

onMounted(() => {
  if (!email.value) {
    router.push({ name: 'login' })
  }
})

defineExpose({ verifyForm, submitVerify, email, showCode })
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
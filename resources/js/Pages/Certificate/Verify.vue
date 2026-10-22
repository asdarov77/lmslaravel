<template>
  <div class="cert-verify u-page">
    <PageHeader
      :title="$t('certificates.verifyTitle')"
      :subtitle="$t('certificates.verifySubtitle')"
      icon="mdi-shield-check-outline"
    />

    <div class="cert-verify__body">
      <v-card class="u-card cert-verify__card">
        <v-card-text>
          <v-form @submit.prevent="verify" data-test="cert-verify-form">
            <v-text-field
              v-model="code"
              :label="$t('certificates.verifyCode')"
              :placeholder="$t('certificates.verifyCodePlaceholder')"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-4"
              data-test="cert-verify-input"
            />
            <v-btn
              color="primary"
              variant="flat"
              type="submit"
              :loading="loading"
              :disabled="loading || !code"
              data-test="cert-verify-submit"
            >
              {{ $t("certificates.verifyAction") }}
            </v-btn>
          </v-form>
        </v-card-text>
      </v-card>

      <v-card v-if="result" class="u-card cert-verify__result" :class="result.valid ? 'cert-verify__result--ok' : 'cert-verify__result--fail'">
        <v-card-text>
          <div class="cert-verify__status">
            <v-icon :icon="result.valid ? 'mdi-check-circle' : 'mdi-close-circle'" size="32" :color="result.valid ? 'success' : 'error'" />
            <span class="cert-verify__status-text">
              {{ result.valid ? $t('certificates.verifyValid') : $t('certificates.verifyInvalid') }}
            </span>
          </div>

          <dl v-if="result.valid" class="cert-verify__details">
            <div class="cert-verify__detail">
              <dt>{{ $t('certificates.verifyFio') }}</dt>
              <dd>{{ result.fio }}</dd>
            </div>
            <div class="cert-verify__detail">
              <dt>{{ $t('certificates.verifyCourse') }}</dt>
              <dd>{{ result.course }}</dd>
            </div>
            <div v-if="result.module_title" class="cert-verify__detail">
              <dt>{{ $t('certificates.verifyModule') }}</dt>
              <dd>{{ result.module_title }}</dd>
            </div>
            <div class="cert-verify__detail">
              <dt>{{ $t('certificates.verifyIssuedAt') }}</dt>
              <dd>{{ result.issued_at }}</dd>
            </div>
          </dl>

          <p v-else class="cert-verify__error">
            {{ result.message }}
          </p>
        </v-card-text>
      </v-card>
    </div>
  </div>
</template>

<script>
import $api from '../../api/httpClient'
import PageHeader from '../../components/ui/PageHeader.vue'

export default {
  name: 'CertificateVerify',
  components: { PageHeader },
  data() {
    return {
      code: '',
      loading: false,
      result: null,
    }
  },
  mounted() {
    if (this.$route.params.code) {
      this.code = this.$route.params.code
      this.verify()
    }
  },
  methods: {
    async verify() {
      if (!this.code || this.loading) return

      this.loading = true
      this.result = null

      try {
        const response = await $api.get(`/api/certificates/verify/${encodeURIComponent(this.code.trim())}`)
        this.result = { valid: true, ...response.data.data }
      } catch (e) {
        const message = e?.response?.data?.error?.message
          || e?.response?.data?.message
          || this.$t('certificates.verifyNotFound')
        this.result = { valid: false, message }
      } finally {
        this.loading = false
      }
    },
  },
}
</script>

<style scoped>
.cert-verify__body {
  max-width: 620px;
}

.cert-verify__card {
  margin-bottom: var(--sp-4);
}

.cert-verify__status {
  align-items: center;
  display: flex;
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}

.cert-verify__status-text {
  font-size: var(--fs-lg);
  font-weight: 600;
}

.cert-verify__details {
  display: grid;
  gap: var(--sp-2);
  margin: 0;
}

.cert-verify__detail {
  display: flex;
  gap: var(--sp-3);
}

.cert-verify__detail dt {
  color: var(--c-text-muted);
  font-size: var(--fs-sm);
  min-width: 120px;
}

.cert-verify__detail dd {
  font-weight: 500;
  margin: 0;
}

.cert-verify__error {
  color: var(--c-error);
}
</style>

<template>
  <div class="cert-settings">
    <PageHeader
      :title="$t('certSettings.pageTitle')"
      :subtitle="$t('certSettings.subtitle')"
      icon="mdi-certificate-outline"
    />

    <v-row>
      <v-col cols="12" md="6">
        <FormCard :title="$t('certSettings.formTitle')">
          <v-form ref="form" @submit.prevent="handleSave">
            <!--
              Поля — через слот FormField, а не через v-model на самом
              FormField: иначе <label for> остаётся без цели, поле не
              получает id и подпись не работает как подпись. Именно так
              поля размечены в остальных формах проекта.
            -->
            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.organization')"
              :hint="$t('certSettings.hints.organization')"
              :error="form.errors.organization"
            >
              <v-text-field :id="id" v-bind="form.bind('organization')" hide-details />
            </FormField>

            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.signatoryName')"
              :error="form.errors.signatoryName"
            >
              <v-text-field :id="id" v-bind="form.bind('signatoryName')" hide-details />
            </FormField>

            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.signatoryTitle')"
              :error="form.errors.signatoryTitle"
            >
              <v-text-field :id="id" v-bind="form.bind('signatoryTitle')" hide-details />
            </FormField>

            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.footerNote')"
              :hint="$t('certSettings.hints.footerNote')"
              :error="form.errors.footerNote"
            >
              <v-textarea :id="id" v-bind="form.bind('footerNote')" rows="2" hide-details />
            </FormField>

            <!--
              Логотип. Превью показывается сразу при выборе файла, до
              сохранения: человек должен увидеть результат, а не пустое
              место с надписью «сохраните, чтобы увидеть».
            -->
            <div class="cert-settings__logo">
              <p class="u-label">{{ $t('certSettings.fields.logo') }}</p>

              <img
                v-if="previewLogo"
                :src="previewLogo"
                class="cert-settings__logo-img"
                alt=""
                data-test="cert-settings-logo-preview"
              />

              <v-btn
                outlined
                small
                color="primary"
                data-test="cert-settings-logo-pick"
                @click="pickLogo"
                :disabled="form.submitting"
              >
                {{ $t('certSettings.logoPick') }}
              </v-btn>

              <v-btn
                v-if="form.values.logo_url && !removeLogo"
                text
                small
                color="error"
                data-test="cert-settings-logo-clear"
                @click="removeLogo = true"
                :disabled="form.submitting"
              >
                {{ $t('certSettings.logoClear') }}
              </v-btn>

              <input
                ref="logoInput"
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                class="d-none"
                data-test="cert-settings-logo-input"
                @change="onLogoSelected"
              />
            </div>

            <v-alert
              v-if="form.serverError"
              type="error"
              dense
              text
              class="mt-3"
              data-test="cert-settings-error"
            >
              {{ form.serverError }}
            </v-alert>

            <FormActions
              :submit-text="$t('certSettings.actions.save')"
              :busy="form.submitting"
            />
          </v-form>
        </FormCard>
      </v-col>

      <v-col cols="12" md="6">
        <!--
          Предпросмотр того же листа, что уходит на печать.
          Дублирует разметку намеренно: отдельная «демка» быстро
          разошлась бы с боевым бланком, и настройщик правил бы одну
          картинку, а печаталась бы другая.
        -->
        <FormCard :title="$t('certSettings.previewTitle')">
          <div class="cert-sheet cert-sheet--preview">
            <header class="cert-sheet__head">
              <img v-if="previewLogo" :src="previewLogo" class="cert-sheet__logo" alt="" />
              <p class="cert-sheet__org">{{
                form.values.organization || $t('certificates.organization')
              }}</p>
              <h2 class="cert-sheet__title">{{ $t('certificates.title') }}</h2>
            </header>

            <p class="cert-sheet__body">
              {{ $t('certificates.issuedTo', { fio: $t('certSettings.previewFio') }) }}
            </p>
            <p class="cert-sheet__body">{{ $t('certSettings.previewCourse') }}</p>

            <p v-if="form.values.footerNote" class="cert-sheet__note">{{ form.values.footerNote }}</p>

            <footer class="cert-sheet__foot">
              <div v-if="form.values.signatoryName" class="cert-sheet__sign">
                <span class="cert-sheet__sign-line"></span>
                <span class="cert-sheet__sign-name">{{ form.values.signatoryName }}</span>
                <span v-if="form.values.signatoryTitle" class="cert-sheet__sign-title">
                  {{ form.values.signatoryTitle }}
                </span>
              </div>
              <p class="cert-sheet__verify">{{ $t('certSettings.previewHint') }}</p>
            </footer>
          </div>
        </FormCard>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import $api from '../../api/httpClient'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import FormActions from '../../components/ui/FormActions.vue'
import { unwrapResponse } from '../../api/envelope'
import useForm from '../../composables/useForm'
import { toast } from '../../composables/useToast'

const { t } = useI18n()
const store = useStore()
const router = useRouter()

// File upload state (not in useForm - handled separately)
const logo = ref(null)
const preview = ref(null)
const removeLogo = ref(false)

const form = useForm({
  fields: [
    'organization',
    'signatoryName',
    'signatoryTitle',
    'footerNote',
    'logo_url',
  ],
  initialValues: {
    organization: '',
    signatoryName: '',
    signatoryTitle: '',
    footerNote: '',
    logo_url: null,
  },
  draftKey: 'cert-settings',
  draftDelay: 800,
  validate: (values) => {
    const errors = {}
    if (!values.organization?.trim()) {
      errors.organization = t('certSettings.errors.organizationRequired')
    }
    if (!values.signatoryName?.trim()) {
      errors.signatoryName = t('certSettings.errors.signatoryNameRequired')
    }
    if (!values.signatoryTitle?.trim()) {
      errors.signatoryTitle = t('certSettings.errors.signatoryTitleRequired')
    }
    return Object.keys(errors).length ? errors : true
  },
  onSubmit: async ({ values, form: api }) => {
    const body = new FormData()

    for (const key of ['organization', 'signatoryName', 'signatoryTitle', 'footerNote']) {
      body.append(key, values[key]?.trim() ?? '')
    }

    if (logo.value) body.append('logo', logo.value)
    if (removeLogo.value) body.append('remove_logo', '1')

    const response = await $api.put('/api/certificates/settings', body)

    // Clean up object URL
    if (preview.value) {
      URL.revokeObjectURL(preview.value)
      preview.value = null
    }

    logo.value = null
    removeLogo.value = false

    const data = response?.data?.data || {}
    return {
      logo_url: data.logo_url || null,
    }
  },
})

const previewLogo = computed(() => {
  if (removeLogo.value) return null
  return preview.value || form.values.logo_url
})

const load = async () => {
  try {
    const response = await $api.get('/api/certificates/settings')
    const data = unwrapResponse(response) || {}

    form.values.organization = data.organization || ''
    form.values.signatoryName = data.signatory_name || ''
    form.values.signatoryTitle = data.signatory_title || ''
    form.values.footerNote = data.footer_note || ''
    form.values.logo_url = data.logo_url || null

    form.clearDraft()
  } catch (e) {
    form.serverError = form.messageOf(e, 'certSettings.errors.load')
  }
}

const pickLogo = () => {
  form.logoInput?.click()
}

const onLogoSelected = (event) => {
  const file = event.target.files?.[0]
  event.target.value = ''

  if (!file) return

  logo.value = file
  removeLogo.value = false
  preview.value = URL.createObjectURL(file)
}

const handleSave = async () => {
  form.serverError = null
  const result = await form.submit()

  if (result) {
    form.values.logo_url = result.logo_url
    toast.success(t('certSettings.actions.saved'))
  }
}

onMounted(load)

// Expose for tests
defineExpose({
  form,
  logo,
  preview,
  removeLogo,
  previewLogo,
  load,
  pickLogo,
  onLogoSelected,
  handleSave,
})
</script>

<script>
import PageHeader from "../../components/ui/PageHeader.vue";
import FormCard from "../../components/ui/FormCard.vue";
import FormField from "../../components/ui/FormField.vue";
import FormActions from "../../components/ui/FormActions.vue";

export default {
  name: "CertificateSettings",
  components: { PageHeader, FormCard, FormField, FormActions },
}
</script>
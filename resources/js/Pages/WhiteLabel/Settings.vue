<template>
  <div class="white-label">
    <PageHeader
      :title="$t('whiteLabel.pageTitle')"
      :subtitle="$t('whiteLabel.pageHint')"
      icon="mdi-palette"
    />

    <v-alert v-if="error" type="error" dense text class="mb-4" data-test="white-label-error">
      {{ error }}
    </v-alert>

    <v-row>
      <!-- Форма настроек -->
      <v-col cols="12" lg="7">
        <FormCard :title="$t('whiteLabel.formTitle')">
          <v-form ref="form" @submit.prevent="save">
            <!-- Организация -->
            <div class="white-label__section">
              <h3 class="white-label__section-title">{{ $t('whiteLabel.sections.organization') }}</h3>

              <FormField
                v-slot="{ id }"
                :label="$t('whiteLabel.fields.organizationName')"
                :hint="$t('whiteLabel.hints.organizationName')"
              >
                <v-text-field :id="id" v-model="form.organization_name" hide-details />
              </FormField>

              <FormField
                v-slot="{ id }"
                :label="$t('whiteLabel.fields.organizationShort')"
                :hint="$t('whiteLabel.hints.organizationShort')"
              >
                <v-text-field :id="id" v-model="form.organization_short" hide-details />
              </FormField>
            </div>

            <!-- Цвета: Light тема -->
            <div class="white-label__section">
              <h3 class="white-label__section-title">{{ $t('whiteLabel.sections.colorsLight') }}</h3>

              <div class="white-label__color-row">
                <ColorPickerField
                  :label="$t('whiteLabel.fields.primaryColor')"
                  v-model="form.primary_color"
                  :hint="$t('whiteLabel.hints.primaryColor')"
                  @update:model-value="onColorChange('primary_color', $event)"
                />

                <ColorPickerField
                  :label="$t('whiteLabel.fields.accentColor')"
                  v-model="form.accent_color"
                  :hint="$t('whiteLabel.hints.accentColor')"
                  @update:model-value="onColorChange('accent_color', $event)"
                />
              </div>

              <div class="white-label__color-row">
                <ColorPickerField
                  :label="$t('whiteLabel.fields.gradientStart')"
                  v-model="form.gradient_start"
                  :hint="$t('whiteLabel.hints.gradientStart')"
                  @update:model-value="onColorChange('gradient_start', $event)"
                />

                <ColorPickerField
                  :label="$t('whiteLabel.fields.gradientEnd')"
                  v-model="form.gradient_end"
                  :hint="$t('whiteLabel.hints.gradientEnd')"
                  @update:model-value="onColorChange('gradient_end', $event)"
                />
              </div>

              <div class="white-label__color-row">
                <ColorPickerField
                  :label="$t('whiteLabel.fields.primaryLighten')"
                  v-model="form.primary_lighten_color"
                  @update:model-value="onColorChange('primary_lighten_color', $event)"
                />

                <ColorPickerField
                  :label="$t('whiteLabel.fields.primaryDarken')"
                  v-model="form.primary_darken_color"
                  @update:model-value="onColorChange('primary_darken_color', $event)"
                />
              </div>

              <div class="white-label__color-row">
                <ColorPickerField
                  :label="$t('whiteLabel.fields.accentLighten')"
                  v-model="form.accent_lighten_color"
                  @update:model-value="onColorChange('accent_lighten_color', $event)"
                />

                <ColorPickerField
                  :label="$t('whiteLabel.fields.accentDarken')"
                  v-model="form.accent_darken_color"
                  @update:model-value="onColorChange('accent_darken_color', $event)"
                />
              </div>
            </div>

            <!-- Цвета: Dark тема -->
            <div class="white-label__section">
              <h3 class="white-label__section-title">{{ $t('whiteLabel.sections.colorsDark') }}</h3>

              <div class="white-label__color-row">
                <ColorPickerField
                  :label="$t('whiteLabel.fields.darkBg')"
                  v-model="form.dark_bg"
                  :hint="$t('whiteLabel.hints.darkBg')"
                  @update:model-value="onColorChange('dark_bg', $event)"
                />

                <ColorPickerField
                  :label="$t('whiteLabel.fields.darkSurface')"
                  v-model="form.dark_surface"
                  @update:model-value="onColorChange('dark_surface', $event)"
                />
              </div>

              <div class="white-label__color-row">
                <ColorPickerField
                  :label="$t('whiteLabel.fields.darkPrimary')"
                  v-model="form.dark_primary"
                  @update:model-value="onColorChange('dark_primary', $event)"
                />

                <ColorPickerField
                  :label="$t('whiteLabel.fields.darkAccent')"
                  v-model="form.dark_accent"
                  @update:model-value="onColorChange('dark_accent', $event)"
                />
              </div>
            </div>

            <!-- Логотипы и фавикон -->
            <div class="white-label__section">
              <h3 class="white-label__section-title">{{ $t('whiteLabel.sections.assets') }}</h3>

              <LogoUpload
                :label="$t('whiteLabel.fields.logoLight')"
                :hint="$t('whiteLabel.hints.logoLight')"
                v-model:file="form.logo_light"
                :current-url="form.logo_light_url"
                :remove-flag="form.remove_logo_light"
                @update:remove-flag="v => form.remove_logo_light = v"
                data-test="white-label-logo-light"
              />

              <LogoUpload
                :label="$t('whiteLabel.fields.logoDark')"
                :hint="$t('whiteLabel.hints.logoDark')"
                v-model:file="form.logo_dark"
                :current-url="form.logo_dark_url"
                :remove-flag="form.remove_logo_dark"
                @update:remove-flag="v => form.remove_logo_dark = v"
                data-test="white-label-logo-dark"
              />

              <LogoUpload
                :label="$t('whiteLabel.fields.favicon')"
                :hint="$t('whiteLabel.hints.favicon')"
                v-model:file="form.favicon"
                :current-url="form.favicon_url"
                :remove-flag="form.remove_favicon"
                @update:remove-flag="v => form.remove_favicon = v"
                accept="image/svg+xml,image/png,image/x-icon"
                data-test="white-label-favicon"
              />
            </div>

            <v-alert v-if="error" type="error" dense text class="mt-3" data-test="white-label-error">
              {{ error }}
            </v-alert>

            <FormActions
              :submit-text="$t('whiteLabel.actions.save')"
              :busy="saving"
            />
          </v-form>
        </FormCard>
      </v-col>

      <!-- Живой превью -->
      <v-col cols="12" lg="5">
        <FormCard :title="$t('whiteLabel.previewTitle')">
          <div class="white-label__preview">
            <div class="white-label__preview-header">
              <img
                v-if="previewLogoLight"
                :src="previewLogoLight"
                class="white-label__preview-logo"
                alt=""
              />
              <span class="white-label__preview-org">
                {{ form.organization_name || $t('whiteLabel.defaultOrg') }}
              </span>
            </div>

            <div class="white-label__preview-content">
              <!-- Кнопки-превью основных токенов -->
              <div class="white-label__preview-tokens">
                <div
                  class="white-label__token"
                  :style="{ background: previewPrimary }"
                  :title="$t('whiteLabel.tokens.primary')"
                >
                  <span>Primary</span>
                  <code>{{ previewPrimary }}</code>
                </div>
                <div
                  class="white-label__token"
                  :style="{ background: previewAccent }"
                  :title="$t('whiteLabel.tokens.accent')"
                >
                  <span>Accent</span>
                  <code>{{ previewAccent }}</code>
                </div>
                <div
                  class="white-label__token white-label__token--gradient"
                  :style="gradientStyle"
                  :title="$t('whiteLabel.tokens.gradient')"
                >
                  <span>Gradient</span>
                  <code>{{ previewGradientStart }} → {{ previewGradientEnd }}</code>
                </div>
              </div>

              <!-- Превью кнопок и компонентов -->
              <div class="white-label__preview-components">
                <v-btn
                  variant="flat"
                  color="primary"
                  class="ma-2"
                >
                  {{ $t('whiteLabel.previewBtnPrimary') }}
                </v-btn>
                <v-btn
                  variant="outlined"
                  color="primary"
                  class="ma-2"
                >
                  {{ $t('whiteLabel.previewBtnOutlined') }}
                </v-btn>
                <v-btn
                  variant="tonal"
                  :style="{ '--v-theme-primary': previewAccent }"
                  class="ma-2"
                >
                  {{ $t('whiteLabel.previewBtnAccent') }}
                </v-btn>

                <v-chip
                  color="primary"
                  variant="flat"
                  class="ma-2"
                >
                  {{ $t('whiteLabel.previewChipPrimary') }}
                </v-chip>
                <v-chip
                  :style="{ '--c-accent': previewAccent }"
                  variant="outlined"
                  class="ma-2"
                >
                  {{ $t('whiteLabel.previewChipAccent') }}
                </v-chip>

                <v-text-field
                  :label="$t('whiteLabel.previewFieldLabel')"
                  variant="outlined"
                  class="ma-2"
                  style="max-width: 240px;"
                  readonly
                />
              </div>

              <!-- Dark mode preview toggle -->
              <div class="white-label__preview-dark">
                <v-switch
                  v-model="previewDark"
                  :label="$t('whiteLabel.previewDarkLabel')"
                  class="ma-4"
                />
                <div
                  v-if="previewDark"
                  class="white-label__dark-preview"
                  :style="darkPreviewStyle"
                >
                  <v-card class="ma-4" style="max-width: 320px;">
                    <v-card-title class="text-h6">
                      {{ $t('whiteLabel.previewDarkCardTitle') }}
                    </v-card-title>
                    <v-card-text>
                      {{ $t('whiteLabel.previewDarkCardText') }}
                    </v-card-text>
                    <v-card-actions>
                      <v-btn variant="flat" color="primary">
                        {{ $t('whiteLabel.previewDarkBtnPrimary') }}
                      </v-btn>
                      <v-btn variant="outlined" color="primary">
                        {{ $t('whiteLabel.previewDarkBtnOutlined') }}
                      </v-btn>
                    </v-card-actions>
                  </v-card>
                </div>
              </div>
            </div>
          </div>
        </FormCard>
      </v-col>
    </v-row>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import $api from '../../api/httpClient'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import FormActions from '../../components/ui/FormActions.vue'
import ColorPickerField from '../../components/ui/ColorPickerField.vue'
import LogoUpload from '../../components/ui/LogoUpload.vue'
import { applyBrand } from '../../utils/whiteLabel'

export default {
  name: 'WhiteLabelSettings',

  components: {
    PageHeader,
    FormCard,
    FormField,
    FormActions,
    ColorPickerField,
    LogoUpload,
  },

  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      previewDark: false,

      form: {
        // Organization
        organization_name: '',
        organization_short: '',

        // Light theme colors
        primary_color: '#1976d2',
        primary_lighten_color: '#42a5f5',
        primary_darken_color: '#1565c0',
        accent_color: '#00bcd4',
        accent_lighten_color: '#4dd0e1',
        accent_darken_color: '#0097a7',
        gradient_start: '#1976d2',
        gradient_end: '#00bcd4',

        // Dark theme colors
        dark_bg: '#0e1116',
        dark_surface: '#161a20',
        dark_primary: '#64b5f6',
        dark_accent: '#4dd0e1',

        // Assets
        logo_light: null,
        logo_dark: null,
        favicon: null,
        logo_light_url: null,
        logo_dark_url: null,
        favicon_url: null,
        remove_logo_light: false,
        remove_logo_dark: false,
        remove_favicon: false,
      },
    }
  },

  computed: {
    previewPrimary() {
      return this.form.primary_color
    },
    previewAccent() {
      return this.form.accent_color
    },
    previewGradientStart() {
      return this.form.gradient_start
    },
    previewGradientEnd() {
      return this.form.gradient_end
    },
    gradientStyle() {
      return {
        background: `linear-gradient(135deg, ${this.form.gradient_start}, ${this.form.gradient_end})`,
      }
    },
    previewLogoLight() {
      return this.form.logo_light ? URL.createObjectURL(this.form.logo_light) : this.form.logo_light_url
    },
    previewLogoDark() {
      return this.form.logo_dark ? URL.createObjectURL(this.form.logo_dark) : this.form.logo_dark_url
    },
    darkPreviewStyle() {
      return {
        background: this.form.dark_bg,
        color: 'var(--c-text-inverse)',
        border: `1px solid ${this.form.dark_surface}`,
        borderRadius: '12px',
        padding: '16px',
      }
    },
  },

  async mounted() {
    await this.load()
  },

  watch: {
    // Живой превью: при изменении цветов применяем их сразу
    'form.primary_color': function (val) { this.applyPreview({ '--v-theme-primary': val }) },
    'form.primary_lighten_color': function (val) { this.applyPreview({ '--v-theme-primary-lighten': val }) },
    'form.primary_darken_color': function (val) { this.applyPreview({ '--v-theme-primary-darken': val }) },
    'form.accent_color': function (val) { this.applyPreview({ '--c-accent': val }) },
    'form.accent_lighten_color': function (val) { this.applyPreview({ '--c-accent-lighten': val }) },
    'form.accent_darken_color': function (val) { this.applyPreview({ '--c-accent-darken': val }) },
    'form.gradient_start': function (val) { this.applyPreview({ '--c-brand-gradient-1': val }) },
    'form.gradient_end': function (val) { this.applyPreview({ '--c-brand-gradient-2': val }) },
    'form.dark_bg': function (val) { this.applyPreview({ '--v-theme-bg': val }) },
    'form.dark_surface': function (val) { this.applyPreview({ '--v-theme-surface': val }) },
    'form.dark_primary': function (val) { this.applyPreview({ '--v-theme-primary': val }) },
    'form.dark_accent': function (val) { this.applyPreview({ '--c-accent': val }) },
  },

  methods: {
    async load() {
      this.loading = true
      try {
        const response = await $api.get('/api/white-label')
        const data = response?.data?.data ?? {}

        // Заполняем форму
        const fields = [
          'organization_name', 'organization_short',
          'primary_color', 'primary_lighten_color', 'primary_darken_color',
          'accent_color', 'accent_lighten_color', 'accent_darken_color',
          'gradient_start', 'gradient_end',
          'dark_bg', 'dark_surface', 'dark_primary', 'dark_accent',
        ]
        fields.forEach(f => { this.form[f] = data[f] ?? this.form[f] })

        this.form.logo_light_url = data.logo_light_url
        this.form.logo_dark_url = data.logo_dark_url
        this.form.favicon_url = data.favicon_url

        // Применяем загруженные переменные к превью
        const vars = data.css_variables ?? {}
        Object.entries(vars).forEach(([k, v]) => {
          this.applyPreview({ [k]: v })
        })
      } catch (e) {
        this.error = this.messageOf(e, 'whiteLabel.errors.load')
      } finally {
        this.loading = false
      }
    },

    applyPreview(vars) {
      // Применяем к превью-области через CSS-переменные на корне превью-контейнера
      // (вместо глобального :root, чтобы не ломать остальное приложение)
      const container = this.$refs.previewContainer
      if (container) {
        Object.entries(vars).forEach(([key, value]) => {
          container.style.setProperty(key, value)
        })
      }
    },

    onColorChange(key, value) {
      this.form[key] = value
    },

    async save() {
      if (this.saving) return
      this.saving = true
      this.error = null

      try {
        const body = new FormData()

        // Все поля формы
        const scalarFields = [
          'organization_name', 'organization_short',
          'primary_color', 'primary_lighten_color', 'primary_darken_color',
          'accent_color', 'accent_lighten_color', 'accent_darken_color',
          'gradient_start', 'gradient_end',
          'dark_bg', 'dark_surface', 'dark_primary', 'dark_accent',
        ]
        scalarFields.forEach(f => { body.append(f, this.form[f]) })

        // Файлы
        if (this.form.logo_light) body.append('logo_light', this.form.logo_light)
        if (this.form.logo_dark) body.append('logo_dark', this.form.logo_dark)
        if (this.form.favicon) body.append('favicon', this.form.favicon)

        // Флаги удаления
        if (this.form.remove_logo_light) body.append('remove_logo_light', '1')
        if (this.form.remove_logo_dark) body.append('remove_logo_dark', '1')
        if (this.form.remove_favicon) body.append('remove_favicon', '1')

        const response = await $api.put('/api/white-label', body)
        const data = response?.data?.data ?? {}

        // Обновляем URL файлов
        this.form.logo_light_url = data.logo_light_url
        this.form.logo_dark_url = data.logo_dark_url
        this.form.favicon_url = data.favicon_url

        // Применяем новые CSS-переменные глобально (через whiteLabel composable)
        const vars = data.css_variables ?? {}
        this.applyGlobal(vars)
      } catch (e) {
        this.error = this.messageOf(e, 'whiteLabel.errors.save')
      } finally {
        this.saving = false
      }
    },

    applyGlobal(vars) {
      // Используем утилиту из whiteLabel composable
      import('../../utils/whiteLabel').then(m => m.applyBrand(vars))
    },

    messageOf(error, fallbackKey) {
      return error?.response?.data?.message || this.$t(fallbackKey)
    },
  },
}
</script>

<style scoped>
.white-label__section {
  margin-bottom: 24px;
}

.white-label__section-title {
  font-size: 0.9375rem;
  font-weight: 600;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.white-label__color-row {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.white-label__preview {
  min-height: 500px;
}

.white-label__preview-header {
  align-items: center;
  display: flex;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.white-label__preview-logo {
  height: 40px;
  object-fit: contain;
  width: auto;
}

.white-label__preview-org {
  font-size: 1.125rem;
  font-weight: 600;
}

.white-label__preview-content {
  padding: 24px;
}

.white-label__preview-tokens {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}

.white-label__token {
  align-items: center;
  border-radius: 8px;
  color: var(--c-on-primary);
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 140px;
  padding: 16px;
}

.white-label__token--gradient {
  min-width: 200px;
}

.white-label__token code {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 4px;
  font-size: 0.6875rem;
  padding: 2px 6px;
}

.white-label__preview-components {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 24px;
}

.white-label__preview-dark {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  padding-top: 16px;
}

.white-label__dark-preview {
  transition: all 0.3s ease;
}
</style>
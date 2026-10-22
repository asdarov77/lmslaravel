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
          <v-form ref="form" @submit.prevent="save">
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
            >
              <v-text-field :id="id" v-model="form.organization" hide-details />
            </FormField>

            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.signatoryName')"
            >
              <v-text-field :id="id" v-model="form.signatoryName" hide-details />
            </FormField>

            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.signatoryTitle')"
            >
              <v-text-field :id="id" v-model="form.signatoryTitle" hide-details />
            </FormField>

            <FormField
              v-slot="{ id }"
              :label="$t('certSettings.fields.footerNote')"
              :hint="$t('certSettings.hints.footerNote')"
            >
              <v-textarea :id="id" v-model="form.footerNote" rows="2" hide-details />
            </FormField>

            <!--
              Логотип. Превью показывается сразу при выборе файла, до
              сохранения: человек должен увидеть результат, а не пустое
              место с надписью «сохраните, чтобы увидеть».
            -->
            <div class="cert-settings__logo">
              <p class="u-label">{{ $t('certSettings.fields.logo') }}</p>

              <img
                v-if="preview"
                :src="preview"
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
              >
                {{ $t('certSettings.logoPick') }}
              </v-btn>

              <v-btn
                v-if="form.logo_url && !removeLogo"
                text
                small
                color="error"
                data-test="cert-settings-logo-clear"
                @click="removeLogo = true"
              >
                {{ $t('certSettings.logoClear') }}
              </v-btn>

              <input
                ref="logoInput"
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                class="d-none"
                @change="onLogoSelected"
              />
            </div>

            <v-alert
              v-if="error"
              type="error"
              dense
              text
              class="mt-3"
              data-test="cert-settings-error"
            >
              {{ error }}
            </v-alert>

            <FormActions
              :submit-text="$t('certSettings.actions.save')"
              :busy="saving"
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
                form.organization || $t('certificates.organization')
              }}</p>
              <h2 class="cert-sheet__title">{{ $t('certificates.title') }}</h2>
            </header>

            <p class="cert-sheet__body">
              {{ $t('certificates.issuedTo', { fio: $t('certSettings.previewFio') }) }}
            </p>
            <p class="cert-sheet__body">{{ $t('certSettings.previewCourse') }}</p>

            <p v-if="form.footerNote" class="cert-sheet__note">{{ form.footerNote }}</p>

            <footer class="cert-sheet__foot">
              <div v-if="form.signatoryName" class="cert-sheet__sign">
                <span class="cert-sheet__sign-line"></span>
                <span class="cert-sheet__sign-name">{{ form.signatoryName }}</span>
                <span v-if="form.signatoryTitle" class="cert-sheet__sign-title">
                  {{ form.signatoryTitle }}
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

<script>
import $api from "../../api/httpClient";
import PageHeader from "../../components/ui/PageHeader.vue";
import FormCard from "../../components/ui/FormCard.vue";
import FormField from "../../components/ui/FormField.vue";
import FormActions from "../../components/ui/FormActions.vue";

export default {
  name: "CertificateSettings",

  components: { PageHeader, FormCard, FormField, FormActions },

  data() {
    return {
      loading: false,
      saving: false,
      error: null,

      form: {
        organization: "",
        signatoryName: "",
        signatoryTitle: "",
        footerNote: "",
        logo_url: null,
      },

      logo: null,
      preview: null,
      removeLogo: false,
    };
  },

  computed: {
    /**
     * Логотип для предпросмотра.
     *
     * Несохранённый файл важнее сохранённого: человек выбрал картинку
     * и смотрит на неё, а не на ту, что лежала до его правки.
     */
    previewLogo() {
      if (this.removeLogo) return null;

      return this.preview || this.form.logo_url;
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;

      try {
        const response = await $api.get("/api/certificates/settings");
        const data = response?.data?.data || {};

        this.form.organization = data.organization || "";
        this.form.signatoryName = data.signatory_name || "";
        this.form.signatoryTitle = data.signatory_title || "";
        this.form.footerNote = data.footer_note || "";
        this.form.logo_url = data.logo_url || null;
      } catch (e) {
        this.error = this.messageOf(e, "certSettings.errors.load");
      } finally {
        this.loading = false;
      }
    },

    pickLogo() {
      this.$refs.logoInput.click();
    },

    onLogoSelected(event) {
      const file = event.target.files?.[0];

      // Сброс значения: повторный выбор того же файла иначе не вызовет
      // change, и замена логотипа выглядела бы сломанной.
      event.target.value = "";

      if (!file) return;

      this.logo = file;
      this.removeLogo = false;
      this.preview = URL.createObjectURL(file);
    },

    async save() {
      if (this.saving) return;

      this.saving = true;
      this.error = null;

      try {
        const body = new FormData();

        for (const key of ["organization", "signatoryName", "signatoryTitle", "footerNote"]) {
          body.append(key, this.form[key]);
        }

        if (this.logo) body.append("logo", this.logo);

        // remove_logo отправляем всегда: иначе нельзя было бы убрать
        // логотип — пустой файл сервер счёл бы «не менять».
        if (this.removeLogo) body.append("remove_logo", "1");

        const response = await $api.put("/api/certificates/settings", body);
        const data = response?.data?.data || {};

        this.form.logo_url = data.logo_url || null;
        this.logo = null;
        this.removeLogo = false;

        // objectURL живёт до перезагрузки страницы; без отзыва браузер
        // держит файл в памяти всё время сессии.
        if (this.preview) {
          URL.revokeObjectURL(this.preview);
          this.preview = null;
        }
      } catch (e) {
        this.error = this.messageOf(e, "certSettings.errors.save");
      } finally {
        this.saving = false;
      }
    },

    messageOf(error, fallbackKey) {
      return error?.response?.data?.message
        || error?.response?.data?.error
        || this.$t(fallbackKey);
    },
  },
};
</script>

<style scoped>
.cert-settings__logo {
  align-items: center;
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: var(--sp-4);
}

.cert-settings__logo-img {
  border: 1px solid var(--v-theme-surface-variant);
  height: 96px;
  object-fit: contain;
  width: auto;
}

/*
 * Предпросмотр повторяет размеры печатного листа, поэтому проверять
 * бланк можно на экране: что видно здесь, то выйдет на печати.
 */
.cert-sheet--preview {
  margin: 0 auto;
  max-width: 620px;
}
</style>
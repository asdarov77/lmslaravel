<template>
  <div class="cert-page">
    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="article" />
    </div>

    <div v-else-if="error" class="u-card u-card__body" data-test="cert-error">
      <EmptyState
        icon="mdi-certificate-outline"
        :title="$t('certificates.errorTitle')"
        :text="error"
      />
    </div>

    <template v-else-if="certificate">
      <!-- Панель управления не печатается: на листе нужны рамка,
           текст и код проверки, а не кнопки интерфейса. -->
      <div class="u-card u-card__body cert-actions no-print">
        <v-btn color="primary" prepend-icon="mdi-printer" data-test="cert-print" @click="print">
          {{ $t('certificates.print') }}
        </v-btn>
        <v-btn variant="text" prepend-icon="mdi-content-copy" data-test="cert-copy" @click="copyCode">
          {{ $t('certificates.copyCode') }}
        </v-btn>
        <v-alert v-if="copied" type="success" density="compact" class="mt-2">
          {{ $t('certificates.codeCopied') }}
        </v-alert>
      </div>

      <article class="cert-sheet" data-test="cert-sheet">
        <header class="cert-sheet__head">
          <p class="cert-sheet__org">{{ $t('certificates.organization') }}</p>
          <h1 class="cert-sheet__title">{{ $t('certificates.title') }}</h1>
        </header>

        <p class="cert-sheet__lead">{{ $t('certificates.issuedTo') }}</p>
        <p class="cert-sheet__fio">{{ certificate.fio }}</p>

        <p class="cert-sheet__lead">{{ $t('certificates.courseCompleted') }}</p>
        <p class="cert-sheet__course">{{ certificate.course }}</p>

        <p v-if="certificate.module_title" class="cert-sheet__module">
          {{ $t('certificates.module') }}: {{ certificate.module_title }}
        </p>

        <dl class="cert-sheet__facts">
          <div>
            <dt>{{ $t('certificates.period') }}</dt>
            <dd>{{ certificate.study_from }} — {{ certificate.study_to }}</dd>
          </div>
          <div>
            <dt>{{ $t('certificates.lessons') }}</dt>
            <dd>{{ certificate.lessons_done }} / {{ certificate.lessons_total }}</dd>
          </div>
          <div v-if="certificate.exams_total">
            <dt>{{ $t('certificates.exams') }}</dt>
            <dd>{{ certificate.exams_passed }} / {{ certificate.exams_total }}</dd>
          </div>
          <div>
            <dt>{{ $t('certificates.issuedAt') }}</dt>
            <dd>{{ certificate.issued_at }}</dd>
          </div>
        </dl>

        <footer class="cert-sheet__foot">
          <div class="cert-sheet__code">
            <span class="cert-sheet__code-label">{{ $t('certificates.code') }}</span>
            <span class="cert-sheet__code-value" data-test="cert-code">{{ certificate.code }}</span>
          </div>
          <p class="cert-sheet__verify">{{ $t('certificates.verifyHint') }}</p>
        </footer>
      </article>
    </template>
  </div>
</template>

<script>
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import EmptyState from '../../components/ui/EmptyState.vue'

/**
 * Печатный сертификат об окончании курса.
 *
 * Это HTML, а не готовый PDF: генератора PDF в проекте нет, а печать
 * браузера («Сохранить как PDF») даёт тот же документ с тем же кодом
 * проверки. Главное отличие от прочих страниц — режим печати: принтер
 * не должен выводить меню и кнопки, только рамку с текстом.
 */
export default {
  name: 'CertificateView',
  components: { EmptyState },
  props: {
    idEdit: { type: [Number, String], required: true },
  },
  data() {
    return {
      certificate: null,
      loading: true,
      error: '',
      copied: false,
    }
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      this.error = ''

      try {
        const response = await $api.get(`/api/my/certificates/${this.idEdit}`)
        this.certificate = unwrapResponse(response)
      } catch (error) {
        this.certificate = null
        // Формулировка сервера здесь полезна: «курс ещё не завершён»
        // объясняет, чего не хватает, в отличие от голого 403.
        this.error =
          error?.response?.data?.message || this.$t('certificates.loadFailed')
      } finally {
        this.loading = false
      }
    },

    print() {
      window.print()
    },

    async copyCode() {
      const code = this.certificate?.code

      if (!code) return

      try {
        await navigator.clipboard.writeText(code)
        this.copied = true
        setTimeout(() => {
          this.copied = false
        }, 2500)
      } catch (error) {
        // Буфер обмена может быть недоступен без https или разрешения:
        // код всё равно напечатан на листе, копирование не обязательно.
      }
    },
  },
}
</script>

<style scoped>
.cert-page {
  margin: 0 auto;
  max-width: 900px;
}

.cert-actions {
  margin-bottom: var(--sp-4);
}

.cert-sheet {
  background: var(--c-surface);
  border: 3px double var(--c-border-strong);
  color: var(--c-text);
  padding: var(--sp-8, 48px);
}

.cert-sheet__head {
  border-bottom: 1px solid var(--c-border);
  padding-bottom: var(--sp-4);
  text-align: center;
}

.cert-sheet__org {
  color: var(--c-text-secondary);
  font-size: 0.8125rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.cert-sheet__title {
  font-size: 1.75rem;
  letter-spacing: 0.04em;
  margin-top: var(--sp-2);
  text-transform: uppercase;
}

.cert-sheet__lead {
  color: var(--c-text-secondary);
  margin-top: var(--sp-6);
}

.cert-sheet__fio {
  font-size: 1.5rem;
  font-weight: 600;
}

.cert-sheet__course {
  font-size: 1.25rem;
  font-weight: 600;
}

.cert-sheet__module {
  color: var(--c-text-secondary);
  margin-top: var(--sp-1);
}

.cert-sheet__facts {
  display: grid;
  gap: var(--sp-3) var(--sp-6);
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  margin-top: var(--sp-6);
}

.cert-sheet__facts dt {
  color: var(--c-text-secondary);
  font-size: 0.8125rem;
}

.cert-sheet__facts dd {
  font-weight: 600;
  margin: 0;
}

.cert-sheet__foot {
  border-top: 1px solid var(--c-border);
  margin-top: var(--sp-6);
  padding-top: var(--sp-4);
}

.cert-sheet__code {
  align-items: baseline;
  display: flex;
  gap: var(--sp-3);
  justify-content: center;
}

.cert-sheet__code-label {
  color: var(--c-text-secondary);
  font-size: 0.8125rem;
}

.cert-sheet__code-value {
  font-family: var(--font-mono, monospace);
  font-size: 1.125rem;
  letter-spacing: 0.16em;
}

.cert-sheet__verify {
  color: var(--c-text-secondary);
  font-size: 0.75rem;
  margin-top: var(--sp-2);
  text-align: center;
}

/**
 * Печать.
 *
 * На листе нужен только сертификат: панель кнопок скрывается, поля
 * страницы уменьшаются, чтобы рамка не обрезалась бумагой.
 */
@media print {
  .no-print {
    display: none !important;
  }

  /* Печать идёт на белую бумагу: в тёмной теме лист иначе залился бы
     тёмным, и сертификат было бы не читать. Печатаем всегда светлым
     независимо от экранной темы. */
  .cert-sheet {
    background: white;
    color: black;
    border-color: black;
    page-break-inside: avoid;
  }

  @page {
    margin: 12mm;
  }
}
</style>
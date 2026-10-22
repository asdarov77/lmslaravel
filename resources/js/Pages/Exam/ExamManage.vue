<template>
  <div class="u-page">
    <PageHeader
      :title="$t('exams.title')"
      :subtitle="$t('exams.subtitle')"
      icon="mdi-clipboard-check-outline"
    >
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          :to="{ name: 'exams.create' }"
          data-test="exam-create-btn"
        >
          <v-icon start icon="mdi-plus" size="18" aria-hidden="true"></v-icon>
          {{ $t('exams.create') }}
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="error" type="error" dense text class="mb-4" data-test="exams-error">
      {{ error }}
    </v-alert>

    <v-row dense>
      <v-col cols="12" md="4">
        <v-card class="exams__filters">
          <div class="u-card__head">
            <h2 class="u-card__title">{{ $t('exams.filters') }}</h2>
          </div>
          <v-card-text>
            <v-text-field
              v-model="filters.query"
              :label="$t('exams.filter.query')"
              :placeholder="$t('exams.filter.queryPlaceholder')"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @update:model-value="scheduleReload"
            />
            <v-select
              v-model="filters.category_id"
              :items="categories"
              item-value="id"
              item-title="title"
              :label="$t('exams.filter.category')"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @update:model-value="scheduleReload"
            />
            <v-select
              v-model="filters.module_id"
              :items="modules"
              item-value="id"
              item-title="title"
              :label="$t('exams.filter.module')"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @update:model-value="scheduleReload"
            />
            <v-select
              v-model="filters.status"
              :items="statusItems"
              item-value="value"
              item-title="label"
              :label="$t('exams.filter.status')"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              @update:model-value="scheduleReload"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card>
          <div class="d-flex justify-space-between align-center mb-4">
            <p class="u-page__subtitle" data-test="exams-count">
              {{ $t("exams.found", { count: exams.length, all: meta.total }) }}
            </p>
          </div>

          <div v-if="loading" class="d-flex justify-center py-8">
            <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
          </div>

          <EmptyState
            v-else-if="!exams.length"
            :icon="'mdi-clipboard-text-off-outline'"
            :title="$t('exams.emptyTitle')"
            :text="$t('exams.emptyText')"
          ></EmptyState>

          <div v-else class="exams__list">
            <article
              v-for="exam in exams"
              :key="exam.id"
              class="u-card exams__item"
            >
              <div class="exams__item-main">
                <div class="d-flex align-center exams__item-head">
                  <h2 class="exams__item-title">{{ exam.title }}</h2>
                  <span class="u-badge" :class="statusBadge(exam.state)">
                    {{ statusLabel(exam.state) }}
                  </span>
                </div>

                <dl class="exams__facts">
                  <div v-if="exam.module_title" class="exams__fact">
                    <dt>{{ $t("exams.module") }}</dt>
                    <dd>{{ exam.module_title }}</dd>
                  </div>
                  <div v-if="exam.category_title" class="exams__fact">
                    <dt>{{ $t("exams.category") }}</dt>
                    <dd>{{ exam.category_title }}</dd>
                  </div>
                  <div v-if="exam.window" class="exams__fact">
                    <dt>{{ $t("exams.window") }}</dt>
                    <dd>{{ exam.window }}</dd>
                  </div>
                  <div class="exams__fact">
                    <dt>{{ $t("exams.attempts") }}</dt>
                    <dd>
                      {{ $t("exams.attemptsUsedOf", { used: exam.attempts_used, total: exam.max_attempts }) }}
                    </dd>
                  </div>
                  <div class="exams__fact">
                    <dt>{{ $t("exams.passingScore") }}</dt>
                    <dd>{{ Math.round(exam.passing_score * 100) }}%</dd>
                  </div>
                </dl>
              </div>

              <div class="exams__item-actions">
                <v-btn
                  variant="text"
                  :to="{ name: 'exams.edit', params: { idEdit: exam.id } }"
                  size="small"
                  :aria-label="$t('exams.edit') + ': ' + exam.title"
                >
                  <v-icon start icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
                  {{ $t('exams.edit') }}
                </v-btn>
                <v-btn
                  variant="text"
                  color="error"
                  size="small"
                  @click="askDelete(exam)"
                  :aria-label="$t('exams.delete') + ': ' + exam.title"
                >
                  <v-icon start icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
                  {{ $t('exams.delete') }}
                </v-btn>
              </div>
            </article>
          </div>

          <div v-if="meta.total > exams.length" class="mt-4">
            <v-pagination
              v-model="page"
              :length="Math.ceil(meta.total / limit)"
              :total-visible="7"
              @update:model-value="load"
            ></v-pagination>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/ui/PageHeader.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import $api from '../../api/httpClient'

export default {
  name: 'ExamManage',

  components: { EmptyState, PageHeader },

  data() {
    return {
      loading: false,
      error: null,
      exams: [],
      meta: { total: 0 },
      page: 1,
      limit: 15,
      timer: null,

      filters: {
        query: '',
        category_id: null,
        module_id: null,
        status: null,
      },

      categories: [],
      modules: [],

      statusItems: [
        { value: 'planned', label: 'Запланирован' },
        { value: 'available', label: 'Доступен' },
        { value: 'closed', label: 'Закрыт' },
        { value: 'exhausted', label: 'Попытки исчерпаны' },
      ],
    }
  },

  computed: {
    statusBadgeClass() {
      const classes = {
        planned: 'u-badge--warning',
        available: 'u-badge--success',
        closed: 'u-badge--error',
        exhausted: 'u-badge--warning',
      }
      return (state) => classes[state] ?? ''
    },

    statusLabel(state) {
      const labels = {
        planned: 'Запланирован',
        available: 'Доступен',
        closed: 'Закрыт',
        exhausted: 'Попытки исчерпаны',
      }
      return labels[state] ?? state
    },
  },

  async mounted() {
    await this.loadReferences()
    await this.load()
  },

  watch: {
    filters: {
      handler() { this.scheduleReload() },
      deep: true,
    },
  },

  methods: {
    /**
     * Справочники для фильтров: специальности и классы (самолёты).
     *
     * Адрес — /api/classes, а НЕ /api/aircrafts: последнего маршрута
     * на бэкенде нет вообще. Ответ 404 от этого запроса уводил всё
     * приложение на страницу «Страница не найдена» — раздел
     * «Управление экзаменами» переставал открываться целиком.
     */
    async loadReferences() {
      try {
        const [catsRes, modsRes] = await Promise.all([
          $api.get('/api/categories', { optional: true }),
          $api.get('/api/classes', { optional: true }),
        ])
        this.categories = catsRes?.data?.data ?? []
        this.modules = modsRes?.data?.data ?? []
      } catch (e) {
        console.warn('[ExamManage] loadReferences:', e?.response?.data?.message || e.message)
      }
    },

    async load() {
      this.loading = true
      try {
        const params = {
          page: this.page,
          limit: this.limit,
        }
        if (this.filters.query) params.q = this.filters.query
        if (this.filters.category_id) params.category_id = this.filters.category_id
        if (this.filters.module_id) params.module_id = this.filters.module_id
        if (this.filters.status) params.status = this.filters.status

        const response = await $api.get('/api/exams', { params })
        const data = response.data.data

        this.exams = data.data ?? []
        this.meta = data.meta ?? { total: 0 }
      } catch (e) {
        this.error = e?.response?.data?.message || this.$t('exams.loadError')
      } finally {
        this.loading = false
      }
    },

    scheduleReload() {
      if (this.timer) clearTimeout(this.timer)
      this.timer = setTimeout(() => this.load(), 300)
    },

    askDelete(exam) {
      this.deleteDialog = true
      this.pendingId = exam.id
      this.deleteTitle = exam.title
    },

    async confirmDelete() {
      if (!this.pendingId) return
      this.saving = true
      try {
        await $api.delete(`/api/exams/${this.pendingId}`)
        this.deleteDialog = false
        await this.load()
      } catch (e) {
        this.showError(e)
      } finally {
        this.saving = false
        this.pendingId = null
      }
    },

    showError(error) {
      this.error = error?.response?.data?.message || this.$t('exams.loadError')
      setTimeout(() => { this.error = null }, 5000)
    },
  },
}
</script>

<style scoped>
.exams__filters {
  height: fit-content;
}

.exams__list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.exams__item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--sp-4);
  padding: var(--sp-4);
}

.exams__item-main {
  flex: 1;
  min-width: 0;
}

.exams__item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
}

.exams__item-title {
  font-size: var(--fs-lg);
  font-weight: 600;
  margin: 0;
  word-break: break-word;
}

.exams__facts {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: var(--sp-3);
  margin-bottom: var(--sp-3);
}

.exams__fact {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}

.exams__fact dt {
  font-size: var(--fs-xs);
  font-weight: 500;
  color: var(--c-text-muted);
}

.exams__fact dd {
  font-size: var(--fs-sm);
  color: var(--c-text);
  margin: 0;
}

.exams__item-actions {
  display: flex;
  gap: var(--sp-2);
  flex-shrink: 0;
}

.exams__filters {
  height: fit-content;
}

.exams__filters .u-card__head {
  padding: var(--sp-3) var(--sp-4);
  margin-bottom: var(--sp-3);
}

.exams__filters v-card-text {
  padding: 0 var(--sp-4) var(--sp-4);
}
</style>
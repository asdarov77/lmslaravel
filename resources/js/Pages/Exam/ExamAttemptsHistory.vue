<template>
  <div class="u-page">
    <PageHeader
      :title="$t('exams.attemptsHistory')"
      :subtitle="$t('exams.attemptsHistoryHint')"
    >
      <template #actions>
        <v-btn
          color="primary"
          variant="tonal"
          :loading="loading"
          :disabled="loading"
          @click="load"
        >
          <v-icon start icon="mdi-refresh" size="18" aria-hidden="true"></v-icon>
          {{ $t("common.refresh") }}
        </v-btn>
      </template>
    </PageHeader>

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate :aria-label="$t('common.loading')"></v-progress-circular>
    </div>

    <EmptyState
      v-else-if="!attempts.length"
      :icon="'mdi-clipboard-text-off-outline'"
      :title="$t('exams.noAttemptsTitle')"
      :text="$t('exams.noAttemptsText')"
    ></EmptyState>

    <div v-else class="exams__list">
      <article v-for="attempt in attempts" :key="attempt.id" class="u-card exams__item">
        <div class="exams__item-main">
          <div class="d-flex align-center exams__item-head">
            <h2 class="exams__item-title">{{ attempt.exam?.title || attempt.exam_id }}</h2>
            <span class="u-badge" :class="verdictBadge(attempt)">
              {{ verdictLabel(attempt) }}
            </span>
          </div>

          <dl class="exams__facts">
            <div class="exams__fact">
              <dt>{{ $t("exams.attemptDate") }}</dt>
              <dd>{{ formatDate(attempt.submitted_at) }}</dd>
            </div>
            <div class="exams__fact">
              <dt>{{ $t("exams.score") }}</dt>
              <dd>
                <span class="u-badge" :class="scoreBadge(attempt)">
                  {{ attempt.score_percent }}%
                </span>
              </dd>
            </div>
            <div class="exams__fact">
              <dt>{{ $t("exams.correct") }}</dt>
              <dd>{{ attempt.correct_count }} / {{ attempt.total_count }}</dd>
            </div>
            <div class="exams__fact">
              <dt>{{ $t("exams.passingScore") }}</dt>
              <dd>{{ attempt.passing_score_percent }}%</dd>
            </div>
            <div v-if="attempt.attempt_number" class="exams__fact">
              <dt>{{ $t("exams.attemptNumber") }}</dt>
              <dd>#{{ attempt.attempt_number }}</dd>
            </div>
          </dl>
        </div>

        <div class="exams__item-actions">
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            :to="{ name: 'exams.take', params: { idEdit: attempt.exam_id } }"
          >
            <v-icon start icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
            {{ $t("exams.retry") }}
          </v-btn>
        </div>
      </article>
    </div>
  </div>
</template>

<script>
import { unwrapResponse } from '../../api/envelope'
import * as examApi from '../../api/exam.api'
import PageHeader from '../../components/ui/PageHeader.vue'
import EmptyState from '../../components/ui/EmptyState.vue'

export default {
  name: 'ExamAttemptsHistory',
  components: { PageHeader, EmptyState },
  data() {
    return {
      loading: true,
      attempts: [],
    }
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      try {
        const response = await examApi.fetchAttempts()
        this.attempts = unwrapResponse(response) || []
      } catch (error) {
        this.attempts = []
      } finally {
        this.loading = false
      }
    },

    formatDate(value) {
      if (!value) return ''
      return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    },

    verdictLabel(attempt) {
      return attempt.passed
        ? this.$t('exams.passed')
        : this.$t('exams.notPassed')
    },

    verdictBadge(attempt) {
      return attempt.passed ? 'u-badge--success' : 'u-badge--warning'
    },

    scoreBadge(attempt) {
      const passed = attempt.passed || (attempt.score_percent >= (attempt.passing_score_percent || 0))
      return passed ? 'u-badge--success' : 'u-badge--warning'
    },
  },
}
</script>

<style scoped>
.exams__list {
  display: grid;
  gap: var(--sp-3);
}

.exams__item {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: var(--sp-4);
  align-items: start;
}

@media (max-width: 600px) {
  .exams__item {
    grid-template-columns: 1fr;
  }
}

.exams__item-head {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}

.exams__item-title {
  margin: 0;
  font-size: var(--fs-lg);
  font-weight: 600;
}

.exams__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--sp-2);
  margin: var(--sp-3) 0;
}

.exams__fact dt {
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
  margin-bottom: 2px;
}

.exams__fact dd {
  margin: 0;
  font-weight: 500;
}

.exams__item-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--sp-2);
  min-width: 140px;
}
</style>
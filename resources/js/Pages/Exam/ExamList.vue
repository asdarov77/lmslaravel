<template>
  <div class="u-page">
    <PageHeader :title="$t('exams.title')" :subtitle="$t('exams.subtitle')">
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

    <div class="exams__stats">
      <div v-for="stat in stats" :key="stat.key" class="u-card exams__stat">
        <span class="exams__stat-value">{{ stat.value }}</span>
        <span class="exams__stat-label">{{ stat.label }}</span>
      </div>
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
      <article v-for="exam in exams" :key="exam.id" class="u-card exams__item">
        <div class="exams__item-main">
          <div class="d-flex align-center exams__item-head">
            <h2 class="exams__item-title">{{ exam.title }}</h2>
            <span class="u-badge" :class="stateBadge(exam.state)">
              {{ exam.state_label }}
            </span>
            <span v-if="exam.passed" class="u-badge u-badge--success">
              {{ $t("exams.passed") }}
            </span>
          </div>

          <dl class="exams__facts">
            <div v-if="exam.module_title" class="exams__fact">
              <dt>{{ $t("plan.module") }}</dt>
              <dd>{{ exam.module_title }}</dd>
            </div>
            <div v-if="exam.category_title" class="exams__fact">
              <dt>{{ $t("plan.specialties") }}</dt>
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
            <div v-if="exam.best_score !== null" class="exams__fact">
              <dt>{{ $t("exams.bestScore") }}</dt>
              <dd>
                <span class="u-badge" :class="exam.best_score >= exam.passing_score ? 'u-badge--success' : 'u-badge--warning'">
                  {{ Math.round(exam.best_score * 100) }}%
                </span>
              </dd>
            </div>
          </dl>
        </div>

        <div class="exams__item-actions">
          <v-btn
            v-if="exam.available"
            color="primary"
            variant="flat"
            size="small"
            :to="{ name: 'exams.take', params: { idEdit: exam.id } }"
          >
            <v-icon start icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
            {{ exam.attempts_used ? $t("exams.retry") : $t("exams.start") }}
          </v-btn>
          <span v-else class="exams__closed">{{ $t(`exams.state.${exam.state}`) }}</span>
        </div>
      </article>
    </div>

    <AppToast v-model="alert" :type="alertType" :text="alertText"></AppToast>
  </div>
</template>

<script>
import PageHeader from "../../components/ui/PageHeader.vue";
import EmptyState from "../../components/ui/EmptyState.vue";
import AppToast from "../../components/ui/AppToast.vue";
import examApi from "../../api/exam.api";

/**
 * Экзамены обучаемого.
 *
 * Раньше пункт меню «Экзамены» вёл сразу на страницу прохождения, где
 * вопросы выбирались по модулю и специальности в адресной строке. У
 * обучаемого не было видно, что ему назначено: что можно сдать, когда
 * открыто и сколько попыток осталось.
 */
export default {
  name: "ExamList",

  components: { PageHeader, EmptyState, AppToast },

  data() {
    return {
      exams: [],
      loading: false,
      alert: false,
      alertType: "success",
      alertText: "",
    };
  },

  computed: {
    stats() {
      return [
        { key: "total", value: this.exams.length, label: this.$t("exams.statTotal") },
        {
          key: "available",
          value: this.exams.filter((e) => e.available).length,
          label: this.$t("exams.statAvailable"),
        },
        {
          key: "passed",
          value: this.exams.filter((e) => e.passed).length,
          label: this.$t("exams.statPassed"),
        },
      ];
    },
  },

  created() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;

      try {
        this.exams = examApi.myExams(await examApi.fetchMyExams());
      } catch (e) {
        this.exams = [];
        this.notify(this.$t("exams.loadError"), "error");
      } finally {
        this.loading = false;
      }
    },

    /**
     * Окно доступности одной строкой: «02.10.2026 — 11.10.2026».
     * Даты разбираем вручную, чтобы не сдвинуть на сутки из-за зоны.
     */
    windowOf(exam) {
      const from = exam.opens_at ? this.formatDate(exam.opens_at) : null;
      const to = exam.closes_at ? this.formatDate(exam.closes_at) : (exam.due_at ? this.formatDate(exam.due_at) : null);

      if (from && to) return `${from} — ${to}`;
      if (to) return `${this.$t("exams.until")} ${to}`;
      return this.$t("exams.noWindow");
    },

    formatDate(value) {
      const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || "");
      return m ? `${m[3]}.${m[2]}.${m[1]}` : value;
    },

    stateBadge(state) {
      if (state === "available") return "u-badge--success";
      if (state === "planned") return "u-badge--muted";
      if (state === "closed") return "u-badge--muted";
      return "u-badge--danger";
    },

    notify(text, type = "success") {
      this.alertText = text;
      this.alertType = type;
      this.alert = true;
    },
  },
};
</script>

<style scoped>
.exams__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}

.exams__stat {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  padding: var(--sp-4);
}

.exams__stat-value {
  font-size: 1.75rem;
  font-weight: 600;
  line-height: 1.1;
}

.exams__stat-label {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.exams__list {
  display: grid;
  gap: var(--sp-3);
}

.exams__item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--sp-4);
  padding: var(--sp-4);
}

.exams__item-head {
  gap: var(--sp-2);
  flex-wrap: wrap;
  margin-bottom: var(--sp-3);
}

.exams__item-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 600;
}

.exams__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--sp-3);
  margin: 0;
}

.exams__fact dt {
  color: var(--c-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: var(--sp-1);
}

.exams__fact dd {
  margin: 0;
  font-size: 0.875rem;
}

.exams__item-actions {
  flex-shrink: 0;
}

.exams__closed {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

@media (max-width: 720px) {
  .exams__item {
    flex-direction: column;
  }

  .exams__item-actions {
    width: 100%;
  }
}
</style>

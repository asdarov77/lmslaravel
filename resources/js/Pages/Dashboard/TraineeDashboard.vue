<template>
  <div class="u-page">
    <PageHeader :title="$t('dashboard.title')" :subtitle="$t('dashboard.subtitle')">
      <template #head>
        <p class="dash__greeting">
          {{ $t("dashboard.greeting") }},
          <strong>{{ userName }}</strong>
        </p>
      </template>
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          :to="{ name: 'learning.plan' }"
        >
          <v-icon start icon="mdi-calendar-month-outline" size="18" aria-hidden="true"></v-icon>
          {{ $t("dashboard.openPlan") }}
        </v-btn>
      </template>
    </PageHeader>

    <!-- Ключевые показатели: как в Moodle «Dashboard» / Canvas «Dash» -->
    <div class="dash__stats">
      <div v-for="stat in stats" :key="stat.key" class="u-card dash__stat">
        <span class="dash__stat-icon" :class="`dash__stat-icon--${stat.tone}`">
          <v-icon :icon="stat.icon" size="20" aria-hidden="true"></v-icon>
        </span>
        <div>
          <span class="dash__stat-value">{{ stat.value }}</span>
          <span class="dash__stat-label">{{ stat.label }}</span>
        </div>
      </div>
    </div>

    <div class="dash__grid">
      <!--
        Продолжить обучение.

        Первый экран после входа, поэтому он идёт первым: конкретный курс
        и кнопка, ведущая в него. Список всего плана ниже по странице —
        он отвечает на другой вопрос («что вообще назначено»).
      -->
      <section v-if="continueItem" class="u-card dash__panel dash__continue" data-test="dash-continue">
        <h2 class="u-card__title">{{ $t("dashboard.continueTitle") }}</h2>
        <p class="u-page__subtitle">{{ continueHint }}</p>

        <div class="dash__continue-body">
          <div>
            <span class="dash__list-title">{{ continueItem.title }}</span>
            <span class="dash__list-sub">
              {{ continueItem.module_title || continueItem.aircraft }}
            </span>
          </div>
          <span class="u-badge" :class="statusBadge(continueItem.status)">
            {{ $t(`plan.status.${continueItem.status}`) }}
          </span>
        </div>

        <v-btn
          class="mt-4"
          color="primary"
          :to="{
            name: 'courses.itemmani',
            query: { idEdit: continueItem.course_id },
          }"
          data-test="dash-continue-go"
        >
          {{ $t("dashboard.continueAction") }}
          <v-icon end icon="mdi-arrow-right" size="16" aria-hidden="true"></v-icon>
        </v-btn>
      </section>

      <!-- Прогресс обучения -->
      <section class="u-card dash__panel">
        <h2 class="u-card__title">{{ $t("dashboard.progress") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.progressHint") }}</p>

        <div class="dash__progress">
          <v-progress-circular
            :model-value="progressPercent"
            :size="104"
            :width="10"
            color="primary"
            bg-color="surface"
          >
            <span class="dash__progress-value">{{ progressPercent }}%</span>
          </v-progress-circular>
        </div>
      </section>

      <!-- Подходит к завершению -->
      <section class="u-card dash__panel">
        <h2 class="u-card__title">{{ $t("dashboard.upcoming") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.upcomingHint") }}</p>

        <ul v-if="upcoming.length" class="dash__list">
          <li v-for="row in upcoming" :key="row.course_id" class="dash__list-row">
            <span class="dash__list-title">{{ row.title }}</span>
            <span
              class="u-badge"
              :class="deadlineBadge(row.days_left)"
            >
              {{ deadlineLabel(row) }}
            </span>
          </li>
        </ul>
        <p v-else class="dash__muted">{{ $t("dashboard.upcomingEmpty") }}</p>
      </section>

      <!-- Экзамены -->
      <section class="u-card dash__panel">
        <h2 class="u-card__title">{{ $t("dashboard.exams") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.examsHint") }}</p>

        <dl v-if="exams.attempts" class="dash__facts">
          <div class="dash__fact">
            <dt>{{ $t("dashboard.examAttempts") }}</dt>
            <dd>{{ exams.attempts }}</dd>
          </div>
          <div class="dash__fact">
            <dt>{{ $t("dashboard.examPassed") }}</dt>
            <dd>{{ exams.passed }}</dd>
          </div>
          <div class="dash__fact">
            <dt>{{ $t("dashboard.examAvg") }}</dt>
            <dd>{{ exams.avg_result != null ? `${Math.round(exams.avg_result * 100)}%` : "—" }}</dd>
          </div>
        </dl>
        <p v-else class="dash__muted">{{ $t("dashboard.examsEmpty") }}</p>

        <v-btn
          class="mt-4"
          color="primary"
          variant="tonal"
          size="small"
          :to="{ name: 'questions' }"
        >
          <v-icon start icon="mdi-clipboard-check-outline" size="18" aria-hidden="true"></v-icon>
          {{ $t("dashboard.goExams") }}
        </v-btn>
      </section>

      <!-- Мои курсы: краткий список назначений -->
      <section class="u-card dash__panel">
        <h2 class="u-card__title">{{ $t("dashboard.myCourses") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.myCoursesHint") }}</p>

        <ul v-if="plan.length" class="dash__list">
          <li v-for="item in plan.slice(0, 6)" :key="item.id" class="dash__list-row">
            <div>
              <span class="dash__list-title">{{ item.course_title }}</span>
              <span class="dash__list-sub">{{ item.module_title || item.aircraft }}</span>
            </div>
            <span class="u-badge" :class="statusBadge(item.status)">
              {{ $t(`plan.status.${item.status}`) }}
            </span>
          </li>
        </ul>
        <p v-else class="dash__muted">{{ $t("plan.emptyText") }}</p>

        <v-btn
          class="mt-4"
          variant="text"
          size="small"
          :to="{ name: 'learning.plan' }"
        >
          {{ $t("dashboard.openPlan") }}
          <v-icon end icon="mdi-arrow-right" size="16" aria-hidden="true"></v-icon>
        </v-btn>
      </section>
    </div>

  </div>
</template>

<script>
import { mapState } from "vuex";
import PageHeader from "../../components/ui/PageHeader.vue";
import learningApi from "../../api/learning.api";
import { toast } from "../../composables/useToast";

/**
 * Дашборд обучаемого.
 *
 * Классическая структура LMS-кабинета (Moodle Dashboard / Canvas Dash):
 * показатели сверху, затем «что заканчивается», «экзамены», «мои курсы».
 * До этого /auk был личной страницей с обрывками Bulma-разметки и
 * счётчиками пользователей/групп, которые обучаемому ничего не давали.
 */
export default {
  name: "TraineeDashboard",

  components: { PageHeader },

  data() {
    return {
      summary: {},
      plan: [],
    };
  },

  computed: {
    ...mapState("Auth", ["user"]),

    userName() {
      return this.user?.fio || this.user?.name || "";
    },

    courses() {
      return this.summary?.courses || {};
    },

    exams() {
      return this.summary?.exams || {};
    },

    upcoming() {
      return this.summary?.upcoming || [];
    },

    progressPercent() {
      return this.summary?.progress?.percent ?? 0;
    },

    /** Курс, который стоит открыть следующим (см. backend continue). */
    continueItem() {
      return this.summary?.continue ?? null;
    },

    /*
     * Подсказка под заголовком зависит от того, начал ли обучаемый этот
     * курс: «начать» и «продолжить» — разные обещания, и обещать
     * продолжение тому, кто ещё не открывал курс, нечестно.
     */
    continueHint() {
      return this.continueItem?.started
        ? this.$t("dashboard.continueStartedHint")
        : this.$t("dashboard.continueNewHint");
    },

    /**
     * Показатели. Значения приходят с бэкенда, поэтому здесь только
     * подпись, тон и иконка — сам расчёт в одном месте (контроллере).
     */
    stats() {
      return [
        {
          key: "courses",
          value: this.courses.distinct ?? 0,
          label: this.$t("dashboard.statCourses"),
          icon: "mdi-book-open-page-variant-outline",
          tone: "primary",
        },
        {
          key: "active",
          value: this.courses.active ?? 0,
          label: this.$t("dashboard.statActive"),
          icon: "mdi-progress-clock",
          tone: "info",
        },
        {
          key: "progress",
          value: `${this.progressPercent}%`,
          label: this.$t("dashboard.statProgress"),
          icon: "mdi-chart-donut",
          tone: "success",
        },
        {
          key: "exams",
          value: this.exams.passed ?? 0,
          label: this.$t("dashboard.statExams"),
          icon: "mdi-clipboard-check-outline",
          tone: "warning",
        },
      ];
    },
  },

  created() {
    this.load();
  },

  methods: {
    async load() {
      try {
        // Сводка и список назначений идут параллельно: дашборд не должен
        // ждать второй запрос, а учебный план нужен для блока «Мои курсы».
        const [dashRes, planRes] = await Promise.all([
          learningApi.fetchMyDashboard(),
          learningApi.fetchMyLearning(),
        ]);

        this.summary = learningApi.dashboard(dashRes);
        this.plan = learningApi.planItems(planRes);
      } catch (e) {
        // Пустые данные — валидное состояние (нет группы, нет курсов).
        // Сообщаем об ошибке только если пришёл отказ сервера.
        if (e?.response?.status && e.response.status >= 400) {
          this.notify(this.$t("dashboard.loadError"), "error");
        }
      }
    },

    /**
     * Дедлайн: сегодня/завтра/через N дней. Без слов «осталось N дней»
     * для каждого варианта в отдельном ключе — формат собирается из двух.
     */
    deadlineLabel(row) {
      if (row.days_left === 0) return this.$t("dashboard.deadlineToday");
      if (row.days_left === 1) return this.$t("dashboard.deadlineTomorrow");
      return this.$t("dashboard.deadlineInDays", { days: row.days_left });
    },

    deadlineBadge(daysLeft) {
      if (daysLeft <= 1) return "u-badge--danger";
      return daysLeft <= 3 ? "u-badge--warning" : "u-badge--muted";
    },

    statusBadge(status) {
      if (status === "completed") return "u-badge--success";
      if (status === "planned") return "u-badge--muted";
      return "u-badge--warning";
    },

    notify(text, type = "success") {
      toast.byType(type, text);
    },
  },
};
</script>

<style scoped>
.dash__greeting {
  margin: var(--sp-2) 0 0;
  font-size: 0.9375rem;
  color: var(--c-text-secondary);
}

.dash__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}

.dash__stat {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4);
}

.dash__stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  flex-shrink: 0;
}

.dash__stat-icon--primary { background: var(--c-primary-soft); color: var(--c-primary); }
.dash__stat-icon--info    { background: var(--c-info-soft);    color: var(--c-info); }
.dash__stat-icon--success { background: var(--c-success-soft); color: var(--c-success); }
.dash__stat-icon--warning { background: var(--c-warning-soft); color: var(--c-warning); }

.dash__stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 600;
  line-height: 1.2;
}

.dash__stat-label {
  display: block;
  font-size: 0.8125rem;
  color: var(--c-text-muted);
}

.dash__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: var(--sp-3);
  align-items: start;
}

.dash__panel {
  padding: var(--sp-4);
}

.dash__progress {
  display: flex;
  justify-content: center;
  padding: var(--sp-4) 0 var(--sp-2);
}

.dash__progress-value {
  font-size: 1.375rem;
  font-weight: 600;
}

.dash__facts {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--sp-3);
  margin: var(--sp-3) 0 0;
}

.dash__fact dt {
  color: var(--c-text-muted);
  font-size: 0.75rem;
  margin-bottom: var(--sp-1);
}

.dash__fact dd {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.dash__list {
  list-style: none;
  margin: var(--sp-3) 0 0;
  padding: 0;
  display: grid;
  gap: var(--sp-2);
}

.dash__list-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-2) 0;
  border-bottom: 1px solid var(--c-border);
}

.dash__list-row:last-child {
  border-bottom: none;
}

.dash__list-title {
  display: block;
  font-size: 0.9375rem;
}

.dash__list-sub {
  display: block;
  font-size: 0.8125rem;
  color: var(--c-text-muted);
}

.dash__muted {
  margin: var(--sp-3) 0 0;
  color: var(--c-text-muted);
  font-size: 0.875rem;
}
</style>

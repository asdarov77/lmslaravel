<template>
  <div class="u-page">
    <PageHeader :title="$t('dashboard.title')" :subtitle="$t('dashboard.subtitle')">
      <template #head>
        <!-- Герой-блок приветствия с градиентным акцентом — первое, что
             видит обучаемый после входа. Задаёт тон всему кабинету. -->
        <div class="dash__hero">
          <p class="dash__hero-greeting">{{ $t("dashboard.greeting") }},
            <strong class="u-text-gradient">{{ userName }}</strong>
          </p>
          <p class="dash__hero-sub">{{ dashboardSubtitle }}</p>
        </div>
      
    <!--
      Обзор интерфейса при первом входе.

      Показывается один раз: факт прохождения хранится в localStorage
      внутри OnboardingTour. Кнопка «показать снова» нужна, потому что
      человек, пропустивший тур случайно, иначе не найдёт его больше
      никогда.
    -->
    <OnboardingTour :steps="tourSteps" tour-key="trainee-dashboard" />

    <v-btn
      v-if="canRestartTour"
      size="small"
      variant="text"
      class="mt-2"
      prepend-icon="mdi-help-circle-outline"
      data-test="tour-restart"
      @click="restartTour"
    >
      {{ $t("onboarding.restart") }}
    </v-btn>
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

    <!--
      Ключевые показатели на общем StatCard. Раньше KPI рисовались
      вручную в каждом дашборде, и оформление расходилось с
      дизайн-системой: цифра не выделялась, тренда не было вовсе.
    -->
    <AppSkeleton v-if="loading" variant="cards" :count="4" class="mb-4" />

    <div v-else class="dash__stats" data-test="dash-stats">
      <StatCard
        v-for="stat in stats"
        :key="stat.key"
        :label="stat.label"
        :value="stat.value"
        :icon="stat.icon"
        :tone="stat.tone"
      />
    </div>

    <div class="dash__grid">
      <!--
        Продолжить обучение.

        Первый экран после входа, поэтому он идёт первым: конкретный курс
        и кнопка, ведущая в него. Список всего плана ниже по странице —
        он отвечает на другой вопрос («что вообще назначено»).
      -->
      <section v-if="continueItem" class="u-card dash__panel dash__continue u-card--elevated u-hover-lift" data-test="dash-continue">
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

        <!-- Точная доля уроков: раньше процент считался по факту
             открытия материалов и 1 из 20 уроков выглядел как 100%. -->
        <div class="dash__continue-progress">
          <v-progress-linear
            :model-value="continueItem.percent || 0"
            color="primary"
            height="8"
            rounded
          ></v-progress-linear>
          <span class="dash__continue-percent">{{ continueItem.percent || 0 }}%</span>
        </div>

        <!-- Счётчик уроков: процент сам по себе не объясняет, много это
             или мало — «5%» может означать 1 из 20 уроков. -->
        <p class="dash__continue-hint">
          {{ $t("dashboard.lessonsProgress") }}: {{ lessonsDone }} / {{ lessonsTotal }}
        </p>

        <p v-if="resumeHint" class="dash__continue-hint">{{ resumeHint }}</p>

        <v-btn
          class="mt-4"
          color="primary"
          :to="{
            name: 'courses.itemmani',
            query: { idEdit: continueItem.course_id },
          }"
          data-test="dash-continue-go"
        >
          {{ resumeHint ? $t("dashboard.resumeFrom") : $t("dashboard.continueAction") }}
          <v-icon end icon="mdi-arrow-right" size="16" aria-hidden="true"></v-icon>
        </v-btn>
      </section>

      <!-- Прогресс обучения -->
      <section class="u-card dash__panel u-card--glass u-hover-lift">
        <h2 class="u-card__title">{{ $t("dashboard.progress") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.progressHint") }}</p>

        <div class="dash__progress">
          <ProgressBarRing
            :value="progressPercent"
            :size="104"
            tone="accent"
            :caption="$t('dashboard.statProgress')"
            data-test="dash-progress-ring"
          />
        </div>
      </section>

      <!--
        Календарь активности (streak). Данные — из /api/gamification/me,
        тот же источник, что и страница «Геймификация»: отдельного
        запроса не делаем, а Heatmap уже лежал в проекте неиспользованным.
        Панель необязательная: если запрос не прошёл (нет права, движок
        недоступен), дашборд работает дальше — просто без календаря.
      -->
      <section
        v-if="heatmapData.length"
        class="u-card dash__panel u-card--glass u-hover-lift"
        data-test="dash-streak"
      >
        <h2 class="u-card__title">{{ $t("dashboard.streakTitle") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.streakHint") }}</p>

        <p class="dash__streak-value">
          <span class="dash__streak-num">{{ streak }}</span>
          <span class="dash__streak-unit">{{ $t("dashboard.streakUnit") }}</span>
        </p>

        <Heatmap
          :data="heatmapData"
          :levels="4"
          :title="$t('dashboard.streakTitle')"
          :legend-from="$t('dashboard.streakLegendFrom')"
          :legend-to="$t('dashboard.streakLegendTo')"
          data-test="dash-streak-heatmap"
        />
      </section>

      <!-- Подходит к завершению -->
      <section class="u-card dash__panel u-card--glass u-hover-lift">
        <h2 class="u-card__title">{{ $t("dashboard.upcoming") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.upcomingHint") }}</p>

        <!--
          Лента дедлайнов. Раньше строки были списком текста: у курса
          не было даже ссылки, и «сдать к пятнице» превращалось в
          перечитывание дат. Теперь строка ведёт в курс, показывает и
          абсолютную дату, и источник срока — иначе неясно, это
          отдельный дедлайн или конец периода занятий.
        -->
        <ul v-if="upcoming.length" class="dash__list" data-test="dash-deadlines">
          <li v-for="(row, index) in upcoming" :key="row.course_id" class="dash__list-row" data-stagger :data-stagger-index="index">
            <router-link
              class="dash__deadline"
              :to="{ name: 'courses.itemmani', query: { idEdit: row.course_id } }"
            >
              <span class="dash__list-title">{{ row.title }}</span>
              <span class="dash__list-sub">
                <time :datetime="row.due_at">{{ formatDue(row.due_at) }}</time>
                <span class="dash__deadline-source">{{ deadlineSource(row) }}</span>
              </span>
              <span class="u-badge" :class="deadlineBadge(row.days_left)">
                {{ deadlineLabel(row) }}
              </span>
            </router-link>
          </li>
        </ul>
        <p v-else class="dash__muted">{{ $t("dashboard.upcomingEmpty") }}</p>
      </section>

      <!-- Экзамены -->
      <section class="u-card dash__panel u-card--glass u-hover-lift" data-test="my-exams">
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
      <section class="u-card dash__panel u-card--glass u-hover-lift" data-test="my-plan">
        <h2 class="u-card__title">{{ $t("dashboard.myCourses") }}</h2>
        <p class="u-page__subtitle">{{ $t("dashboard.myCoursesHint") }}</p>

        <ul v-if="plan.length" class="dash__list">
          <li v-for="(item, index) in plan.slice(0, 6)" :key="item.id" class="dash__list-row" data-stagger :data-stagger-index="index">
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
import OnboardingTour from "../../components/ui/OnboardingTour.vue";
import PageHeader from "../../components/ui/PageHeader.vue";
import StatCard from "../../components/ui/StatCard.vue";
import ProgressBarRing from "../../components/ui/ProgressBarRing.vue";
import AppSkeleton from "../../components/ui/AppSkeleton.vue";
import Heatmap from "../../components/ui/charts/Heatmap.vue";
import $api from "../../api/httpClient";
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

  components: { PageHeader, OnboardingTour, StatCard, ProgressBarRing, AppSkeleton, Heatmap },

  data() {
    return {
      summary: {},
      plan: [],
      // Календарь активности и streak — из /api/gamification/me.
      // Держим отдельно от summary: это необязательный блок, его
      // отсутствие не должно ломать остальной дашборд.
      gamification: {},
      // Пока идут два параллельных запроса, показываем скелетоны той же
      // формы. Раньше состояния загрузки не было вовсе: карточки появлялись
      // разом и без предупреждения, а на медленной сети страница выглядела
      // пустой минуту.
      loading: true,
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

    /**
     * Подпись точки возврата.
     *
     * Показывается только когда сервер знает, какой урок и какой файл
     * были последними: без этого кнопка обещала бы «продолжить с
     * последнего места», а открывала бы курс с начала.
     */
    resumeHint() {
      if (!this.continueItem?.resume_lesson_id) {
        return "";
      }

      return this.continueItem.resume_file
        ? `${this.$t("dashboard.resumeFrom")}: ${this.continueItem.resume_file}`
        : this.$t("dashboard.resumeFrom");
    },

    /** Уроки всего и уроки с записанным прогрессом — для счётчика. */
    lessonsDone() {
      return this.summary?.progress?.lessons_completed ?? 0;
    },

    lessonsTotal() {
      return this.summary?.progress?.lessons_total ?? 0;
    },

    /** Шаги тура приходят из локализации, а не из кода. */
    tourSteps() {
      return this.$t("onboarding.steps");
    },

    /** Кнопка «показать снова» — только после первого показа. */
    canRestartTour() {
      try {
        return window.localStorage.getItem("app:tour:trainee-dashboard") === "1";
      } catch (error) {
        // Приватный режим: localStorage недоступен, кнопка просто не
        // показывается — это лучше, чем кнопка, которая не работает.
        return false;
      }
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

    /** Подзаголовок героя-блока: текущий статус обучаемого. */
    dashboardSubtitle() {
      const lessons = this.lessonsTotal;
      const done = this.lessonsDone;
      if (!lessons) return this.$t("dashboard.heroSubStart");
      return this.$t("dashboard.heroSubProgress", { done, total: lessons });
    },

    /**
     * Дни активности в формате Heatmap.
     *
     * Бэкенд отдаёт { date, points }, компонент ждёт { date, value } —
     * переименование при отдаче, без пересчёта самих чисел.
     */
    heatmapData() {
      const calendar = this.gamification?.calendar;

      if (!Array.isArray(calendar)) return [];

      return calendar
        .filter((day) => day && day.date)
        .map((day) => ({ date: day.date, value: Number(day.points) || 0 }));
    },

    /** Текущий streak в днях — подпись крупным числом над сеткой. */
    streak() {
      return Number(this.gamification?.streak) || 0;
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
    restartTour() {
      try {
        window.localStorage.removeItem("app:tour:trainee-dashboard");
      } catch (error) {
        // см. canRestartTour
      }

      // Ключ сброшен — ту�� заново откроется при следующей монтаже,
      // а здесь просто перезагружаем шаги.
      window.location.reload();
    },

    async load() {
      try {
        // Сводка и список назначений идут параллельно: дашборд не должен
        // ждать второй запрос, а учебный план нужен для блока «Мои курсы».
        const [dashRes, planRes] = await Promise.all([
          learningApi.fetchMyDashboard(),
          learningApi.fetchMyLearning(),
        ]);

        this.summary = learningApi.dashboard(dashRes);
        /*
         * Блок ниже называется «Мои курсы», а /api/my/learning отдаёт
         * строки плана: группа может быть записана на конкретные модули
         * курса (у каждой строки свой parent_id), и один курс приходил
         * двумя строками с одинаковым названием. На странице учебного
         * плана это верно — там видно, на какие именно модули запись,
         * — а здесь список выглядел как сбой данных.
         */
        this.plan = learningApi.planItems(planRes).filter(
          (item, index, all) => all.findIndex(c => c.course_id === item.course_id) === index
        );
      } catch (e) {
        // Пустые данные — валидное состояние (нет группы, нет курсов).
        // Сообщаем об ошибке только если пришёл отказ сервера.
        if (e?.response?.status && e.response.status >= 400) {
          this.notify(this.$t("dashboard.loadError"), "error");
        }
      } finally {
        this.loading = false;
      }

      // Календарь активности грузим ОТДЕЛЬНО и не блокируем им дашборд:
      // это дополнительный блок, и его отсутствие (нет права, отказ
      // сервера) не должно превращать рабочий экран в ошибку.
      try {
        const response = await $api.get("/api/gamification/me");
        this.gamification = response?.data?.data ?? {};
      } catch {
        this.gamification = {};
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

    /** Дата дедлайна в локальном формате, без «Invalid Date». */
    formatDue(value) {
      if (!value) return "";

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;

      return new Intl.DateTimeFormat(this.$i18n?.locale || "ru-RU", {
        day: "2-digit",
        month: "short",
      }).format(date);
    },

    /**
     * Откуда взят срок: отдельный дедлайн или конец периода занятий.
     * Разница существенна: период может длиться месяц, а сдать нужно
     * к конкретной дате.
     */
    deadlineSource(row) {
      return row.source === "deadline"
        ? this.$t("dashboard.deadlineSourceDeadline")
        : this.$t("dashboard.deadlineSourceStudyTo");
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
.dash__hero {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  margin: var(--sp-2) 0 var(--sp-4);
}

.dash__hero-greeting {
  margin: 0;
  font-size: 1.125rem;
  color: var(--c-text-secondary);
  line-height: var(--lh-normal);
}

.dash__hero-sub {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--c-text-muted);
  max-width: 60ch;
  line-height: var(--lh-normal);
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

.dash__streak-value {
  display: flex;
  align-items: baseline;
  gap: var(--sp-2);
  margin: var(--sp-3) 0 var(--sp-2);
}

.dash__streak-num {
  font-size: var(--fs-3xl);
  font-weight: var(--fw-bold);
  line-height: 1;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
  color: var(--c-text);
}

.dash__streak-unit {
  font-size: var(--fs-sm);
  color: var(--c-text-muted);
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

.dash__continue-progress {
  align-items: center;
  display: flex;
  gap: var(--sp-2);
  margin-top: var(--sp-3);
}

.dash__continue-percent {
  color: var(--c-text-muted);
  flex: none;
  font-size: 0.8125rem;
  min-width: 3ch;
  text-align: right;
}

.dash__continue-hint {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
  margin-top: var(--sp-2);
}

.dash__list-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-2) 0;
  border-bottom: 1px solid var(--c-border);
}

/* Строка дедлайна целиком кликабельна: курс открывается из любого
   места строки, а не только из названия. */
.dash__deadline {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex: 1 1 auto;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.dash__deadline:hover .dash__list-title {
  color: var(--c-primary);
  text-decoration: underline;
}

.dash__deadline:focus-visible {
  outline: 2px solid var(--c-accent);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

.dash__deadline .dash__list-sub {
  display: flex;
  align-items: baseline;
  gap: var(--sp-2);
  white-space: nowrap;
}

.dash__deadline-source {
  font-size: var(--fs-2xs);
  color: var(--c-text-muted);
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

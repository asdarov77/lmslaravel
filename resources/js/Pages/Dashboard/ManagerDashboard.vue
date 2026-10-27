<template>
  <div class="u-page">
    <PageHeader :title="$t('manager.title')" :subtitle="$t('manager.subtitle')">
      <template #head>
        <!-- Герой-блок приветствия для администратора/инструктора. -->
        <div class="dash__hero">
          <p class="dash__hero-greeting">{{ $t("manager.greeting") }},
            <strong class="u-text-gradient">{{ userName }}</strong>
          </p>
          <p class="dash__hero-sub">{{ dashboardSubtitle }}</p>
        </div>
      </template>
      <template #actions>
        <v-btn
          v-for="action in quickActions"
          :key="action.to"
          color="primary"
          variant="flat"
          size="small"
          :to="action.to"
        >
          <v-icon start :icon="action.icon" size="18" aria-hidden="true"></v-icon>
          {{ $t(action.labelKey) }}
        </v-btn>
      </template>
    </PageHeader>

    <!--
      Пока считаются показатели, показываем СКЕЛЕТОНЫ той же формы.
      Раньше loading в данные попадал, но в шаблоне не использовался:
      страница секунду была пустой, а потом разом появлялась — выглядело
      как «ничего не грузится». Скелетон повторяет раскладку, поэтому
      страница не прыгает при появлении данных.
    -->
    <AppSkeleton v-if="loading" variant="cards" :count="4" class="mb-4" />

    <template v-else>
      <!-- Сводные показатели -->
      <div v-if="stats.length" class="dash__stats" data-test="dash-stats">
        <StatCard
          v-for="stat in stats"
          :key="stat.key"
          :label="$t(stat.label_key)"
          :value="stat.value"
          :suffix="stat.suffix"
          :icon="stat.icon"
          :trend="stat.trend"
          :tone="stat.tone"
        />
      </div>

      <MetricCharts
        v-if="trends.attempts_by_month.length || trends.groups.length"
        class="mb-4"
        :months="trends.attempts_by_month"
        :groups="trends.groups"
      />

      <div class="dash__grid">

      <!--
          Требует внимания. Суммы сами по себе бесполезны: «групп: 10»
          ничего не подсказывает. Список говорит, что делать, и ведёт
          прямо к записи.
      -->
<section class="u-card dash__panel u-card--glass u-hover-lift">
        <h2 class="u-card__title">{{ $t('manager.attentionTitle') }}</h2>
        <p class="u-page__subtitle">{{ $t('manager.attentionHint') }}</p>

        <ul v-if="attention.length" class="dash__list">
          <li v-for="(row, index) in attention" :key="`${row.key}-${index}`" class="dash__list-row" data-stagger :data-stagger-index="index">
            <div>
              <span class="dash__list-title">{{ row.text || $t(row.hint_key) }}</span>
              <span class="dash__list-sub">{{ $t(row.hint_key) }}</span>
            </div>
            <v-btn
              size="x-small"
              variant="text"
              color="primary"
              :to="row.to"
              :aria-label="`${$t('manager.open')}: ${row.text || $t(row.hint_key)}`"
            >
              {{ $t("manager.open") }}
            </v-btn>
          </li>
        </ul>

        <div v-else class="dash__empty">
          <v-icon icon="mdi-check-circle-outline" size="28" color="success" aria-hidden="true"></v-icon>
          <p>{{ $t('manager.attentionEmpty') }}</p>
        </div>
      </section>

      <!-- Последние попытки экзаменов -->
      <section class="u-card dash__panel u-card--glass u-hover-lift">
        <h2 class="u-card__title">{{ $t('manager.recent') }}</h2>
        <p class="u-page__subtitle">{{ $t('manager.recentHint') }}</p>

        <ul v-if="recent.length" class="dash__list">
          <li v-for="(row, index) in recent" :key="row.id" class="dash__list-row" data-stagger :data-stagger-index="index">
            <div>
              <span class="dash__list-title">{{ row.exam || $t('manager.examUnknown') }}</span>
              <span class="dash__list-sub">{{ row.user }}</span>
            </div>
            <span class="u-badge" :class="row.passed ? 'u-badge--success' : 'u-badge--danger'">
              {{ row.correct_count }}/{{ row.total_count }}
            </span>
          </li>
        </ul>

        <p v-else class="dash__muted">{{ $t('manager.recentEmpty') }}</p>
      </section>

      <!-- Топ курсов: где зачисления и активность попыток -->
      <section v-if="topCourses.length" class="u-card dash__panel u-card--glass u-hover-lift">
        <h2 class="u-card__title">{{ $t('manager.topCourses') }}</h2>
        <p class="u-page__subtitle">{{ $t('manager.topCoursesHint') }}</p>

        <ul class="dash__list">
          <li v-for="(row, index) in topCourses" :key="row.id" class="dash__list-row" data-stagger :data-stagger-index="index">
            <div>
              <span class="dash__list-title">{{ row.title }}</span>
              <span class="dash__list-sub">{{ $t('manager.topCoursesStudents', { n: row.students }) }}</span>
            </div>
            <span class="u-badge">{{ row.attempts }}</span>
          </li>
        </ul>
      </section>

      <!-- Работы на проверку: сданные без оценки -->
      <section v-if="reviewQueue.length" class="u-card dash__panel u-card--glass u-hover-lift">
        <h2 class="u-card__title">{{ $t('manager.reviewQueue') }}</h2>
        <p class="u-page__subtitle">{{ $t('manager.reviewQueueHint') }}</p>

        <ul class="dash__list">
          <li v-for="row in reviewQueue" :key="row.id" class="dash__list-row">
            <div>
              <span class="dash__list-title">{{ row.assignment }}</span>
              <span class="dash__list-sub">{{ row.student }}</span>
            </div>
            <v-btn size="x-small" variant="text" color="primary" to="/assignments">
              {{ $t('manager.open') }}
            </v-btn>
          </li>
        </ul>
      </section>
      </div>
    </template>

  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'
import MetricCharts from '../../components/ui/MetricCharts.vue'
import StatCard from '../../components/ui/StatCard.vue'
import AppSkeleton from '../../components/ui/AppSkeleton.vue'
import { mapGetters, mapState } from 'vuex'
import { toast } from '../../composables/useToast'
import { fetchDashboardSummary } from '../../api/dashboard.api'
import { unwrapResponse } from '../../api/envelope'

/**
 * Личный кабинет администратора и инструктора.
 *
 * Отличие от кабинета обучаемого — не оформление, а содержание: здесь
 * «что происходит в системе», там «как моё обучение». Показатели и
 * список требующего внимания считает сервер: клиент не должен решать,
 * что пользователю можно видеть.
 */
export default {
  name: 'ManagerDashboard',

  components: { PageHeader, MetricCharts, StatCard, AppSkeleton },

  data: () => ({
    role: null,
    stats: [],
    attention: [],
    recent: [],
    topCourses: [],
    reviewQueue: [],
    // Ряды для графиков: приходят готовыми, считаются на сервере.
    trends: { attempts_by_month: [], groups: [] },
    loading: true,
  }),

  computed: {
    ...mapState('Auth', ['user']),
    ...mapGetters('Auth', ['can']),

    userName() {
      return this.user?.fio || this.user?.name || ''
    },

    /**
     * Быстрые действия показываются только по правам: инструктору
     * без groups.manage кнопка «Создать группу» вела бы в 403.
     */
    quickActions() {
      const actions = []

      if (this.can('users.create')) {
        actions.push({ icon: 'mdi-account-plus-outline', labelKey: 'manager.actions.addUser', to: '/reg' })
      }
      if (this.can('groups.manage')) {
        actions.push({ icon: 'mdi-account-group-outline', labelKey: 'manager.actions.addGroup', to: '/groups/add' })
      }
      if (this.can('courses.manage')) {
        actions.push({ icon: 'mdi-book-plus-outline', labelKey: 'manager.actions.addCourse', to: '/course' })
      }
      if (this.can('exams.manage')) {
        actions.push({ icon: 'mdi-calendar-outline', labelKey: 'manager.actions.calendar', to: '/calendar' })
      }

      return actions
    },

    /** Подзаголовок героя-блока: контекст роли. */
    dashboardSubtitle() {
      return this.role
        ? this.$t('manager.heroSubRole', { role: this.role })
        : this.$t('manager.heroSubDefault');
    },
  },

  async created() {
    try {
      const response = await fetchDashboardSummary()
      const data = unwrapResponse(response) || {}

      this.role = data.role ?? null
      this.stats = Array.isArray(data.stats) ? data.stats : []
      this.attention = Array.isArray(data.attention) ? data.attention : []
      this.recent = Array.isArray(data.recent) ? data.recent : []
      this.topCourses = Array.isArray(data.top_courses) ? data.top_courses : []
      this.reviewQueue = Array.isArray(data.review_queue) ? data.review_queue : []
      this.trends = {
        attempts_by_month: Array.isArray(data.trends?.attempts_by_month) ? data.trends.attempts_by_month : [],
        groups: Array.isArray(data.trends?.groups) ? data.trends.groups : [],
      }
    } catch (error) {
      // Уведомление ушло в общий стек: оно переживает переход на
      // другую страницу, а раньше умирало вместе с компонентом.
      toast.error(this.messageOf(error) || this.$t('manager.failed'))
    } finally {
      this.loading = false
    }
  },

  methods: {
    messageOf(error) {
      const data = error?.response?.data
      return data?.error?.message || data?.message || ''
    },
  },
}
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

.dash__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-6) 0;
  color: var(--c-text-secondary);
  text-align: center;
}
</style>

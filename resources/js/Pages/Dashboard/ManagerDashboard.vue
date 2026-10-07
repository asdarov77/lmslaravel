<template>
  <div class="u-page">
    <PageHeader :title="$t('manager.title')" :subtitle="$t('manager.subtitle')">
      <template #head>
        <p class="dash__greeting">
          {{ $t("manager.greeting") }},
          <strong>{{ userName }}</strong>
        </p>
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

    <!-- Сводные показатели -->
    <div v-if="stats.length" class="dash__stats">
      <div v-for="stat in stats" :key="stat.key" class="u-card dash__stat">
        <span class="dash__stat-icon" :class="`dash__stat-icon--${stat.tone}`">
          <v-icon :icon="stat.icon" size="20" aria-hidden="true"></v-icon>
        </span>
        <div>
          <span class="dash__stat-value">{{ stat.value }}</span>
          <span class="dash__stat-label">{{ $t(stat.label_key) }}</span>
        </div>
      </div>
    </div>

    <div class="dash__grid">
      <!--
          Требует внимания. Суммы сами по себе бесполезны: «групп: 10»
          ничего не подсказывает. Список говорит, что делать, и ведёт
          прямо к записи.
      -->
      <section class="u-card dash__panel">
        <h2 class="u-card__title">{{ $t('manager.attentionTitle') }}</h2>
        <p class="u-page__subtitle">{{ $t('manager.attentionHint') }}</p>

        <ul v-if="attention.length" class="dash__list">
          <li v-for="(row, index) in attention" :key="`${row.key}-${index}`" class="dash__list-row">
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
      <section class="u-card dash__panel">
        <h2 class="u-card__title">{{ $t('manager.recent') }}</h2>
        <p class="u-page__subtitle">{{ $t('manager.recentHint') }}</p>

        <ul v-if="recent.length" class="dash__list">
          <li v-for="row in recent" :key="row.id" class="dash__list-row">
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
    </div>

    <AppToast v-model="alert" :type="alertType" :text="alertText"></AppToast>
  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'
import AppToast from '../../components/ui/AppToast.vue'
import { mapGetters, mapState } from 'vuex'
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

  components: { PageHeader, AppToast },

  data: () => ({
    role: null,
    stats: [],
    attention: [],
    recent: [],
    loading: true,
    alert: false,
    alertType: 'error',
    alertText: '',
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
  },

  async created() {
    try {
      const response = await fetchDashboardSummary()
      const data = unwrapResponse(response) || {}

      this.role = data.role ?? null
      this.stats = Array.isArray(data.stats) ? data.stats : []
      this.attention = Array.isArray(data.attention) ? data.attention : []
      this.recent = Array.isArray(data.recent) ? data.recent : []
    } catch (error) {
      this.alertText = this.messageOf(error) || this.$t('manager.failed')
      this.alertType = 'error'
      this.alert = true
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

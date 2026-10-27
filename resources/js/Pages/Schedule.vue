<template>
  <div>
    <PageHeader :title="$t('schedule.title')" :subtitle="$t('schedule.hint')">
      <template #actions>
        <v-chip-group v-model="kind" mandatory density="compact" data-test="schedule-kind">
          <v-chip value="all" size="small">{{ $t('schedule.all') }}</v-chip>
          <v-chip value="period" size="small">{{ $t('schedule.periods') }}</v-chip>
          <v-chip value="deadline" size="small">{{ $t('schedule.deadlines') }}</v-chip>
        </v-chip-group>
      </template>
    </PageHeader>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="list-item-avatar-two-line@4" />
    </div>

    <EmptyState
      v-else-if="!filtered.length"
      icon="mdi-calendar-blank-outline"
      :title="$t('schedule.emptyTitle')"
      :text="$t('schedule.emptyText')"
    />

    <div v-else class="schedule">
      <!--
        Ближайшее событие вынесено отдельно: ответ на вопрос «что мне
        сегодня делать» не должен требовать прокрутки до середины
        списка месяца.
      -->
      <section v-if="next" class="u-card u-card__body schedule-next" data-test="schedule-next">
        <span class="u-badge" :class="statusBadge(next.status)">
          {{ $t(`plan.status.${next.status || 'planned'}`) }}
        </span>
        <h2 class="u-card__title mt-2">{{ next.title }}</h2>
        <p v-if="next.extendedProps?.course_title" class="u-muted">
          {{ next.extendedProps.course_title }}
        </p>
        <p class="schedule-next__date">{{ periodLabel(next) }}</p>
        <v-btn
          v-if="next.extendedProps?.course_id"
          color="primary"
          variant="text"
          class="mt-2 px-0"
          :to="{ name: 'courses.itemmani', query: { idEdit: next.extendedProps.course_id } }"
        >
          {{ $t('schedule.openCourse') }}
          <v-icon end icon="mdi-arrow-right" size="16" aria-hidden="true" />
        </v-btn>
      </section>

      <section class="u-card u-card__body">
        <h2 class="u-card__title">{{ $t('schedule.listTitle') }}</h2>

        <ul class="schedule-list" data-test="schedule-list">
          <li v-for="event in filtered" :key="event.id" class="schedule-item">
            <span class="schedule-item__date">
              {{ formatDate(event.start) }}
              <template v-if="event.end && event.end !== event.start">
                — {{ formatDate(event.end) }}
              </template>
            </span>

            <span class="schedule-item__title">{{ event.title }}</span>

            <span class="u-badge" :class="statusBadge(event.extendedProps?.status)">
              {{ $t(`plan.status.${event.extendedProps?.status || 'planned'}`) }}
            </span>

            <span
              v-if="event.extendedProps?.deadline"
              class="schedule-item__deadline"
              :title="$t('schedule.deadlineTitle')"
            >
              {{ $t('schedule.deadline') }}: {{ formatDate(event.extendedProps.deadline) }}
            </span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<script>
import $api from '../api/httpClient'
import { unwrapResponse } from '../api/envelope'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'

/**
 * Расписание обучаемого.
 *
 * Отдельная страница от календаря методиста: у методиста он с фильтрами
 * по всем группам и курсам, а у обучаемого своя группа, и показывать
 * ему эти фильтры незачем — контроллер всё равно игнорирует чужую
 * группу и молча отдаёт свою.
 *
 * Список намеренно не календарная сетка: вопрос «когда у меня занятия»
 * про периоды и сроки, а не про конкретный день.
 */
export default {
  name: 'MySchedule',
  components: { EmptyState, PageHeader },
  data() {
    return {
      events: [],
      // Отфильтрованный список: в шаблоне используется он, а events —
      // источник, из которого он берётся. Иначе фильтр пришлось бы
      // пересчитывать в шаблоне на каждом рендере.
      filtered: [],
      kind: 'all',
      loading: true,
    }
  },
  computed: {
    /**
     * Ближайшее событие: то, что ещё не закончилось и начинается раньше
     * всех остальных. Просроченные периоды в подсказку не попадают —
     * они остаются в списке, но советовать «открыть» их незачем.
     */
    next() {
      const today = new Date().toISOString().slice(0, 10);

      return (
        this.filtered.find((event) => {
          const end = event.end || event.start

          return !event.start || event.start <= today ? end >= today : true
        }) || null
      )
    },
  },
  async mounted() {
    await this.load()
  },
  watch: {
    // Фильтр вида событий меняется на клиенте: это переключение между
    // двумя уже загруженными списками, а не новый запрос.
    kind() {
      this.applyFilter()
    },
  },
  methods: {
    async load() {
      this.loading = true

      try {
        const response = await $api.get('/api/calendar')
        const payload = unwrapResponse(response) || {}

        this.events = payload.events || []
        this.applyFilter()
      } catch (error) {
        this.events = []
      } finally {
        this.loading = false
      }
    },

    applyFilter() {
      // Источник один: перезагрузка при смене фильтра не нужна и
      // дёргала бы API на каждый клик.
      this.filtered = this.kind === 'all'
        ? this.events
        : this.events.filter((event) => event.extendedProps?.kind === this.kind)
    },

    formatDate(value) {
      if (!value) return ''

      return new Date(value).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
    },

    periodLabel(event) {
      const from = this.formatDate(event.start)
      const to = event.end ? this.formatDate(event.end) : ''

      return to && to !== from ? `${from} — ${to}` : from
    },

    statusBadge(status) {
      if (status === 'active') return 'u-badge--success'
      if (status === 'completed') return ''

      return 'u-badge--warning'
    },
  },
}
</script>

<style scoped>
.schedule {
  display: grid;
  gap: var(--sp-4);
}

.schedule-next__date {
  color: var(--text-muted);
  margin-top: var(--sp-1);
}

.schedule-list {
  list-style: none;
  margin: var(--sp-3) 0 0;
  padding: 0;
}

.schedule-item {
  align-items: center;
  border-top: 1px solid var(--c-border);
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
  padding: var(--sp-3) 0;
}

.schedule-item:first-child {
  border-top: none;
}

.schedule-item__date {
  color: var(--text-muted);
  font-variant-numeric: tabular-nums;
  min-width: 180px;
}

.schedule-item__title {
  flex: 1;
  font-weight: 500;
}

.schedule-item__deadline {
  color: var(--c-danger);
  font-size: 0.8125rem;
}
</style>
<template>
  <div class="gamification">
    <PageHeader
      :title="$t('gamification.pageTitle')"
      :subtitle="$t('gamification.pageHint')"
      icon="mdi-chart-line"
    />

    <v-alert v-if="error" type="error" dense text class="mb-4" data-test="gamification-error">
      {{ error }}
    </v-alert>

    <v-row>
      <!-- Итог: XP, уровень, streak. -->
      <v-col cols="12" md="8">
        <FormCard>
          <div class="gamification__top">
            <div class="gamification__stat">
              <span class="gamification__num">{{ data.xp ?? 0 }}</span>
              <span class="gamification__label">{{ $t('gamification.xp') }}</span>
            </div>

            <div class="gamification__stat">
              <span class="gamification__num">{{ data.level?.level ?? 1 }}</span>
              <span class="gamification__label">{{ $t('gamification.level') }}</span>
            </div>

            <div class="gamification__stat">
              <span class="gamification__num">{{ data.streak ?? 0 }}</span>
              <span class="gamification__label">{{ $t('gamification.streakDays') }}</span>
            </div>

            <div v-if="data.group" class="gamification__stat gamification__stat--wide">
              <span class="gamification__label">{{ $t('gamification.group') }}</span>
              <span class="gamification__group">{{ data.group }}</span>
            </div>
          </div>

          <!--
            До следующего уровня. Полоса без процентов и конфетти:
            в рабочем LMS цифра важнее анимации.
          -->
          <div class="gamification__level">
            <div class="gamification__level-row">
              <span>{{ $t('gamification.toNextLevel', { need: data.level?.need ?? 0 }) }}</span>
              <span class="gamification__muted">
                {{ data.level?.current ?? 0 }} / {{ data.level?.next ?? 0 }}
              </span>
            </div>
            <v-progress-linear
              :model-value="levelPercent"
              color="primary"
              height="6"
              rounded
            />
          </div>

          <!--
            Календарь активности. Не heatmap с цветовой шкалой, а
            полоса дней: у месяца 30 ячеек, и разница оттенков ничего
            не объясняет человеку, который не считал баллы.
          -->
          <div class="gamification__calendar">
            <div
              v-for="day in data.calendar ?? []"
              :key="day.date"
              class="gamification__day"
              :class="{ 'gamification__day--on': day.points > 0 }"
              :title="day.date + (day.points ? ': +' + day.points : '')"
            />
          </div>
          <p class="gamification__muted gamification__calendar-hint">
            {{ $t('gamification.calendarHint') }}
          </p>
        </FormCard>
      </v-col>

      <!-- Лидерборд группы. -->
      <v-col cols="12" md="4">
        <FormCard :title="$t('gamification.leaderboardTitle')">
          <template v-if="(data.leaderboard ?? []).length">
            <ol class="gamification__board">
              <li
                v-for="row in data.leaderboard"
                :key="row.user_id"
                :class="{ 'gamification__board-row--me': row.is_me }"
                class="gamification__board-row"
              >
                <span class="gamification__rank">{{ row.rank }}</span>
                <span class="gamification__name">{{ row.fio }}</span>
                <span class="gamification__board-xp">{{ row.xp }}</span>
              </li>
            </ol>
          </template>
          <EmptyState
            v-else
            :title="$t('gamification.emptyBoard')"
            :text="$t('gamification.emptyBoardHint')"
          />
        </FormCard>
      </v-col>
    </v-row>

    <!-- Достижения. -->
    <FormCard class="mt-4" :title="$t('gamification.badgesTitle')">
      <p class="gamification__muted mb-3">{{ $t('gamification.badgesHint') }}</p>

      <div class="gamification__badges">
        <div
          v-for="badge in data.badges ?? []"
          :key="badge.key"
          class="gamification__badge"
          :class="{ 'gamification__badge--on': badge.granted }"
          :data-test="badge.granted ? 'badge-granted' : 'badge-locked'"
        >
          <v-icon :color="badge.granted ? 'primary' : 'grey'" size="28">
            {{ badge.icon }}
          </v-icon>

          <span class="gamification__badge-title">{{ badge.title }}</span>

          <!-- Подсказка показывает и условие, и сколько уже закрыто:
              «3 из 25» полезнее, чем просто «25 уроков». -->
          <span class="gamification__badge-hint">
            <template v-if="badge.granted">{{ badge.hint }}</template>
            <template v-else-if="badge.progress">
              {{ $t('gamification.badgeProgress', {
                current: badge.progress.current,
                total: badge.progress.total,
              }) }}
            </template>
            <template v-else>{{ badge.hint }}</template>
          </span>

          <v-progress-linear
            v-if="!badge.granted && badge.progress"
            :model-value="badge.progress.percent"
            height="3"
            color="primary"
            class="gamification__badge-bar"
          />
        </div>
      </div>
    </FormCard>
  </div>
</template>

<script>
import $api from "../api/httpClient";
import PageHeader from "../components/ui/PageHeader.vue";
import FormCard from "../components/ui/FormCard.vue";
import EmptyState from "../components/ui/EmptyState.vue";

export default {
  name: "GamificationPage",

  components: { PageHeader, FormCard, EmptyState },

  data() {
    return {
      error: null,
      data: {},
    };
  },

  computed: {
    /**
     * Процент внутри текущего уровня.
     *
     * Считается от «сколько набрано» и «сколько до следующего», а не
     * от next: уровень 1 — это 0…100 XP, а не 0…400, иначе полоса
     * ползла бы едва заметно и читалась как сломанная.
     */
    levelPercent() {
      const level = this.data.level;
      if (!level || !level.next || !level.current) return 0;

      const span = level.next - this.data.level.current;
      if (span <= 0) return 100;

      return Math.max(0, Math.min(100, Math.round((level.current / span) * 100)));
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.error = null;

      try {
        const response = await $api.get("/api/gamification/me");
        this.data = response?.data?.data ?? {};
      } catch (e) {
        this.error = e?.response?.data?.message || this.$t("gamification.loadFailed");
      }
    },
  },
};
</script>

<style scoped>
.gamification__top {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-6);
}

.gamification__stat {
  display: flex;
  flex-direction: column;
  min-width: 72px;
}

.gamification__stat--wide {
  margin-left: auto;
  text-align: right;
}

.gamification__num {
  font-size: 1.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.gamification__label {
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.8125rem;
}

.gamification__group {
  font-size: 1rem;
  font-weight: 500;
}

.gamification__muted {
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.8125rem;
}

.gamification__level {
  margin-top: var(--sp-5);
}

.gamification__level-row {
  display: flex;
  font-size: 0.8125rem;
  justify-content: space-between;
  margin-bottom: 6px;
}

/*
 * Календарь: 35 ячеек в ряд. Обёртка переносит их на узких экранах,
 * поэтому месяц не выходит за границы карточки.
 */
.gamification__calendar {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: var(--sp-5);
}

.gamification__day {
  background: rgba(0, 0, 0, 0.08);
  border-radius: 3px;
  height: 14px;
  width: 14px;
}

.gamification__day--on {
  background: rgb(var(--v-theme-primary));
}

.gamification__calendar-hint {
  margin-top: var(--sp-2);
}

.gamification__board {
  list-style: none;
  margin: 0;
  padding: 0;
}

.gamification__board-row {
  align-items: center;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  display: flex;
  gap: var(--sp-3);
  padding: 8px 4px;
}

.gamification__board-row--me {
  background: rgba(var(--v-theme-primary), 0.08);
  border-radius: 6px;
}

.gamification__rank {
  color: rgba(0, 0, 0, 0.5);
  font-variant-numeric: tabular-nums;
  min-width: 20px;
}

.gamification__name {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gamification__board-xp {
  font-variant-numeric: tabular-nums;
  font-weight: 500;
}

.gamification__badges {
  display: grid;
  gap: var(--sp-4);
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
}

.gamification__badge {
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: var(--sp-3);
}

.gamification__badge--on {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.gamification__badge-title {
  font-size: 0.9375rem;
  font-weight: 500;
}

.gamification__badge-hint {
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.75rem;
}

.gamification__badge-bar {
  margin-top: 4px;
}
</style>
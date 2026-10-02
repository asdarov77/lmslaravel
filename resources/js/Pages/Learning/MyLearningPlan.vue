<template>
  <div class="u-page">
    <PageHeader :title="$t('plan.title')" :subtitle="$t('plan.subtitle')">
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

    <!-- Сводные счётчики: сколько всего, сколько в работе -->
    <div class="plan__stats">
      <div v-for="stat in stats" :key="stat.key" class="u-card plan__stat">
        <span class="plan__stat-value">{{ stat.value }}</span>
        <span class="plan__stat-label">{{ stat.label }}</span>
      </div>
    </div>

    <!-- Фильтр по состоянию: в LMS это то же, что вкладки «активные/все» -->
    <div class="plan__filters">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="cats-chip"
        :class="{ 'cats-chip--on': status === tab.key }"
        :aria-pressed="status === tab.key"
        @click="status = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <EmptyState
      v-if="!loading && !visibleItems.length"
      :icon="'mdi-calendar-remove-outline'"
      :title="anyItems ? $t('plan.emptyFiltered') : $t('plan.emptyTitle')"
      :text="anyItems ? $t('plan.emptyFilteredText') : $t('plan.emptyText')"
    ></EmptyState>

    <div v-else class="plan__list">
      <article v-for="item in visibleItems" :key="item.id" class="u-card plan__item">
        <div class="plan__item-main">
          <div class="d-flex align-center plan__item-head">
            <h2 class="plan__item-title">{{ item.course_title }}</h2>
            <span class="u-badge" :class="statusBadge(item.status)">
              {{ $t(`plan.status.${item.status}`) }}
            </span>
          </div>

          <dl class="plan__facts">
            <div v-if="item.module_title" class="plan__fact">
              <dt>{{ $t("plan.module") }}</dt>
              <dd>{{ item.module_title }}</dd>
            </div>
            <div v-if="item.lesson_type" class="plan__fact">
              <dt>{{ $t("plan.lessonType") }}</dt>
              <dd>{{ item.lesson_type }}</dd>
            </div>
            <div class="plan__fact">
              <dt>{{ $t("plan.period") }}</dt>
              <dd>{{ period(item) }}</dd>
            </div>
            <div v-if="item.deadline" class="plan__fact">
              <dt>{{ $t("plan.deadline") }}</dt>
              <dd>
                <span class="u-badge" :class="deadlineBadge(item.deadline)">
                  {{ formatDate(item.deadline) }}
                </span>
              </dd>
            </div>
            <div class="plan__fact">
              <dt>{{ $t("plan.specialties") }}</dt>
              <dd>
                <span
                  v-for="cat in item.categories"
                  :key="cat.id"
                  class="u-badge u-badge--muted plan__cat"
                >
                  {{ cat.title }}
                </span>
                <span v-if="!item.categories.length">—</span>
              </dd>
            </div>
          </dl>
        </div>

        <div class="plan__item-actions">
          <!-- Материалы — в новом окне, см. комментарий в EventCalendar.vue -->
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            :to="{
              name: 'courses.itemmani',
              query: { idEdit: item.course_id },
            }"
            target="_blank"
            rel="noopener"
            :title="$t('courses.list.openManifestHint')"
          >
            <v-icon start icon="mdi-open-in-new" size="18" aria-hidden="true"></v-icon>
            {{ $t("plan.openMaterials") }}
          </v-btn>
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
import learningApi from "../../api/learning.api";

/**
 * Учебный план обучаемого.
 *
 * Зачем страница: пункт меню «Учебный план» вёл на админскую форму записи
 * групп на курсы (/group/learning, право users.courses) и отдавал
 * обучаемому 403 — у него нет ни одного учебного материала в интерфейсе.
 * Теперь это read-only его собственный план: назначения его группы с
 * датами, модулями и состоянием.
 */
export default {
  name: "MyLearningPlan",

  components: { PageHeader, EmptyState, AppToast },

  data() {
    return {
      items: [],
      loading: false,
      status: "all",
      alert: false,
      alertType: "success",
      alertText: "",
    };
  },

  computed: {
    anyItems() {
      return this.items.length > 0;
    },

    visibleItems() {
      if (this.status === "all") return this.items;
      return this.items.filter((i) => i.status === this.status);
    },

    tabs() {
      return [
        { key: "all", label: this.$t("plan.filterAll") },
        { key: "active", label: this.$t("plan.status.active") },
        { key: "planned", label: this.$t("plan.status.planned") },
        { key: "completed", label: this.$t("plan.status.completed") },
      ];
    },

    /**
     * Счётчики видны и на пустом фильтре, поэтому считаем по полному
     * списку, а не по отфильтрованному.
     */
    stats() {
      const count = (status) => this.items.filter((i) => i.status === status).length;
      const courses = new Set(this.items.map((i) => i.course_id)).size;

      return [
        { key: "total", value: courses, label: this.$t("plan.statCourses") },
        { key: "active", value: count("active"), label: this.$t("plan.status.active") },
        { key: "planned", value: count("planned"), label: this.$t("plan.status.planned") },
        { key: "completed", value: count("completed"), label: this.$t("plan.status.completed") },
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
        const response = await learningApi.fetchMyLearning();
        this.items = learningApi.planItems(response);
      } catch (e) {
        // План может быть пустым — это не ошибка. Ошибкой считаем
        // только невозможность получить данные.
        this.items = [];
        this.notify(e?.response?.status === 403 ? this.$t("plan.forbidden") : this.$t("plan.loadError"), "error");
      } finally {
        this.loading = false;
      }
    },

    period(item) {
      const from = item.study_from ? this.formatDate(item.study_from) : "?";
      const to = item.study_to ? this.formatDate(item.study_to) : "?";
      return `${from} — ${to}`;
    },

    /**
     * Даты приходят ISO-строкой. Разбираем вручную, а не через new Date(),
     * чтобы не сдвинуть дату на сутки из-за локальной таймзоны браузера.
     */
    formatDate(value) {
      const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || "");
      return m ? `${m[3]}.${m[2]}.${m[1]}` : value;
    },

    /**
     * Подсветка дедлайна по близости.
     *
     * Считается по локальному дню пользователя: если срок прошёл — это
     * «просрочено», и такой бейдж обязан быть заметным, иначе
     * просроченная работа выглядит как обычная предстоящая.
     */
    deadlineBadge(deadline) {
      const due = new Date(deadline + 'T00:00:00');
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const days = Math.round((due - today) / 86400000);

      if (days < 0) return 'u-badge--danger';
      if (days <= 3) return 'u-badge--warning';
      return 'u-badge--muted';
    },

    statusBadge(status) {
      return status === "completed" ? "u-badge--success"
        : status === "planned" ? "u-badge--muted"
        : "u-badge--warning";
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
.plan__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}

.plan__stat {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  padding: var(--sp-4);
}

.plan__stat-value {
  font-size: 1.75rem;
  font-weight: 600;
  line-height: 1.1;
}

.plan__stat-label {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.plan__filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-bottom: var(--sp-4);
}

.plan__list {
  display: grid;
  gap: var(--sp-3);
}

.plan__item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--sp-4);
  padding: var(--sp-4);
}

.plan__item-head {
  gap: var(--sp-2);
  flex-wrap: wrap;
  margin-bottom: var(--sp-3);
}

.plan__item-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 600;
}

.plan__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--sp-3);
  margin: 0;
}

.plan__fact dt {
  color: var(--c-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: var(--sp-1);
}

.plan__fact dd {
  margin: 0;
  font-size: 0.875rem;
}

.plan__cat {
  margin: 0 var(--sp-1) var(--sp-1) 0;
}

.plan__item-actions {
  flex-shrink: 0;
}

@media (max-width: 720px) {
  .plan__item {
    flex-direction: column;
  }

  .plan__item-actions {
    width: 100%;
  }
}
</style>

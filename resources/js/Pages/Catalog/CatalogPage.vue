<template>
  <div class="u-page">
    <PageHeader
      :title="$t('catalog.title')"
      :subtitle="$t('catalog.subtitle')"
      icon="mdi-bookshelf"
    />

    <!-- Фильтры -->
    <div class="catalog__filters">
      <v-text-field
        v-model="query"
        :label="$t('catalog.search')"
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        density="comfortable"
        hide-details
        clearable
        data-test="catalog-search"
        @update:model-value="scheduleReload"
      />

      <v-select
        v-model="categoryId"
        :items="categoryItems"
        item-value="id"
        item-title="title"
        :label="$t('catalog.category')"
        variant="outlined"
        density="comfortable"
        hide-details
        clearable
        data-test="catalog-category"
        @update:model-value="scheduleReload"
      />

      <v-chip-group v-model="mine" filter mandatory class="catalog__mine" @update:model-value="reload">
        <v-chip value="all">{{ $t("catalog.all") }}</v-chip>
        <v-chip value="mine">{{ $t("catalog.onlyMine") }}</v-chip>
      </v-chip-group>
    </div>

    <p class="u-page__subtitle" data-test="catalog-count">
      {{ $t("catalog.found", { count: items.length, all: meta.all ?? items.length }) }}
    </p>

    <!-- Каталог -->
    <div v-if="items.length" class="catalog__grid">
      <article
        v-for="course in items"
        :key="course.id"
        class="u-card catalog__card"
        :data-test="course.enrolled ? 'catalog-card-enrolled' : 'catalog-card'"
      >
        <header class="catalog__card-head">
          <h2 class="u-card__title">
            <!--
                Ссылка ведёт на материал, а он открыт только записанному
                (CourseAccess). Для незаписанного название остаётся
                текстом: клик по ссылке приводил бы к 403.
            -->
            <router-link v-if="course.enrolled" :to="course.to">{{ course.title }}</router-link>
            <template v-else>{{ course.title }}</template>
          </h2>
          <span class="u-badge" :class="course.enrolled ? 'u-badge--success' : 'u-badge--muted'">
            {{ course.enrolled ? $t("catalog.enrolled") : $t("catalog.notEnrolled") }}
          </span>
        </header>

        <p v-if="course.short_description" class="catalog__desc">
          {{ course.short_description }}
        </p>

        <dl class="catalog__facts">
          <div v-if="course.aircraft">
            <dt>{{ $t("catalog.aircraft") }}</dt>
            <dd>{{ course.aircraft }}</dd>
          </div>
          <div>
            <dt>{{ $t("catalog.topics") }}</dt>
            <dd>{{ course.topics }}</dd>
          </div>
          <div v-if="course.categories.length">
            <dt>{{ $t("catalog.categories") }}</dt>
            <dd>
              <span
                v-for="category in course.categories"
                :key="category.id"
                class="u-badge u-badge--muted catalog__chip"
              >{{ category.title }}</span>
            </dd>
          </div>
        </dl>

        <div class="catalog__actions">
          <v-btn
            v-if="course.enrolled"
            variant="text"
            color="primary"
            size="small"
            :loading="busyId === course.id"
            data-test="catalog-leave"
            @click="toggle(course)"
          >
            {{ $t("catalog.leave") }}
          </v-btn>
          <v-btn
            v-else
            variant="flat"
            color="primary"
            size="small"
            :disabled="!canEnroll"
            :loading="busyId === course.id"
            data-test="catalog-enroll"
            @click="toggle(course)"
          >
            {{ $t("catalog.enroll") }}
          </v-btn>

          <v-btn
            v-if="course.enrolled"
            variant="text"
            size="small"
            :to="course.to"
            data-test="catalog-open"
          >
            {{ $t("catalog.open") }}
            <v-icon end icon="mdi-arrow-right" size="16" aria-hidden="true"></v-icon>
          </v-btn>
        </div>
      </article>
    </div>

    <!-- Пустое состояние различает «нет такого фильтра» и «нет courses» -->
    <EmptyState
      v-else
      :title="loading ? $t('catalog.loading') : $t('catalog.emptyTitle')"
      :text="loading ? '' : $t('catalog.emptyHint')"
      :icon="'mdi-bookshelf'"
    />

    <v-alert v-if="!canView" type="warning" density="compact" class="mt-4" data-test="catalog-noview">
      {{ $t("catalog.noViewHint") }}
    </v-alert>

    <v-alert v-else-if="!canEnroll" type="info" density="compact" class="mt-4" data-test="catalog-noenroll">
      {{ $t("catalog.noEnrollHint") }}
    </v-alert>

    <AppToast v-model="alert" :type="alertType" :text="alertText" />
  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import AppToast from '../../components/ui/AppToast.vue'
import { mapGetters } from 'vuex'
import { fetchCatalog, enrollCourse, unenrollCourse } from '../../api/catalog.api'
import { asArray, unwrapResponse } from '../../api/envelope'

/** Задержка перед запросом: поиск не должен бить по серверу на букву. */
const DEBOUNCE_MS = 300

/**
 * Витрина курсов.
 *
 * Отличие от «Моего обучения» принципиальное: там список того, что уже
 * назначено, здесь — выбор из того, что доступно, с записью в один
 * клик. Самостоятельная запись идёт только в свою группу и только при
 * наличии courses.view; управляющему курсами она не нужна (он и так
 * видит материал), поэтому кнопки у него нет.
 */
export default {
  name: 'CatalogPage',

  components: { PageHeader, EmptyState, AppToast },

  data: () => ({
    items: [],
    categories: [],
    meta: {},
    query: '',
    categoryId: null,
    mine: 'all',
    loading: true,
    busyId: null,
    timer: null,
    alert: false,
    alertType: 'error',
    alertText: '',
  }),

  computed: {
    ...mapGetters('Auth', ['can', 'user']),

    categoryItems() {
      return asArray(this.categories)
    },

    /**
     * Витрина — раздел каталога, то есть courses.view.
     *
     * Маршрут и так требует права, но проверка в компоненте тоже нужна:
     * иначе без права запрос всё равно уходил бы, а пользователь видел
     * либо «курсов нет» (вводя в заблуждение: их нет не у него), либо
     * голый список без объяснения.
     */
    canView() {
      return this.can('courses.view')
    },

    /**
     * Управляющему курсами запись не нужна: материал ему и так доступен,
     * а курс не в его учебном плане.
     */
    canEnroll() {
      if (!this.canView) return false
      return !this.can('courses.manage', 'content.manage')
    },

    /** Без группы записаться некуда. */
    hasGroup() {
      return Boolean(this.user?.group_id)
    },
  },

  created() {
    if (!this.canView) {
      this.loading = false
      return
    }

    this.reload()
  },

  beforeUnmount() {
    if (this.timer) clearTimeout(this.timer)
  },

  methods: {
    async reload() {
      if (!this.canView) {
        this.loading = false
        return
      }

      this.loading = true
      try {
        const response = await fetchCatalog({
          q: (this.query || '').trim() || undefined,
          category_id: this.categoryId || undefined,
          mine: this.mine === 'mine' ? 1 : undefined,
        })
        const data = unwrapResponse(response) || {}

        this.items = asArray(data.items)
        this.categories = asArray(data.categories)
        this.meta = data.meta || {}
      } catch (error) {
        this.showError(error)
      } finally {
        this.loading = false
      }
    },

    scheduleReload() {
      if (this.timer) clearTimeout(this.timer)
      this.timer = setTimeout(() => this.reload(), DEBOUNCE_MS)
    },

    /**
     * Запись или отписка.
     *
     * После успеха список перечитывается целиком: сервер возвращает
     * фактическое состояние, и доверять локальной перестановке флажка
     * нельзя — запись могла уже существовать.
     */
    async toggle(course) {
      if (this.busyId !== null) return

      this.busyId = course.id
      try {
        const response = course.enrolled
          ? await unenrollCourse(course.id)
          : await enrollCourse(course.id)

        const data = unwrapResponse(response) || {}
        const enrolled = data.enrolled ?? !course.enrolled

        this.items = this.items.map((item) =>
          item.id === course.id ? { ...item, enrolled } : item
        )

        this.showMessage(
          enrolled ? this.$t('catalog.enrolled') : this.$t('catalog.left'),
          'success'
        )
      } catch (error) {
        this.showError(error)
      } finally {
        this.busyId = null
      }
    },

    showMessage(text, type = 'success') {
      this.alertText = text
      this.alertType = type
      this.alert = true
    },

    showError(error) {
      const data = error?.response?.data
      const message = data?.error?.message || data?.data?.message || data?.message

      this.showMessage(message || this.$t('catalog.failed'), 'error')
    },
  },
}
</script>

<style scoped>
.catalog__filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
  align-items: center;
  margin-bottom: var(--sp-3);
}

.catalog__filters > :first-child {
  min-width: 260px;
}

.catalog__filters > :nth-child(2) {
  min-width: 220px;
}

.catalog__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: var(--sp-4);
}

.catalog__card {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.catalog__card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--sp-2);
}

.catalog__desc {
  margin: 0;
  color: var(--c-text-secondary);
}

.catalog__facts {
  display: grid;
  gap: var(--sp-2);
  margin: 0;
}

.catalog__facts > div {
  display: flex;
  gap: var(--sp-2);
  font-size: 0.875rem;
}

.catalog__facts dt {
  min-width: 11ch;
  color: var(--c-text-secondary);
}

.catalog__facts dd {
  margin: 0;
}

.catalog__chip {
  margin-right: var(--sp-1);
}

.catalog__actions {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: auto;
}
</style>

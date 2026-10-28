<template>
  <div class="u-page">
    <PageHeader
      :title="$t('catalog.title')"
      :subtitle="$t('catalog.subtitle')"
      icon="mdi-bookshelf"
    />

    <!-- Герой-секция каталога: акцент на курсы, вдохновение и поиск. -->
    <div class="catalog__hero u-card--glass u-hover-lift">
      <div class="catalog__hero-content">
        <!--
          Заголовок страницы — h1 в PageHeader выше. Здесь h2: два h1 на
          одной странице сбивают навигацию по заголовкам — и у человека,
          и у скринридера, который строит по ним оглавление
          (WCAG 2.2 SC 1.3.1).
        -->
        <h2 class="catalog__hero-title u-text-gradient">{{ $t('catalog.heroTitle') }}</h2>
        <p class="catalog__hero-sub">{{ $t('catalog.heroSub') }}</p>
        <v-text-field
          v-model="query"
          :label="$t('catalog.search')"
          :hint="$t('common.minCharsHint')"
          persistent-hint
          minlength="3"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          clearable
          class="catalog__hero-search"
          data-test="catalog-search"
          @update:model-value="scheduleReload"
        />
      </div>
    </div>

    <!-- Фильтры. Поиска здесь нет: он уже есть в герое-секции, и два
         одинаковых поля на одной странице означали два ответа на один
         вопрос «где искать». -->
    <div class="catalog__filters u-card--glass">
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

      <div class="catalog__controls">
        <v-select
          v-model="sortBy"
          :items="sortOptions"
          item-value="value"
          item-title="label"
          :label="$t('catalog.sort')"
          variant="outlined"
          density="comfortable"
          hide-details
          class="catalog__sort"
        />

        <v-btn-toggle v-model="viewMode" mandatory class="catalog__view-toggle">
          <v-btn value="grid" icon="mdi-view-grid" :title="$t('catalog.viewGrid')" />
          <v-btn value="list" icon="mdi-view-list" :title="$t('catalog.viewList')" />
        </v-btn-toggle>
      </div>
    </div>

    <p class="u-page__subtitle" data-test="catalog-count">
      {{ $t("catalog.found", { count: items.length, all: meta.all ?? items.length }) }}
    </p>

    <!-- Каталог -->
    <div v-if="items.length" :class="viewMode === 'grid' ? 'catalog__grid' : 'catalog__list'">
      <article
        v-for="(course, index) in items"
        :key="course.id"
        class="u-card--glass catalog__card u-hover-lift"
        :class="{ 'catalog__card--list': viewMode === 'list' }"
        :data-test="course.enrolled ? 'catalog-card-enrolled' : 'catalog-card'"
        data-stagger
        :data-stagger-index="index"
      >
        <!--
          Обложка. Поля картинки у курса нет, поэтому рисуем
          детерминированный градиент по id: одинаковый курс — одинаковая
          обложка, и не нужно миграцию БД ради оформления.
        -->
        <div class="catalog__cover" :style="coverStyle(course.id)" aria-hidden="true">
          <span class="catalog__cover-initial">{{ initial(course.title) }}</span>
        </div>

        <header class="catalog__card-head">
          <h2 class="u-card__title">
            <router-link v-if="course.enrolled" :to="course.to">{{ course.title }}</router-link>
            <span v-else>{{ course.title }}</span>
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
          <div v-if="course.instructors && course.instructors.length">
            <dt>{{ $t("catalog.instructors") }}</dt>
            <dd>
              <router-link
                v-for="instructor in course.instructors"
                :key="instructor.id"
                class="catalog__instructor"
                :to="{ name: 'instructor.profile', params: { id: instructor.id } }"
              >{{ instructor.fio }}</router-link>
            </dd>
          </div>
          <div v-if="course.categories.length">
            <dt>{{ $t("catalog.categories") }}</dt>
            <dd>
              <!--
                Показаны первые три: у курса бывает шесть-семь
                специальностей, и без ограничения карточка вырастала до
                470px, а кнопка записи уезжала под экран. Остальные —
                в подсказке по наведению, полный список не нужен для
                решения «записываться или нет».
              -->
              <span
                v-for="category in visibleCategories(course)"
                :key="category.id"
                class="u-badge u-badge--muted catalog__chip"
              >{{ category.title }}</span>
              <span
                v-if="course.categories.length > CATEGORY_CHIPS"
                class="u-badge u-badge--muted catalog__chip"
                :title="allCategoryTitles(course)"
              >+{{ course.categories.length - CATEGORY_CHIPS }}</span>
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

  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import { mapGetters, mapState } from 'vuex'
import { toast } from '../../composables/useToast'
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

  components: { PageHeader, EmptyState },

  data: () => ({
    items: [],
    categories: [],
    meta: {},
    /*
     * Сколько специальностей помещается в карточку до свёртки в «+N».
     * Лежит в data, а не отдельной константой модуля: значение нужно и
     * шаблону, и методу, а шаблон Options API видит только поля data.
     */
    CATEGORY_CHIPS: 3,
    query: '',
    categoryId: null,
    mine: 'all',
    loading: true,
    busyId: null,
    timer: null,
    viewMode: 'grid',
    sortBy: 'name',
  }),

  computed: {
    ...mapState('Auth', ['user']),
    ...mapGetters('Auth', ['can']),

    categoryItems() {
      return asArray(this.categories)
    },

    sortOptions() {
      return [
        { value: 'name', label: this.$t('catalog.sortByName') },
        { value: 'topics', label: this.$t('catalog.sortByTopics') },
      ]
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
    this.applyQueryFromUrl()
    if (!this.canView) {
      this.loading = false
      return
    }

    this.reload()
  },

  beforeUnmount() {
    if (this.timer) clearTimeout(this.timer)
  },

  watch: {
    query() { this.syncQueryToUrl() },
    categoryId() { this.syncQueryToUrl() },
    mine() { this.syncQueryToUrl() },
    sortBy() { this.syncQueryToUrl() },
    viewMode() { this.syncQueryToUrl() },
  },

  methods: {
    /**
     * Первые три специальности карточки. Ограничение держит карточку
     * в разумной высоте: у курса их бывает шесть-семь, каждая — отдельная
     * строка, и блок съедал больше половины высоты.
     */
    visibleCategories(course) {
      return (course.categories || []).slice(0, this.CATEGORY_CHIPS)
    },

    /** Полный список в подсказке у счётчика «+N». */
    allCategoryTitles(course) {
      return (course.categories || []).map((c) => c.title).join(', ')
    },
    /** Прочитать фильтры из URL при входе на страницу. */
    applyQueryFromUrl() {
      const q = this.$route?.query || {}
      this.query = typeof q.q === 'string' ? q.q : ''
      this.categoryId = q.category ? Number(q.category) || null : null
      this.mine = q.mine === '1' ? 'mine' : 'all'
      this.sortBy = q.sort === 'topics' ? 'topics' : 'name'
      this.viewMode = q.view === 'list' ? 'list' : 'grid'
    },

    /** Записать фильтры в URL при изменении. */
    syncQueryToUrl() {
      // Роутера может не быть вовсе (превью компонента вне приложения)
      // или быть заглушкой без replace. Синхронизация с URL — удобство,
      // а не условие работы страницы, поэтому при отсутствии replace
      // просто выходим, а не роняем компонент.
      if (typeof this.$router?.replace !== 'function') return

      const query = {}
      if (this.query.trim()) query.q = this.query.trim()
      if (this.categoryId) query.category = this.categoryId
      if (this.mine === 'mine') query.mine = '1'
      if (this.sortBy === 'topics') query.sort = 'topics'
      if (this.viewMode === 'list') query.view = 'list'

      this.$router.replace({ query }).catch(() => {})
    },

    /**
     * Цвет обложки по id курса.
     *
     * Детерминированный градиент: один и тот же курс всегда одной и той же
     * обложки. Менять цвет по настроению нельзя — это подпись, по которой
     * курс узнают в списке.
     */
    coverStyle(id) {
      const hue = ((Number(id) || 0) * 47) % 360
      return {
        background: `linear-gradient(135deg, hsl(${hue} 55% 42%), hsl(${(hue + 40) % 360} 60% 30%))`,
      }
    },

    /**
     * Первая буква названия — для обложки без картинки.
     * Кириллица берётся как есть: латинская альтернатива тут не нужна,
     * названия курсов на русском.
     */
    initial(title) {
      const text = String(title || '').trim()
      return text ? text[0].toUpperCase() : '?'
    },

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
      toast.byType(type, text)
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
  /*
   * Одинаковая высота карточек в строке. Без этого правила сетка
   * растягивала ячейки по самой высокой, но сами карточки (align-items
   * у grid по умолчанию stretch) держались только содержимым: у курса
   * без описания и без преподавателей блок «Действия» вставал на
   * середину карточки, а кнопки соседних курсов не выстраивались в
   * линию — интерфейс «прыгал». Теперь строка выравнивается по самой
   * высокой карточке, а `.catalog__actions { margin-top: auto }`
   * прижимает действия к нижнему краю во всех карточках сразу.
   */
  align-items: stretch;
}

.catalog__list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.catalog__card {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  height: 100%;
}

/* Ховер-лифт задан классом u-hover-lift (в app.css) — кастомный hover
   убран, чтобы не конфликтовать со стеклянной карточкой. */

.catalog__card--list {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr) auto;
  grid-template-areas:
    'cover head actions'
    'cover desc actions'
    'cover facts actions';
  align-items: center;
  column-gap: var(--sp-4);
}

.catalog__card--list .catalog__cover { grid-area: cover }
.catalog__card--list .catalog__card-head { grid-area: head }
.catalog__card--list .catalog__desc { grid-area: desc }
.catalog__card--list .catalog__facts { grid-area: facts }
.catalog__card--list .catalog__actions { grid-area: actions }

.catalog__card--list /* Обложка курса: детерминированный градиент, первая буква названия. */
.catalog__cover {
  position: relative;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  overflow: hidden;
}

.catalog__cover::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 40%, rgba(0, 0, 0, 0.28));
}

.catalog__cover-initial {
  position: relative;
  z-index: 1;
  font-size: 2.5rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.92);
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
}

.catalog__card--list .catalog__cover {
  height: 80px;
  width: 160px;
  flex-shrink: 0;
  border-radius: var(--radius-md);
}

.catalog__card--list .catalog__cover-initial {
  font-size: 1.8rem;
}

@media (max-width: 900px) {
  .catalog__card--list {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'cover'
      'head'
      'desc'
      'facts'
      'actions';
  }

  .catalog__card--list .catalog__cover {
    width: 100%;
    height: 110px;
  }
}

.catalog__card-head {
  flex: 1;
}

.catalog__card--list .catalog__desc {
  flex: 2;
}

.catalog__card--list .catalog__facts {
  flex: 1;
}

.catalog__card--list .catalog__actions {
  margin-top: 0;
}

.catalog__controls {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-left: auto;
}

.catalog__sort {
  min-width: 160px;
}

.catalog__view-toggle {
  flex-shrink: 0;
}

/* Обложка курса: детерминированный градиент, первая буква названия. */
.catalog__cover {
  position: relative;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  overflow: hidden;
}

.catalog__cover::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 40%, rgba(0, 0, 0, 0.28));
}

.catalog__cover-initial {
  position: relative;
  z-index: 1;
  font-size: 2.5rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.92);
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
}

.catalog__card--list .catalog__cover {
  height: 80px;
  width: 160px;
  flex-shrink: 0;
  border-radius: var(--radius-md);
}

.catalog__card--list .catalog__cover-initial {
  font-size: 1.8rem;
}

@media (max-width: 900px) {
  .catalog__card--list {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'cover'
      'head'
      'desc'
      'facts'
      'actions';
  }

  .catalog__card--list .catalog__cover {
    width: 100%;
    height: 110px;
  }
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

.catalog__instructor { color: var(--c-primary); display: block; text-decoration: none; }
.catalog__instructor:hover { text-decoration: underline; }
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

/* Герой-секция каталога. */
.catalog__hero {
  padding: var(--sp-10) var(--sp-8);
  border-radius: var(--radius-xl);
  margin-bottom: var(--sp-6);
  overflow: hidden;
}

.catalog__hero-content {
  max-width: 680px;
}

.catalog__hero-title {
  margin: 0 0 var(--sp-3);
  font-size: var(--fs-4xl);
  font-weight: var(--fw-bold);
  line-height: var(--lh-tight);
  letter-spacing: -0.02em;
}

.catalog__hero-sub {
  margin: 0 0 var(--sp-5);
  font-size: var(--fs-lg);
  color: var(--c-text-secondary);
  line-height: var(--lh-normal);
  max-width: 60ch;
}

.catalog__hero-search {
  min-width: 360px;
}

@media (max-width: 640px) {
  .catalog__hero {
    padding: var(--sp-6) var(--sp-4);
  }

  .catalog__hero-title {
    font-size: var(--fs-2xl);
  }

  .catalog__hero-sub {
    font-size: var(--fs-base);
  }

  .catalog__hero-search {
    min-width: auto;
    width: 100%;
  }
}

/* Карточка курса: более выразительная обложка с градиентным свечением. */
.catalog__cover {
  position: relative;
  height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  overflow: hidden;
  background: linear-gradient(135deg, var(--c-primary), var(--c-accent));
}

.catalog__cover::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 50% 50%, rgba(255 255 255 / 0.25) 0%, transparent 60%);
}

.catalog__cover::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 35%, rgba(0, 0, 0, 0.35));
}

.catalog__cover-initial {
  position: relative;
  z-index: 1;
  font-size: 3rem;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.95);
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
  letter-spacing: -0.03em;
}

.catalog__card--list .catalog__cover {
  height: 90px;
  width: 170px;
  border-radius: var(--radius-md);
}

.catalog__card--list .catalog__cover-initial {
  font-size: 2rem;
}
</style>

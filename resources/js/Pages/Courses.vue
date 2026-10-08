<template>
  <div class="u-page">
    <PageHeader
      :title="$t('courses.list.title')"
      :subtitle="$t('courses.list.subtitle')"
    >
      <template #actions>
        <v-btn color="primary" variant="flat" :to="{ name: 'course.store' }">
          <v-icon start icon="mdi-plus" size="18" aria-hidden="true"></v-icon>
          {{ $t("courses.list.create") }}
        </v-btn>
      </template>
    </PageHeader>

    <div class="courses">
      <!-- ============================ фильтры ============================ -->
      <aside class="courses__filters">
        <section class="u-card courses__filter-block">
          <h2 class="courses__filter-title">{{ $t("courses.list.filterAircraft") }}</h2>
          <div class="courses__chips">
            <button
              v-for="tag in tags"
              :key="tag.id"
              type="button"
              class="cats-chip"
              :class="{ 'cats-chip--on': Number(selectedAircraft) === Number(tag.id) }"
              :aria-pressed="Number(selectedAircraft) === Number(tag.id)"
              @click="clickair(tag.id)"
            >
              {{ tag.path }}
            </button>
          </div>
        </section>

        <section class="u-card courses__filter-block">
          <h2 class="courses__filter-title">{{ $t("courses.list.filterCategory") }}</h2>
          <nav class="courses__nav" :aria-label="$t('courses.list.filterCategory')">
            <button
              type="button"
              class="courses__nav-item"
              :class="{ 'courses__nav-item--on': !activeCategory }"
              :aria-current="!activeCategory ? 'true' : null"
              @click="setActiveCategory({ id: 0 })"
            >
              {{ $t("courses.list.allCategories") }}
            </button>
            <button
              v-for="_category in categories"
              :key="_category.id"
              type="button"
              class="courses__nav-item"
              :class="{ 'courses__nav-item--on': activeCategory && activeCategory.id === _category.id }"
              :aria-current="activeCategory && activeCategory.id === _category.id ? 'true' : null"
              @click="setActiveCategory(_category)"
            >
              {{ _category.title }}
            </button>
          </nav>
        </section>

        <!--
          Переключатель видимости. Раньше подпись формировалась как
          «Показать: true» — булево значение пользователю ничего
          не объясняло. Теперь это переключатель с понятными словами.
        -->
        <section class="u-card courses__filter-block">
          <v-switch
            v-model="show"
            color="primary"
            hide-details
            :label="show ? $t('courses.list.onlyVisible') : $t('courses.list.onlyHidden')"
            @update:model-value="getCourses"
          ></v-switch>
        </section>
      </aside>

      <!-- ============================= сетка ============================= -->
      <section class="courses__grid">
        <EmptyState
          v-if="!visibleCourses.length"
          :icon="'mdi-book-open-page-variant-outline'"
          :title="hasFilters ? $t('courses.list.emptyFiltered') : $t('courses.list.emptyTitle')"
          :text="hasFilters ? $t('courses.list.emptyFilteredText') : $t('courses.list.emptyText')"
        ></EmptyState>

        <article
          v-for="course in visibleCourses"
          :key="course.id"
          class="courses__card"
          :data-course-id="course.id"
        >
          <h3 class="courses__card-title">{{ course.title }}</h3>

          <p class="courses__card-text">
            {{ course.short_description || $t('courses.list.noDescription') }}
          </p>

          <div class="courses__card-meta">
            <span class="u-badge">{{ course.path }}</span>
            <span v-if="course.category" class="u-badge">{{ course.category.title }}</span>
            <span v-if="!course.visible" class="u-badge u-badge--warning">
              {{ $t("courses.list.hidden") }}
            </span>
          </div>

          <!--
            Действия: просмотр, редактирование, удаление, открыть.
            Раньше «Открыть» и «Удалить» были одного цвета error, поэтому
            необратимое действие не выделялось. Теперь удаление — единственная
            красная кнопка, а «Открыть» ведёт в отдельную вкладку, о чём
            сказано в подсказке.
          -->
          <div class="courses__actions">
            <v-btn
              size="small"
              variant="tonal"
              color="primary"
              :to="{ name: 'courses.desc', params: { idEdit: course.id } }"
            >
              {{ $t("courses.list.more") }}
            </v-btn>

            <v-btn
              size="small"
              variant="text"
              :to="{ name: 'course.update', params: { idEdit: course.id } }"
              :aria-label="`${$t('courses.list.edit')}: ${course.title}`"
              :title="$t('courses.list.edit')"
            >
              <v-icon icon="mdi-pencil-outline" size="18" aria-hidden="true"></v-icon>
            </v-btn>

            <v-btn
              size="small"
              variant="text"
              color="primary"
              :to="{ name: 'courses.itemmani', query: { idEdit: course.id } }"
              target="_blank"
              :aria-label="`${$t('courses.list.openManifest')}: ${course.title}`"
              :title="$t('courses.list.openManifestHint')"
            >
              <v-icon icon="mdi-open-in-new" size="18" aria-hidden="true"></v-icon>
            </v-btn>

            <v-btn
              size="small"
              variant="text"
              color="error"
              :aria-label="`${$t('courses.list.delete')}: ${course.title}`"
              :title="$t('courses.list.delete')"
              @click="askDelete(course)"
            >
              <v-icon icon="mdi-trash-can-outline" size="18" aria-hidden="true"></v-icon>
            </v-btn>
          </div>
        </article>
      </section>
    </div>

    <ConfirmDialog
      v-model="deleteDialog"
      :title="$t('courses.delete.title')"
      :confirm-text="$t('courses.delete.confirm')"
      :cancel-text="$t('common.cancel')"
      :busy="deleting"
      @cancel="deleteDialog = false"
      @confirm="confirmDelete"
    >
      {{ $t("courses.delete.text", { name: pendingCourse?.title ?? '' }) }}
    </ConfirmDialog>

  </div>
</template>

<script>
import { mapState } from "vuex";
import PageHeader from "../components/ui/PageHeader.vue";
import EmptyState from "../components/ui/EmptyState.vue";
import ConfirmDialog from "../components/ui/ConfirmDialog.vue";

export default {
  name: "Courses",
  components: { PageHeader, EmptyState, ConfirmDialog },

  data() {
    return {
      show: true,
      activeCategory: null,
      // id выбранного класса (самолёта). null — все.
      selectedAircraft: 0,
      isLoading: true,
      deleting: false,
      deleteDialog: false,
      pendingId: null,
    };
  },

  async created() {
    await Promise.all([
      this.$store.dispatch("Course/fetchAircrafts").catch(() => {}),
      this.$store.dispatch("Course/fetchCategories").catch(() => {}),
      this.$store.dispatch("Course/fetchCourses").catch(() => {}),
    ]).catch(() => {});

    this.isLoading = false;
  },

  computed: {
    ...mapState("Course", ["courses", "categories", "aircrafts", "totalCourses"]),

    /** Классы (самолёты) — источник чипов фильтра. */
    tags() {
      return Array.isArray(this.aircrafts) ? this.aircrafts : [];
    },

    allCourses() {
      return Array.isArray(this.courses) ? this.courses : [];
    },

    allCategories() {
      return Array.isArray(this.categories) ? this.categories : [];
    },

    /**
     * Фильтр по видимости. Раньше список дополнительно фильтровался
     * прямо в шаблоне (courses.filter(...)), из-за чего вычисляемое
     * свойство нельзя было переиспользовать и его нельзя было
     * посчитать заранее для счётчика.
     */
    visibleCourses() {
      return this.allCourses.filter((course) => course.visible === this.show);
    },

    /**
     * Применён ли фильтр.
     *
     * Нужен, чтобы пустое состояние не врало. После выбора категории
     * стор содержит уже отфильтрованный список, поэтому «курсов нет»
     * и «под фильтр ничего не подошло» по данным не различить —
     * но для пользователя это разные вещи: в первом случае надо
     * создать курс, во втором — снять фильтр.
     */
    hasFilters() {
      return Boolean(this.activeCategory) || Boolean(this.selectedAircraft);
    },
  },

  methods: {
    setActiveCategory(category) {
      // «Все категории» приходит как { id: 0 }: сбрасываем выбор.
      this.activeCategory = category && category.id ? category : null;
      this.getCourses();
    },

    clickair(itemId) {
      this.selectedAircraft = Number(itemId) === Number(this.selectedAircraft) ? 0 : itemId;
      this.getCourses();
    },

    getCourses() {
      const params = {};

      if (this.activeCategory) params.category_id = this.activeCategory.id;
      if (this.selectedAircraft) params.aircraft_id = this.selectedAircraft;

      this.isLoading = true;

      const action = Object.keys(params).length
        ? this.$store.dispatch("Course/fetchCoursesFilter", params)
        : this.$store.dispatch("Course/fetchCourses");

      Promise.resolve(action)
        .catch((error) => console.error(error))
        .finally(() => (this.isLoading = false));
    },

    askDelete(course) {
      this.pendingId = course.id;
      this.deleteDialog = true;
    },

    async confirmDelete() {
      if (!this.pendingId) return;

      this.deleting = true;

      try {
        await this.$store.dispatch("Course/deleteCourse", this.pendingId);
        await this.$store.dispatch("Course/fetchCourses");
        this.notify(this.$t("courses.delete.done"), "success");
      } catch (error) {
        this.notify(this.$t("courses.delete.error"), "error");
      } finally {
        this.deleting = false;
        this.deleteDialog = false;
        this.pendingId = null;
      }
    },

    notify(text, type) {
      toast.byType(type, text);
    },
  },
};
</script>

<style scoped>
/*
  Сетка «фильтры + карточки» вместо колонок Bulma.
  Bulma подключалась с CDN только ради этой страницы; из-за этого она
  выглядела иначе всех остальных и ломалась при любом изменении.
*/
.courses {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: var(--sp-4);
  align-items: start;
}

@media (max-width: 1100px) {
  .courses {
    grid-template-columns: minmax(0, 1fr);
  }
}

.courses__filters {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.courses__filter-block {
  padding: var(--sp-4);
}

.courses__filter-title {
  margin: 0 0 var(--sp-3);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--c-text-muted);
}

.courses__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
}

.courses__nav {
  display: flex;
  flex-direction: column;
  max-height: 320px;
  overflow-y: auto;
  margin: 0 calc(-1 * var(--sp-2));
}

/*
  Пункты навигации по разделам. Раньше это были <a> без единого
  стиля и с .menu-list a:focus { background-color: blue !important } —
  насыщенный синий фокус плюс !important поверх всего.
*/
.courses__nav-item {
  display: block;
  width: 100%;
  padding: var(--sp-2);
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--c-text);
  font: inherit;
  font-size: var(--fs-sm);
  text-align: left;
  cursor: pointer;
  transition: background-color var(--dur-fast) var(--ease),
    color var(--dur-fast) var(--ease);
}

.courses__nav-item:hover {
  background: var(--c-surface-3);
}

.courses__nav-item:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.courses__nav-item--on {
  background: var(--c-primary-soft);
  color: var(--c-primary);
  font-weight: var(--fw-medium);
}

.courses__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--sp-4);
  align-items: start;
}

.courses__card {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-4);
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-1);
  transition: box-shadow var(--dur) var(--ease),
    border-color var(--dur) var(--ease);
}

.courses__card:hover {
  box-shadow: var(--shadow-2);
  border-color: var(--c-border-strong);
}

.courses__card-title {
  margin: 0;
  font-size: var(--fs-md);
  font-weight: var(--fw-semibold);
  line-height: var(--lh-tight);
  color: var(--c-text);
}

.courses__card-text {
  margin: 0;
  font-size: var(--fs-sm);
  line-height: var(--lh-normal);
  color: var(--c-text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.courses__card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-1);
  margin-top: auto;
}

.courses__actions {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  padding-top: var(--sp-2);
  border-top: 1px solid var(--c-border);
}
</style>

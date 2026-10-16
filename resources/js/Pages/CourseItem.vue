<template>
  <div class="u-page">
    <PageHeader
      :title="$t('courses.desc.title')"
      :subtitle="$t('courses.desc.subtitle')"
    >
      <template #actions>
        <v-btn
          v-if="canEdit"
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="saving || !dirty"
          data-test="course-desc-save"
          @click="save"
        >
          {{ $t("common.save") }}
        </v-btn>
        <v-btn variant="text" :to="{ name: 'courses.list' }" data-test="course-desc-back">
          {{ $t("common.back") }}
        </v-btn>
      </template>
    </PageHeader>

    <v-alert v-if="loadError" type="error" density="compact" class="mb-4" data-test="course-desc-error">
      {{ loadError }}
    </v-alert>

    <v-card v-if="draft" class="u-card">
      <v-card-text>
        <v-form ref="form" @submit.prevent="save">
          <v-text-field
            :label="$t('common.labelOpisanie-kursa')"
            v-model="draft.title"
            :disabled="!canEdit"
            data-test="course-desc-title"
          />
          <v-text-field
            :label="$t('common.labelKorotkoe-opisanie')"
            v-model="draft.short_description"
            :disabled="!canEdit"
            data-test="course-desc-short"
          />
          <v-textarea
            :label="$t('common.labelPolnoe-opisanie')"
            v-model="draft.long_description"
            :disabled="!canEdit"
            rows="4"
            data-test="course-desc-long"
          />
          <v-select
            :label="$t('common.labelKategorii')"
            v-model="draft.category_id"
            :items="categories"
            item-value="id"
            item-title="title"
            :disabled="!canEdit"
            clearable
            data-test="course-desc-category"
          />
        </v-form>
      </v-card-text>

      <v-card-actions v-if="canEdit">
        <v-btn color="primary" variant="flat" :loading="saving" :disabled="saving || !dirty" data-test="course-desc-submit">
          {{ $t("common.save") }}
        </v-btn>
        <v-btn variant="text" :disabled="saving || !dirty" data-test="course-desc-reset">
          {{ $t("common.reset") }}
        </v-btn>
      </v-card-actions>
    </v-card>

    <EmptyState
      v-else-if="!loading"
      icon="mdi-book-open-outline"
      :title="$t('courses.desc.notFound')"
      :text="$t('courses.desc.notFoundText')"
      data-test="course-desc-empty"
    />
  </div>
</template>

<script>
/*
 * Описание курса (/courses/desc/:id).
 *
 * Раньше страница выглядела законченной, но работать не могла:
 *
 *  1. Четыре поля v-model="course.…" — редактируемые на вид, —
 *     при этом блок methods был закомментирован целиком: сохранить
 *     было НЕКОМУ. Человек правил текст и терял его.
 *  2. course до загрузки равно null, а шаблон читал course.title
 *     без проверки → «Cannot read properties of null (reading
 *     'title')», и страница падала целиком.
 *  3. <v-select> категорий без :items — список всегда пустой.
 *  4. Ни заголовка, ни крошек, ни кнопки «назад»: зайти можно было
 *     только по прямой ссылке с кнопки «Подробнее».
 *
 * Теперь это обычная страница приложения: заголовок, форма, кнопки
 * сохранения/сброса/возврата. Правка доступна тем, у кого есть
 * courses.manage (то же право, что у конструктора курса) — остальным
 * поля показаны, но заблокированы: описание читать можно всем.
 */
import { mapGetters, mapState } from "vuex";
import $api from "../api/httpClient";
import { unwrapResponse } from "../api/envelope";
import PageHeader from "../components/ui/PageHeader.vue";
import EmptyState from "../components/ui/EmptyState.vue";
import { toast } from "../composables/useToast";

const emptyDraft = () => ({
  title: "",
  short_description: "",
  long_description: "",
  category_id: null,
});

export default {
  name: "CourseItem",

  components: { PageHeader, EmptyState },

  props: {
    idEdit: { type: Number, required: true },
  },

  data() {
    return {
      draft: null,
      initial: null,
      categories: [],
      saving: false,
      loading: true,
      loadError: null,
    };
  },

  computed: {
    ...mapState("Course", ["course"]),
    ...mapGetters("Auth", ["can"]),

    /** Право на правку описания — то же, что у конструктора курса. */
    canEdit() {
      return this.can("courses.manage");
    },

    /** Есть несохранённые изменения: включает кнопку «Сохранить». */
    dirty() {
      if (!this.draft || !this.initial) return false;
      return JSON.stringify(this.draft) !== JSON.stringify(this.initial);
    },
  },

  async mounted() {
    await this.load();
  },

  /*
   * Уход со страницы с несохранёнными правками спрашивает подтверждение.
   * Options-API-эквивалент composable useLeaveGuard: тот использует
   * onBeforeRouteLeave и требует setup-контекста, которого у этого
   * компонента нет.
   */
  beforeRouteLeave(to, from, next) {
    if (!this.dirty) {
      next();

      return;
    }

    const ok = window.confirm(this.$t("common.leaveConfirm"));
    next(ok);
  },

  methods: {
    async load() {
      this.loading = true;
      this.loadError = null;

      try {
        const [courseRes, catRes] = await Promise.all([
          $api.get(`/api/course/${this.idEdit}`, { optional: true }),
          $api.get("/api/categories", { optional: true }),
        ]);

        const course = unwrapResponse(courseRes);
        this.categories = unwrapResponse(catRes) ?? [];

        if (!course) {
          this.draft = null;
          return;
        }

        this.draft = {
          title: course.title ?? "",
          short_description: course.short_description ?? "",
          long_description: course.long_description ?? "",
          category_id: course.categories?.[0]?.id ?? null,
        };
        this.initial = { ...this.draft };
      } catch (e) {
        this.loadError = this.$t("courses.desc.loadFailed");
      } finally {
        this.loading = false;
      }
    },

    async save() {
      if (!this.draft || this.saving) return;

      this.saving = true;

      try {
        await $api.put(`/api/course/${this.idEdit}`, {
          title: this.draft.title,
          short_description: this.draft.short_description,
          long_description: this.draft.long_description,
          category_id: this.draft.category_id,
        });

        // Сервер — источник истины: перечитываем, чтобы поля показали
        // ровно то, что сохранилось (trim, значения по умолчанию).
        await this.$store.dispatch("Course/fetchCourses").catch(() => {});
        await this.load();
        toast.success(this.$t("courses.desc.saved"));
      } catch (e) {
        toast.error(e?.response?.data?.error?.message || this.$t("courses.desc.saveFailed"));
      } finally {
        this.saving = false;
      }
    },

    reset() {
      if (this.initial) this.draft = { ...this.initial };
    },
  },
};
</script>
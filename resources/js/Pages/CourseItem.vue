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
        <v-form ref="form" @submit.prevent="handleSave">
          <v-row>
            <v-col cols="12" md="6">
              <v-form-group
                :error="form.errors.title"
                :label="$t('courses.desc.fieldTitle')"
              >
                <v-text-field
                  v-model="form.values.title"
                  :placeholder="$t('courses.desc.fieldTitlePlaceholder')"
                  data-test="course-desc-title"
                />
              </v-form-group>
            </v-col>
            <v-col cols="12" md="6">
              <v-form-group
                :error="form.errors.shortDescription"
                :label="$t('courses.desc.fieldShortDescription')"
              >
                <v-text-field
                  v-model="form.values.shortDescription"
                  :placeholder="$t('courses.desc.fieldShortDescriptionPlaceholder')"
                  data-test="course-desc-short-description"
                />
              </v-form-group>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <v-form-group
                :error="form.errors.longDescription"
                :label="$t('courses.desc.fieldLongDescription')"
              >
                <v-textarea
                  v-model="form.values.longDescription"
                  :placeholder="$t('courses.desc.fieldLongDescriptionPlaceholder')"
                  rows="5"
                  data-test="course-desc-long-description"
                />
              </v-form-group>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <v-form-group
                :error="form.errors.categoryId"
                :label="$t('courses.desc.fieldCategory')"
              >
                <v-select
                  v-model="form.values.categoryId"
                  :items="categories"
                  :label="$t('courses.desc.fieldCategory')"
                  :rules="[categoryRule]"
                  item-text="name"
                  item-value="id"
                  data-test="course-desc-category"
                >
                  <v-option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                  >
                    {{ category.name }}
                  </v-option>
                </v-select>
              </v-form-group>
            </v-col>
          </v-row>
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
import { mapGetters, mapState, reactive, watch } from "vuex"
import $api from "../api/httpClient"
import { unwrapResponse } from "../api/envelope"
import PageHeader from "../components/ui/PageHeader.vue"
import EmptyState from "../components/ui/EmptyState.vue"
import useForm from "../../composables/useForm"
import { toast } from "../composables/useToast"
import { required, maxLength } from "../../composables/validation/rules"

const emptyDraft = () => ({
  title: "",
  short_description: "",
  long_description: "",
  category_id: null,
})

export default {
  name: "CourseItem",

  components: {
    PageHeader,
    EmptyState,
  },

  computed: {
    ...mapState("Course", ["course"]),
    ...mapGetters("Course", ["categories"]),

    draft() {
      return this.course && this.course.id
        ? this.course.description
        : emptyDraft()
    },

    dirty: computed(() => {
      return this.draft && JSON.stringify(this.draft) !== JSON.stringify(this.form.values)
    }),

    canEdit: computed(() => this.$auth.user && this.$auth.user.can("courses.manage")),

    loadError: computed(() => this.course && this.course.loadError),

    categoryRule: computed(() => [required("Категория обязательна")]),
  },

  watch: {
    "draft"(newValue) {
      if (newValue) {
        this.form.setValues(newValue)
      }
    },
  },

  setup() {
    const form = useForm({
      fields: ["title", "short_description", "long_description", "category_id"],
      initialValues: emptyDraft(),
      validate: (values) => {
        const errors = {}
        if (!values.title?.trim()) {
          errors.title = "Название обязательно"
        }
        if (!values.short_description?.trim()) {
          errors.short_description = "Короткое описание обязательно"
        }
        if (!values.long_description?.trim()) {
          errors.long_description = "Описание обязательно"
        }
        if (!values.category_id) {
          errors.category_id = "Категория обязательна"
        }
        return Object.keys(errors).length ? errors : true
      },
      onSubmit: async ({ values }) => {
        try {
          const response = await $api.put(
            `/api/courses/${this.course.id}`,
            values
          )
          const data = unwrapResponse(response)
          toast.success("Курс успешно обновлен")
          return data
        } catch (e) {
          toast.error("Ошибка сохранения курса")
          throw e
        }
      },
    })

    return { form }
  },

  mounted() {
    if (this.course && this.course.loadError) {
      this.loadError = this.course.loadError
    }
  },
}
</script>
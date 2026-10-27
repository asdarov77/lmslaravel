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
          :loading="form.submitting.value"
          :disabled="form.submitting.value || !form.dirty.value"
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

    <v-skeleton-loader v-if="loading" type="article, actions" class="u-card u-card__body" />

    <v-card v-else-if="loaded" class="u-card">
      <v-card-text>
        <v-form @submit.prevent="save">
          <v-row>
            <v-col cols="12" md="6">
              <FormField
                :label="$t('courses.desc.fieldTitle')"
                name="title"
                :error="form.errors.title"
                :required="form.isRequired('title')"
              >
                <v-text-field
                  v-bind="form.bind('title')"
                  :placeholder="$t('courses.desc.fieldTitlePlaceholder')"
                  data-test="course-desc-title"
                />
              </FormField>
            </v-col>
            <v-col cols="12" md="6">
              <FormField
                :label="$t('courses.desc.fieldShortDescription')"
                name="short_description"
                :error="form.errors.short_description"
              >
                <v-text-field
                  v-bind="form.bind('short_description')"
                  :placeholder="$t('courses.desc.fieldShortDescriptionPlaceholder')"
                  data-test="course-desc-short-description"
                />
              </FormField>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <FormField
                :label="$t('courses.desc.fieldLongDescription')"
                name="long_description"
                :error="form.errors.long_description"
              >
                <v-textarea
                  v-bind="form.bind('long_description')"
                  :placeholder="$t('courses.desc.fieldLongDescriptionPlaceholder')"
                  rows="5"
                  data-test="course-desc-long-description"
                />
              </FormField>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <FormField
                :label="$t('courses.desc.fieldCategory')"
                name="category_id"
                :error="form.errors.category_id"
                :required="form.isRequired('category_id')"
              >
                <v-select
                  v-bind="form.bind('category_id')"
                  :items="categories"
                  item-title="title"
                  item-value="id"
                  data-test="course-desc-category"
                />
              </FormField>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
      <v-card-actions v-if="canEdit">
        <v-btn
          color="primary"
          variant="flat"
          :loading="form.submitting.value"
          :disabled="form.submitting.value || !form.dirty.value"
          data-test="course-desc-submit"
          @click="save"
        >
          {{ $t("common.save") }}
        </v-btn>
        <v-btn
          variant="text"
          :disabled="form.submitting.value || !form.dirty.value"
          data-test="course-desc-reset"
          @click="reset"
        >
          {{ $t("common.reset") }}
        </v-btn>
      </v-card-actions>
    </v-card>

    <EmptyState
      v-else
      icon="mdi-book-open-outline"
      :title="$t('courses.desc.notFound')"
      :text="$t('courses.desc.notFoundText')"
      data-test="course-desc-empty"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import FormField from '../components/ui/FormField.vue'
import { useForm } from '../composables/useForm'
import { toast } from '../composables/useToast'
import { required, maxLength } from '../composables/validation/rules'

const route = useRoute()
const store = useStore()
const { t } = useI18n()

const idEdit = Number(route.params.idEdit)
const loading = ref(true)
const loaded = ref(false)
const loadError = ref('')
const loadedValues = ref({})

const canEdit = computed(() => store.getters['Auth/can']('courses.manage'))
const categories = computed(() => store.getters['Course/categories'] || [])

const form = useForm({
  schema: {
    title: { initial: '', rules: [required(t('course.errors.titleRequired')), maxLength(255, t('course.errors.titleTooLong'))] },
    short_description: { initial: '', rules: [maxLength(255, t('course.errors.shortTooLong'))] },
    long_description: { initial: '', rules: [maxLength(2000, t('course.errors.longTooLong'))] },
    category_id: { initial: null, rules: [required(t('course.errors.categoriesRequired'))] },
  },
  draftKey: Number.isFinite(idEdit) ? `course.desc.${idEdit}` : null,
  onSubmit: async ({ values }) => {
    await store.dispatch('Course/updateCourse', {
      id: idEdit,
      data: {
        title: values.title.trim(),
        short_description: values.short_description.trim(),
        long_description: values.long_description.trim(),
        category_id: values.category_id || null,
      },
    })
    toast.success(t('courses.desc.saved'))
  },
})

function courseValues(course) {
  return {
    title: course.title ?? '',
    short_description: course.short_description ?? '',
    long_description: course.long_description ?? '',
    category_id: Number(course.category_id) || course.categories?.[0]?.id || null,
  }
}

function reset() {
  form.reset(loadedValues.value)
}

async function save() {
  await form.submit()
}

onMounted(async () => {
  if (!Number.isFinite(idEdit)) {
    loadError.value = t('courses.desc.notFoundText')
    loading.value = false
    return
  }

  store.dispatch('Course/fetchCategories').catch(() => {})

  try {
    await store.dispatch('Course/fetchCourse', { course_id: idEdit })
    const course = store.state.Course.course
    if (!course || course.id === undefined || course.id === null) {
      loadError.value = t('courses.desc.notFoundText')
      return
    }
    loadedValues.value = courseValues(course)
    form.reset(loadedValues.value)
    loaded.value = true
  } catch (error) {
    loadError.value = error?.response?.data?.message || t('courses.desc.loadFailed')
  } finally {
    loading.value = false
  }
})
</script>

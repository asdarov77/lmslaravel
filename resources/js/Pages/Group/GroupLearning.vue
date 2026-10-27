<template>
  <div class="u-page">
    <PageHeader :title="$t('groupLearning.title')" />

    <FormCard
      :busy="form.submitting.value"
      @submit="submitForm"
      @cancel="cancel"
    >
      <v-alert
        v-if="form.serverError.value"
        type="error"
        variant="tonal"
        class="mb-4"
        :text="form.serverError.value"
      />

      <!-- Выбор группы -->
      <FormField
        :label="$t('groupLearning.group')"
        name="group_id"
        :error="form.errors.group_id"
        :required="form.isRequired('group_id')"
      >
        <v-select
          v-bind="form.bind('group_id')"
          :items="allGroups"
          item-title="groupname"
          item-value="id"
          :placeholder="$t('groupLearning.selectGroup')"
          :disabled="form.submitting.value"
          variant="outlined"
          density="compact"
          hide-details
        />
      </FormField>

      <!-- Выбор класса (ВС) с каскадом к категориям -->
      <FormField
        :label="$t('groupLearning.class')"
        name="aircraft_id"
        :error="form.errors.aircraft_id"
        :required="form.isRequired('aircraft_id')"
        :hint="$t('groupLearning.classHint')"
      >
        <CascadeSelect
          v-model="form.values.aircraft_id"
          :options="aircraftOptions"
          :placeholder="$t('groupLearning.selectAircraft')"
          :disabled="form.submitting.value"
          :clearable="true"
          @update:model-value="onAircraftChange"
        />
      </FormField>

      <!-- Выбор категории (зависит от выбранного класса) -->
      <FormField
        :label="$t('groupLearning.categories')"
        name="category_id"
        :error="form.errors.category_id"
        :required="form.isRequired('category_id')"
        :disabled="!form.values.aircraft_id"
      >
        <RemoteAutocomplete
          v-model="form.values.category_id"
          :options="categoryOptions"
          :placeholder="$t('groupLearning.selectCategory')"
          :disabled="form.submitting.value || !form.values.aircraft_id"
          :clearable="true"
          :search-debounce="0"
        />
      </FormField>

      <v-divider class="my-4" />

      <!-- Дерево курсов/модулей -->
      <FormField
        v-if="form.values.aircraft_id && form.values.category_id"
        :label="$t('groupLearning.courses')"
        name="course_ids"
        :error="form.errors.course_ids"
        :required="form.isRequired('course_ids')"
        :hint="$t('groupLearning.materialsOnlyHint')"
      >
        <treeselect
          :key="treeselectKey"
          :placeholder="$t('groupLearning.coursesPlaceholder')"
          :default-expand-level="1"
          v-model="form.values.course_ids"
          :options="courseOptions"
          :multiple="true"
          :clearable="false"
          :disabled="form.submitting.value"
        />
      </FormField>

      <v-alert
        v-else-if="form.values.aircraft_id && !form.values.category_id"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-3"
        :text="$t('groupLearning.selectCategoryFirst')"
      />

      <v-alert
        v-else-if="!form.values.aircraft_id"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-3"
        :text="$t('groupLearning.selectAircraftFirst')"
      />

      <v-alert
        v-if="form.values.course_ids.length"
        type="success"
        variant="tonal"
        density="compact"
        class="mt-3"
        :text="$t('groupLearning.selectedCount', { count: form.values.course_ids.length })"
      />

      <v-divider class="my-4" />

      <!-- Преподаватель -->
      <FormField
        :label="$t('groupLearning.instructor')"
        name="teacher"
        :error="form.errors.teacher"
      >
        <RemoteAutocomplete
          v-model="form.values.teacher"
          :options="instructorOptions"
          :placeholder="$t('groupLearning.selectInstructor')"
          :clearable="true"
          :search-debounce="150"
        />
      </FormField>

      <!-- Тип занятия -->
      <FormField
        :label="$t('groupLearning.lessonType')"
        name="typeOfLesson"
        :error="form.errors.typeOfLesson"
      >
        <v-select
          v-bind="form.bind('typeOfLesson')"
          :items="lessonTypes"
          :placeholder="$t('groupLearning.selectLessonType')"
          :disabled="form.submitting.value"
          variant="outlined"
          density="compact"
          hide-details
        />
      </FormField>

      <v-divider class="my-4" />

      <!-- Период обучения (DateRangeField) -->
      <FormField
        :label="$t('groupLearning.period')"
        name="period"
        :error="form.errors.period"
        :required="form.isRequired('period')"
        :hint="$t('groupLearning.periodHint')"
      >
        <DateRangeField
          v-model="periodRange"
          :start-label="$t('groupLearning.studyFrom')"
          :end-label="$t('groupLearning.studyTo')"
          :required="true"
          :min-date="today"
          @update:model-value="onPeriodChange"
        />
      </FormField>

      <!-- Дедлайн -->
      <FormField
        :label="$t('plan.deadline')"
        name="deadline"
        :error="form.errors.deadline"
        :hint="$t('groupLearning.deadlineHint')"
      >
        <MaskedField
          v-model="form.values.deadline"
          preset="date"
          :placeholder="$t('groupLearning.selectDeadline')"
          :clearable="true"
          :disabled="form.submitting.value"
        />
      </FormField>

      <!-- Ошибки валидации на уровне формы (не полей) -->
      <v-alert
        v-for="(error, index) in form.nonFieldErrors"
        :key="index"
        type="error"
        variant="tonal"
        class="mb-2"
        :text="error"
      />
    </FormCard>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRoute, useRouter } from 'vue-router'
import $api from '../../api/httpClient'
import { unwrapArray, numericQuery, asArray } from '../../api/envelope'
import { canonicalRoleSlug } from '../../utils/roles'
import { toast } from '../../composables/useToast'
import useForm from '../../composables/useForm'
import useLeaveGuard from '../../composables/useLeaveGuard'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import DateRangeField from '../../components/ui/fields/DateRangeField.vue'
import MaskedField from '../../components/ui/fields/MaskedField.vue'
import CascadeSelect from '../../components/ui/fields/CascadeSelect.vue'
import RemoteAutocomplete from '../../components/ui/fields/RemoteAutocomplete.vue'
import Treeselect from 'vue3-treeselect'
import 'vue3-treeselect/dist/vue3-treeselect.css'

const { t } = useI18n()
const store = useStore()
const route = useRoute()
const router = useRouter()

const props = defineProps({
  idEdit: { type: Number, default: null },
})

const today = new Date().toISOString().slice(0, 10)

const form = useForm({
  schema: {
    group_id: {
      initial: null,
      rules: [],
    },
    aircraft_id: {
      initial: null,
      rules: [],
    },
    category_id: {
      initial: null,
      rules: [],
    },
    course_ids: {
      initial: [],
      rules: [],
    },
    teacher: {
      initial: null,
      rules: [],
    },
    typeOfLesson: {
      initial: 'Лекция',
      rules: [],
    },
    study_from: {
      initial: today,
      rules: [],
    },
    study_to: {
      initial: null,
      rules: [],
    },
    deadline: {
      initial: null,
      rules: [],
    },
  },

  draftKey: 'groupLearning',

  async onSubmit({ values }) {
    // Валидация перед отправкой
    const errors = []
    const fieldErrors = {}

    if (!values.group_id) fieldErrors.group_id = t('groupLearning.groupRequired')
    if (!values.aircraft_id) fieldErrors.aircraft_id = t('groupLearning.classRequired')
    if (!values.category_id) fieldErrors.category_id = t('groupLearning.categoryRequired')
    if (!values.course_ids?.length) errors.push(t('groupLearning.coursesRequired'))
    if (!values.study_from) fieldErrors.study_from = t('groupLearning.fromRequired')
    if (!values.study_to) fieldErrors.study_to = t('groupLearning.toRequired')
    else if (values.study_from && values.study_to < values.study_from) {
      fieldErrors.study_to = t('groupLearning.toBeforeFrom')
    }

    if (Object.keys(fieldErrors).length > 0) {
      form.setFieldErrors(fieldErrors)
    }
    if (errors.length > 0) {
      form.setNonFieldErrors(errors)
    }

    if (Object.keys(fieldErrors).length > 0 || errors.length > 0) {
      toast.error(t('groupLearning.checkForm'))
      return false
    }

    // Подготовка данных для сохранения
    const entries = Array.from(new Set(values.course_ids)).map((nodeId) => {
      const asCourse = /^course-(\d+)$/.exec(String(nodeId))
      return asCourse
        ? { course_id: Number(asCourse[1]), parent_id: null }
        : { course_id: nodeCourse.value[nodeId], parent_id: nodeId }
    }).filter((entry) => entry.course_id)

    try {
      await store.dispatch('Course/fetchGroup2learnings', {
        group_id: values.group_id,
        entries,
        category_id: values.category_id,
        teacher: values.teacher,
        typeOfLesson: values.typeOfLesson,
        study_from: values.study_from,
        study_to: values.study_to,
        deadline: values.deadline || null,
      })

      toast.success(t('groupLearning.enrolled'))
      await router.push('/groups/list')
    } catch (error) {
      collectErrors(error)
      toast.error(form.nonFieldErrors[0] || t('groupLearning.saveFailed'))
      throw error
    }
  },
})

const { confirmLeave } = useLeaveGuard(() => form.dirty.value, { message: t('common.leaveConfirm') })

const aircraftOptions = computed(() =>
  (store.state.Course.aircrafts || []).map((a) => ({
    value: a.id,
    label: a.path,
  }))
)

const instructorOptions = computed(() => {
  const users = store.state.User.users || []
  return users
    .filter((u) => canonicalRoleSlug(u.role) === 'instructor')
    .map((u) => ({ value: u.fio, label: u.fio }))
})

const periodRange = ref({ start: today, end: null })

const onPeriodChange = (range) => {
  form.setFieldValue('study_from', range.start)
  form.setFieldValue('study_to', range.end || null)
  periodRange.value = range
}

const categoryOptions = ref([])

const onAircraftChange = async (aircraftId) => {
  form.setFieldValue('category_id', null)
  form.setFieldValue('course_ids', [])
  categoryOptions.value = []
  courseOptions.value = []
  treeselectKey.value += 1

  if (!aircraftId) return

  // Загружаем категории для выбранного класса
  const categories = (store.state.Course.categories || []).filter(
    (c) => Number(c.aircraft_id) === Number(aircraftId)
  )
  categoryOptions.value = categories.map((c) => ({
    value: c.id,
    label: c.title,
  }))
}

const courseOptions = ref([])
const nodeCourse = ref({})
const treeselectKey = ref(0)

watch(() => form.values.category_id, async (categoryId) => {
  if (!categoryId || !form.values.aircraft_id) {
    courseOptions.value = []
    nodeCourse.value = {}
    treeselectKey.value += 1
    return
  }

  form.setFieldValue('course_ids', [])
  treeselectKey.value += 1

  try {
    const params = numericQuery({
      aircraft_id: form.values.aircraft_id,
      category_id: categoryId,
    })
    const response = await $api.get('/api/course', { params })
    const courses = unwrapArray(response)

    const nodeCourseMap = {}
    courseOptions.value = courses.map((course) => ({
      id: `course-${course.id}`,
      label: course.title,
      children: flatToHierarchy(asArray(course.aukstructures), course.id, nodeCourseMap),
    }))
    nodeCourse.value = nodeCourseMap
  } catch (error) {
    courseOptions.value = []
    form.setNonFieldErrors([t('groupLearning.loadCoursesError')])
    console.error(error)
  }
})

const flatToHierarchy = (flat, courseId, nodeCourseMap) => {
  const byId = new Map()

  flat.forEach((item) => {
    if (!item) return
    nodeCourseMap[item.id] = courseId
    byId.set(item.id, {
      id: item.id,
      label: item.title,
      isDisabled: Number(item.type) !== 3,
      children: [],
    })
  })

  const roots = []
  byId.forEach((node) => {
    const parent = byId.get(node.parent_id)
    if (parent && parent !== node) {
      parent.children.push(node)
    } else {
      roots.push(node)
    }
  })
  return roots
}

const collectErrors = (error) => {
  const details = error?.response?.data?.error?.details
  if (details && typeof details === 'object') {
    Object.entries(details).forEach(([field, messages]) => {
      const text = Array.isArray(messages) ? messages.join(' ') : String(messages)
      form.setFieldError(field.replace(/\.\d+$/, ''), text)
    })
    return
  }
  form.setNonFieldError(error?.response?.data?.error?.message || error?.message || t('groupLearning.saveFailed'))
}

onMounted(async () => {
  await Promise.all([
    store.dispatch('User/fetchGroups'),
    store.dispatch('User/fetchUsers'),
    store.dispatch('Course/fetchCategories'),
    store.dispatch('Course/fetchAircrafts'),
  ]).catch((error) => console.error(error))

  const fromRoute = Number(props.idEdit)
  if (Number.isInteger(fromRoute) && fromRoute > 0) {
    form.setFieldValue('group_id', fromRoute)
  }
})

const cancel = () => {
  router.go(-1)
}

defineExpose({ form, submitForm: form.submit })
</script>

<style scoped>
:deep(.v-card-text) {
  font-size: 1rem;
}

:deep(.vue-treeselect__control) {
  height: 56px;
  border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-3);
  margin-bottom: 17px;
  border-radius: var(--radius-md);
}

:deep(.vue-treeselect__placeholder),
:deep(.vue-treeselect__single-value) {
  padding-left: 12px;
  line-height: 56px;
  color: var(--c-text-muted);
}

:deep(.vue-treeselect__control-arrow-container) {
  width: 38px;
}

:deep(.vue-treeselect__multi-value-item) {
  cursor: pointer;
  color: var(--c-text-secondary);
  background-color: var(--c-surface-2);
  border-bottom: 3px solid var(--c-primary);
  border-radius: var(--radius-sm);
}

:deep(.vue-treeselect--has-value .vue-treeselect__multi-value) {
  margin-bottom: 15px;
}
</style>
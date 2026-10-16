<template>
  <div class="u-page">
    <PageHeader :title="headerTitle">
      <template #actions>
        <v-chip :color="readiness.color" variant="tonal" size="small" data-test="wizard-readiness">
          {{ readiness.text }}
        </v-chip>
      </template>
    </PageHeader>

    <StepperShell
      v-model="activeStep"
      :steps="steps"
      :busy="form.submitting.value"
      :finish-text="t('common.save')"
      :before-next="beforeNext"
      @step-change="onStepChange"
      @finish="finish"
    >
      <!-- ------------------------------------------------------- Основное -->
      <template #basic>
        <div class="u-wizard">
          <div>
            <FormField
              :label="t('exams.wizard.title')"
              name="title"
              :error="form.errors.title"
              :required="form.isRequired('title')"
            >
              <v-text-field v-bind="form.bind('title')" data-test="exam-title" />
            </FormField>

            <FormField
              :label="t('exams.wizard.description')"
              name="description"
              :error="form.errors.description"
              :counter="descCounter"
            >
              <v-textarea
                v-bind="form.bind('description')"
                :maxlength="500"
                :rows="3"
                @blur="form.validateField('description')"
              />
            </FormField>

            <FormField
              :label="t('exams.wizard.category')"
              name="category_id"
              :error="form.errors.category_id"
              :required="form.isRequired('category_id')"
            >
              <v-select
                v-bind="form.bind('category_id')"
                :items="categories"
                item-value="id"
                item-title="title"
                data-test="exam-category"
                clearable
                @update:model-value="onCategoryChange"
              />
            </FormField>

            <FormField
              :label="t('exams.wizard.module')"
              name="module_id"
              :error="form.errors.module_id"
            >
              <v-select
                v-bind="form.bind('module_id')"
                :items="modules"
                item-value="id"
                item-title="title"
                clearable
                :disabled="!form.values.category_id"
              />
            </FormField>

            <FormField
              :label="t('exams.wizard.course')"
              name="course_id"
              :error="form.errors.course_id"
            >
              <v-select
                v-bind="form.bind('course_id')"
                :items="courses"
                item-value="id"
                item-title="title"
                clearable
                :disabled="!form.values.category_id"
              />
            </FormField>
          </div>

          <ExamPreview
            class="u-wizard__aside"
            :label="t('exams.wizard.preview')"
            :title="form.values.title"
            :description="form.values.description"
            :categories="previewCategories"
            :modules="previewModules"
            :title-placeholder="t('exams.wizard.previewNoTitle')"
            :categories-placeholder="t('exams.wizard.previewNoCategories')"
          />
        </div>
      </template>

      <!-- ------------------------------------------------------ Вопросы -->
      <template #questions>
        <div class="u-wizard">
          <div>
            <h3 class="u-section__title">{{ t('exams.wizard.stepQuestions') }}</h3>
            <p class="u-section__hint">{{ t('exams.wizard.stepQuestionsHint') }}</p>

            <div class="d-flex align-center mb-3">
              <v-switch
                v-model="form.values.shuffle"
                :label="t('exams.wizard.shuffle')"
                color="primary"
                hide-details
                data-test="exam-shuffle"
              />
            </div>

            <div v-if="modules.length" class="exams-wizard__module-rows">
              <h4 class="u-section__sub-title mb-2">{{ t('exams.wizard.questionsByModule') }}</h4>
              <div v-for="module in modules" :key="module.id" class="exams-wizard__module-row mb-3">
                <div class="d-flex align-center mb-1">
                  <span class="exams-wizard__module-title">{{ module.title }}</span>
                  <v-spacer />
                  <span class="u-badge u-badge--primary">{{ availableQuestions[module.id] || 0 }} {{ t('exams.wizard.available') }}</span>
                </div>
                <v-row dense class="exams-wizard__limits">
                  <v-col cols="12" md="6">
                    <FormField
                      :label="t('exams.wizard.questionLimit')"
                      name="question_limit"
                      :error="form.errors.question_limit"
                      :hint="t('exams.wizard.questionLimitHint')"
                    >
                      <v-text-field
                        v-bind="form.bind('question_limit')"
                        type="number"
                        min="0"
                        max="200"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                      />
                    </FormField>
                  </v-col>
                  <v-col cols="12" md="6">
                    <FormField
                      :label="t('exams.wizard.perModuleLimit')"
                      name="per_module_limits"
                      :error="form.errors.per_module_limits"
                    >
                      <v-text-field
                        v-model="perModuleLimits[module.id]"
                        type="number"
                        min="0"
                        max="200"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        :label="t('exams.wizard.perModuleLimit')"
                        @update:model-value="updatePerModuleLimit(module.id, $event)"
                      />
                    </FormField>
                  </v-col>
                </v-row>
              </div>
            </div>

            <v-alert v-else-if="form.values.category_id" type="info" variant="tonal" class="mb-3">
              {{ t('exams.wizard.noModulesForCategory') }}
            </v-alert>

            <v-alert v-else type="info" variant="tonal" class="mb-3">
              {{ t('exams.wizard.selectCategoryFirst') }}
            </v-alert>

            <ExamPreview
              class="u-wizard__aside"
              :label="t('exams.wizard.questionsPreview')"
              :total-questions="totalQuestions"
              :per-module-limits="perModuleLimits"
              :shuffle="form.values.shuffle"
              :modules="modules"
              :available="availableQuestions"
            />
          </div>
        </div>
      </template>

      <!-- ------------------------------------------------------ Настройки -->
      <template #settings>
        <div class="u-wizard">
          <div>
            <h3 class="u-section__title">{{ t('exams.wizard.passingScore') }}</h3>
            <FormField
              :label="t('exams.wizard.passingScore')"
              name="passing_score"
              :error="form.errors.passing_score"
              :required="form.isRequired('passing_score')"
              :hint="t('exams.wizard.passingScoreHint')"
            >
              <v-text-field
                v-bind="form.bind('passing_score')"
                type="number"
                min="0"
                max="1"
                step="0.01"
                suffix="%"
                density="compact"
                variant="outlined"
                hide-details
                data-test="exam-passing-score"
              />
            </FormField>

            <v-progress-linear
              v-if="form.values.passing_score !== '' && form.values.passing_score !== null"
              :model-value="form.values.passing_score * 100"
              :color="gradeColor"
              height="6"
              rounded
              class="mt-2"
            />
            <p v-if="form.values.passing_score !== '' && form.values.passing_score !== null" class="exams-wizard__grade-hint mt-1">
              {{ t('exams.wizard.gradePreview', { grade: gradePreview, boundary: gradeBoundary }) }}
            </p>

            <h3 class="u-section__title mt-5">{{ t('exams.wizard.maxAttempts') }}</h3>
            <FormField
              :label="t('exams.wizard.maxAttempts')"
              name="max_attempts"
              :error="form.errors.max_attempts"
              :required="form.isRequired('max_attempts')"
            >
              <v-text-field
                v-bind="form.bind('max_attempts')"
                type="number"
                min="1"
                max="20"
                density="compact"
                variant="outlined"
                hide-details
                data-test="exam-max-attempts"
              />
            </FormField>

            <h3 class="u-section__title mt-5">{{ t('exams.wizard.window') }}</h3>
            <FormField
              :label="t('exams.wizard.opensAt')"
              name="opens_at"
              :error="form.errors.opens_at"
            >
              <v-text-field
                v-bind="form.bind('opens_at')"
                type="datetime-local"
                density="compact"
                variant="outlined"
                hide-details
              />
            </FormField>

            <FormField
              :label="t('exams.wizard.closesAt')"
              name="closes_at"
              :error="form.errors.closes_at"
            >
              <v-text-field
                v-bind="form.bind('closes_at')"
                type="datetime-local"
                density="compact"
                variant="outlined"
                hide-details
              />
            </FormField>

            <FormField
              :label="t('exams.wizard.dueAt')"
              name="due_at"
              :error="form.errors.due_at"
            >
              <v-text-field
                v-bind="form.bind('due_at')"
                type="datetime-local"
                density="compact"
                variant="outlined"
                hide-details
              />
            </FormField>

            <h3 class="u-section__title mt-5">{{ t('exams.wizard.assignment') }}</h3>
            <FormField
              :label="t('exams.wizard.assignGroup')"
              name="group_id"
              :error="form.errors.group_id"
            >
              <v-select
                v-bind="form.bind('group_id')"
                :items="groups"
                item-value="id"
                item-title="groupname"
                clearable
              />
            </FormField>

            <FormField
              :label="t('exams.wizard.assignUser')"
              name="user_id"
              :error="form.errors.user_id"
            >
              <v-select
                v-bind="form.bind('user_id')"
                :items="users"
                item-value="id"
                item-title="fio"
                clearable
              />
            </FormField>

            <ExamPreview
              class="u-wizard__aside"
              :label="t('exams.wizard.settingsPreview')"
              :passing-score="form.values.passing_score"
              :max-attempts="form.values.max_attempts"
              :opens-at="form.values.opens_at"
              :closes-at="form.values.closes_at"
              :due-at="form.values.due_at"
              :group="selectedGroup"
              :user="selectedUser"
            />
          </div>
        </div>
      </template>

      <!-- ----------------------------------------------------- Проверка -->
      <template #review>
        <div class="u-wizard">
          <div>
            <h3 class="u-section__title">{{ t('exams.wizard.reviewTitle') }}</h3>

            <ul class="u-wizard__list">
              <li
                v-for="item in reviewItems"
                :key="item.key"
                class="u-wizard__list-item"
                :class="{ 'is-done': item.done }"
              >
                <v-icon
                  :icon="item.done ? 'mdi-check-circle' : 'mdi-alert-circle-outline'"
                  :color="item.done ? 'success' : 'warning'"
                  size="18"
                  aria-hidden="true"
                />
                <span>{{ item.label }}</span>
                <span v-if="item.value" class="ms-auto u-badge u-badge--primary">{{ item.value }}</span>
              </li>
            </ul>

            <v-alert
              v-if="reviewWarning"
              type="warning"
              variant="tonal"
              class="mt-3"
            >
              {{ reviewWarning }}
            </v-alert>
          </div>

          <SectionCard class="u-wizard__aside" :title="t('exams.wizard.finalNote')">
            <p class="u-wizard__hint mb-0">{{ t('exams.wizard.finalNoteText') }}</p>
          </SectionCard>
        </div>
      </template>
    </StepperShell>

    <div class="u-draft-hint">
      <v-icon icon="mdi-content-save-check-outline" size="16" aria-hidden="true" />
      {{ t('common.draftSaved') }}
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRoute, useRouter } from 'vue-router'
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormField from '../../components/ui/FormField.vue'
import SectionCard from '../../components/ui/SectionCard.vue'
import StepperShell from '../../components/ui/StepperShell.vue'
import ExamPreview from '../../components/ui/ExamPreview.vue'
import useForm from '../../composables/useForm'
import useLeaveGuard from '../../composables/useLeaveGuard'
import { required, maxLength, min, numeric } from '../../composables/validation/rules'
import { toast } from '../../composables/useToast'

/**
 * Конструктор экзамена: мастер из 4 шагов.
 *
 * 1. Основное — название, описание, категория, модуль, курс.
 * 2. Вопросы — лимиты по модулям, перемешивание.
 * 3. Настройки — проходной балл, попытки, окно доступности, назначение.
 * 4. Проверка — чек-лист готовности перед созданием.
 *
 * Шаг живёт в URL (?step=), поэтому переживает перезагрузку и им
 * можно поделиться ссылкой.
 */
const props = defineProps({
  idEdit: { type: [Number, String], default: null },
})

const { t } = useI18n()
const store = useStore()
const route = useRoute()
const router = useRouter()

const isEdit = computed(() => props.idEdit !== null && props.idEdit !== undefined && props.idEdit !== '')

const steps = computed(() => [
  { key: 'basic', title: t('exams.wizard.stepBasic'), subtitle: t('exams.wizard.stepBasicHint') },
  { key: 'questions', title: t('exams.wizard.stepQuestions'), subtitle: t('exams.wizard.stepQuestionsHint') },
  { key: 'settings', title: t('exams.wizard.stepSettings'), subtitle: t('exams.wizard.stepSettingsHint') },
  { key: 'review', title: t('exams.wizard.stepReview'), subtitle: t('exams.wizard.stepReviewHint') },
])

const STEP_KEYS = ['basic', 'questions', 'settings', 'review']

const initialStep = () => {
  const fromQuery = STEP_KEYS.indexOf(String(route.query.step ?? ''))
  return fromQuery > -1 ? fromQuery : 0
}

const activeStep = ref(initialStep())

const categories = computed(() => store.getters['Course/categories'] || [])
const modules = computed(() => store.getters['Course/modules'] || [])
// Курсы наполняет Exam/fetchCoursesByCategory — геттера
// 'Course/courseList' в модуле Course нет, и список «Курс» был пуст.
const courses = computed(() => store.getters['Exam/courseList'] || [])

const previewCategories = computed(() => {
  const id = form.values.category_id
  if (!id) return []
  const cat = categories.value.find((c) => c.id === id)
  return cat ? [cat.title] : []
})

const previewModules = computed(() => {
  const id = form.values.module_id
  if (!id) return []
  const mod = modules.value.find((m) => m.id === id)
  return mod ? [mod.title] : []
})

const headerTitle = computed(() =>
  isEdit.value ? t('exams.editTitle') : t('exams.createTitle')
)

const descCounter = computed(() => ({
  current: (form.values.description || '').length,
  max: 500,
}))

const availableQuestions = computed(() => {
  if (!form.values.category_id) return {}
  const key = `questions_${form.values.category_id}`
  return store.state.Exam.questionCache?.[key] || {}
})

const totalQuestions = computed(() => {
  return Object.values(availableQuestions.value).reduce((sum, n) => sum + n, 0)
})

const perModuleLimits = ref({})

const updatePerModuleLimit = (moduleId, value) => {
  perModuleLimits.value[moduleId] = value === '' ? null : Number(value)
}

const onCategoryChange = async () => {
  form.values.module_id = null
  form.values.course_id = null
  form.values.question_limit = null
  perModuleLimits.value = {}

  if (form.values.category_id) {
    await store.dispatch('Exam/fetchModuleStats', form.values.category_id)
    await store.dispatch('Course/fetchCoursesByCategory', form.values.category_id)
  }
}

const gradeBoundary = computed(() => {
  if (form.values.passing_score === null || form.values.passing_score === '') return null
  const boundaries = store.getters['GradeBoundary/boundaries'] || []
  return boundaries.find((b) => form.values.passing_score >= b.boundary) || null
})

const gradeColor = computed(() => {
  if (!gradeBoundary.value) return 'primary'
  const grade = gradeBoundary.value.grade
  if (grade >= 4) return 'success'
  if (grade >= 3) return 'warning'
  return 'error'
})

const gradePreview = computed(() => {
  if (!gradeBoundary.value) return '—'
  return gradeBoundary.value.grade
})

const form = useForm({
  schema: {
    title: { initial: '', rules: [required(t('exams.errors.titleRequired')), maxLength(255, t('exams.errors.titleTooLong'))] },
    description: { initial: '', rules: [maxLength(500, t('exams.errors.descTooLong'))] },
    category_id: { initial: null, rules: [required(t('exams.errors.categoryRequired'))] },
    module_id: { initial: null, rules: [] },
    course_id: { initial: null, rules: [] },
    question_limit: { initial: 0, rules: [min(0, t('exams.errors.limitInvalid')), numeric()] },
    passing_score: { initial: 0.5, rules: [required(t('exams.errors.passingScoreRequired')), min(0, t('exams.errors.passingScoreInvalid')), numeric()] },
    max_attempts: { initial: 3, rules: [required(t('exams.errors.attemptsRequired')), min(1, t('exams.errors.attemptsInvalid')), numeric()] },
    shuffle: { initial: false, rules: [] },
    opens_at: { initial: null, rules: [] },
    closes_at: { initial: null, rules: [] },
    due_at: { initial: null, rules: [] },
    group_id: { initial: null, rules: [] },
    user_id: { initial: null, rules: [] },
  },

  draftKey: isEdit.value ? `exam.edit.${props.idEdit}` : 'exam.create',

  onSubmit: async ({ values }) => {
    const payload = {
      title: values.title.trim(),
      description: values.description?.trim() || '',
      category_id: values.category_id,
      module_id: values.module_id,
      course_id: values.course_id,
      question_limit: values.question_limit === '' || values.question_limit === null ? null : Number(values.question_limit),
      passing_score: values.passing_score === '' ? null : Number(values.passing_score),
      max_attempts: values.max_attempts === '' ? null : Number(values.max_attempts),
      shuffle: Boolean(values.shuffle),
      opens_at: values.opens_at || null,
      closes_at: values.closes_at || null,
      due_at: values.due_at || null,
      group_id: values.group_id === '' ? null : values.group_id,
      user_id: values.user_id === '' ? null : values.user_id,
    }

    if (isEdit.value) {
      await store.dispatch('Exam/updateExam', { id: props.idEdit, data: payload })
      toast.success(t('exams.saved'))
    } else {
      await store.dispatch('Exam/createExam', payload)
      toast.success(t('exams.created'))
    }

    await router.back()
  },
})

const { confirmLeave } = useLeaveGuard(() => form.dirty.value, { message: t('common.leaveConfirm') })

const STEP_FIELDS = {
  basic: ['title', 'description', 'category_id'],
  questions: [],
  settings: ['passing_score', 'max_attempts'],
  review: [],
}

const beforeNext = async () => {
  const key = STEP_KEYS[activeStep.value]
  const fields = STEP_FIELDS[key] || []

  for (const name of fields) {
    const ok = await form.validateField(name)
    if (!ok) {
      form.scrollToFirstError()
      return false
    }
  }

  return true
}

const onStepChange = (key) => {
  if (!key) return
  router.replace({ query: { ...route.query, step: key } }).catch(() => {})
}

const finish = () => {
  form.submit()
}

const readiness = computed(() => {
  const filled = reviewItems.value.filter((item) => item.done).length
  const total = reviewItems.value.length

  if (filled === total) return { text: t('exams.wizard.ready'), color: 'success' }
  if (filled === 0) return { text: t('exams.wizard.readinessEmpty'), color: 'warning' }

  return { text: t('exams.wizard.readinessPartial', { filled, total }), color: 'warning' }
})

const reviewItems = computed(() => [
  {
    key: 'title',
    done: Boolean(form.values.title?.trim()),
    label: t('exams.wizard.checkTitle'),
  },
  {
    key: 'category',
    done: form.values.category_id !== null && form.values.category_id !== '',
    label: t('exams.wizard.checkCategory'),
  },
  {
    key: 'module',
    done: form.values.module_id !== null && form.values.module_id !== '',
    label: t('exams.wizard.checkModule'),
  },
  {
    key: 'questions',
    done: totalQuestions.value > 0,
    label: t('exams.wizard.checkQuestions'),
    value: totalQuestions.value ? `${totalQuestions.value} ${t('exams.wizard.questionsCount')}` : null,
  },
  {
    key: 'passing',
    done: form.values.passing_score !== null && form.values.passing_score !== '',
    label: t('exams.wizard.checkPassingScore'),
  },
  {
    key: 'attempts',
    done: form.values.max_attempts !== null && form.values.max_attempts !== '',
    label: t('exams.wizard.checkAttempts'),
  },
])

const reviewWarning = computed(() => {
  if (!form.values.category_id) return t('exams.wizard.reviewNoCategory')
  if (!form.values.module_id) return t('exams.wizard.reviewNoModule')
  if (totalQuestions.value === 0) return t('exams.wizard.reviewNoQuestions')
  return null
})

const selectedGroup = computed(() => {
  if (!form.values.group_id) return null
  return groups.value.find((g) => g.id === form.values.group_id)?.groupname || null
})

const selectedUser = computed(() => {
  if (!form.values.user_id) return null
  return users.value.find((u) => u.id === form.values.user_id)?.fio || null
})

const groups = ref([])
const users = ref([])

onMounted(async () => {
  // Справочники фильтров живут там, где они и описаны: темы/модули —
  // в Course (fetchModules → GET /api/aukstructure), курсы по
  // специальности — в Exam (fetchCoursesByCategory). Раньше стояли
  // Course/fetchModules и Course/fetchCourseList — таких actions в
  // модуле нет, dispatch возвращал undefined, и .catch() на нём ронял
  // onMounted целиком: справочники не грузились вовсе.
  await Promise.all([
    store.dispatch('Course/fetchCategories').catch(() => {}),
    store.dispatch('Course/fetchModules').catch(() => {}),
  ])

  if (isEdit.value) {
    await store.dispatch('Exam/fetchExam', props.idEdit).catch(() => {})

    const exam = store.state.Exam.exam || {}
    form.reset({
      title: exam.title ?? '',
      description: exam.description ?? '',
      category_id: exam.category_id ?? null,
      module_id: exam.module_id ?? null,
      course_id: exam.course_id ?? null,
      question_limit: exam.question_limit ?? 0,
      passing_score: exam.passing_score ?? 0.5,
      max_attempts: exam.max_attempts ?? 3,
      shuffle: exam.shuffle ?? false,
      opens_at: exam.opens_at ? new Date(exam.opens_at).toISOString().slice(0, 16) : null,
      closes_at: exam.closes_at ? new Date(exam.closes_at).toISOString().slice(0, 16) : null,
      due_at: exam.due_at ? new Date(exam.due_at).toISOString().slice(0, 16) : null,
      group_id: exam.group_id ?? null,
      user_id: exam.user_id ?? null,
    })

    if (exam.category_id) {
      await Promise.all([
        store.dispatch('Exam/fetchModuleStats', exam.category_id).catch(() => {}),
        store.dispatch('Exam/fetchCoursesByCategory', exam.category_id).catch(() => {}),
      ])
    }
  }

  await loadAssignmentOptions()
})

const loadAssignmentOptions = async () => {
  try {
    // /api/v1/users, а не /api/users: последнего маршрута на бэкенде
    // нет, и его 404 уводил всё приложение на страницу 404.
    const [groupsRes, usersRes] = await Promise.all([
      $api.get('/api/groups', { optional: true }),
      $api.get('/api/v1/users', { params: { role: 'trainee' }, optional: true }),
    ])
    groups.value = unwrapResponse(groupsRes) ?? []
    users.value = unwrapResponse(usersRes) ?? []
  } catch (e) {
    console.warn('[ExamWizard] loadAssignmentOptions:', e?.message)
  }
}

watch(
  () => route.query.step,
  (value) => {
    const index = STEP_KEYS.indexOf(String(value ?? ''))
    if (index > -1 && index !== activeStep.value) activeStep.value = index
  }
)

/*
 * Смена специальности перезагружает её справочники: статистику
 * вопросов и список курсов. Без этого при создании нового экзамена
 * списки «Тема (модуль)» и «Курс» оставались пустыми — их наполнял
 * только путь редактирования, где category_id приходит из сервера.
 */
watch(
  () => form.values.category_id,
  async (categoryId) => {
    form.values.module_id = null
    form.values.course_id = null

    if (!categoryId) {
      perModuleLimits.value = {}
      return
    }

    await Promise.all([
      store.dispatch('Exam/fetchModuleStats', categoryId).catch(() => {}),
      store.dispatch('Exam/fetchCoursesByCategory', categoryId).catch(() => {}),
    ])
  }
)

defineExpose({ form, submitForm: finish })
</script>

<style scoped>
.u-wizard {
  display: grid;
  gap: var(--sp-6);
  align-items: start;
}

@media (min-width: 960px) {
  .u-wizard {
    grid-template-columns: minmax(0, 1fr) 340px;
  }
}

.u-wizard__aside {
  position: sticky;
  top: var(--sp-4);
}

.exams-wizard__module-row {
  padding: var(--sp-3);
  background: var(--c-surface-2);
  border-radius: var(--radius-md);
}

.exams-wizard__module-title {
  font-weight: 600;
  font-size: var(--fs-md);
}

.exams-wizard__limits .v-field {
  max-width: 200px;
}

.exams-wizard__grade-hint {
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}

.u-wizard__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: var(--sp-2);
}

.u-wizard__list-item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2) var(--sp-3);
  border-radius: var(--radius-sm);
  background: var(--c-surface-2);
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
}

.u-wizard__list-item.is-done {
  color: var(--c-text);
}

.u-wizard__hint {
  margin: var(--sp-2) 0 0;
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}

.u-draft-hint {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  margin-top: var(--sp-3);
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}

.u-section__sub-title {
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--c-text);
}
</style>
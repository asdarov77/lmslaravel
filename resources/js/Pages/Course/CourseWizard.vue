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
      <template #main>
        <div class="u-wizard">
          <div>
            <FormField
              :label="t('course.labelPolnoe-nazvanie-kursa')"
              name="title"
              :error="form.errors.title"
              :required="form.isRequired('title')"
            >
              <v-text-field v-bind="form.bind('title')" data-test="course-title" />
            </FormField>

            <FormField
              :label="t('course.labelKratkoe-nazvanie-kursa')"
              name="short_description"
              :error="form.errors.short_description"
              :counter="shortCounter"
            >
              <v-text-field v-bind="form.bind('short_description')" />
            </FormField>

            <FormField
              :label="t('course.labelPolnoe-opisanie-kursa')"
              name="long_description"
              :error="form.errors.long_description"
            >
              <AutosizeTextarea
                :label-id="`field-long_description-label`"
                v-model="form.values.long_description"
                :maxlength="2000"
                :rows="5"
                @blur="form.validateField('long_description')"
              />
            </FormField>

            <FormField
              :label="t('course.labelKategoriya-kursa')"
              name="category_id"
              :error="form.errors.category_id"
              :required="form.isRequired('category_id')"
            >
              <v-select
                v-bind="form.bind('category_id')"
                :items="categories"
                item-value="id"
                item-title="title"
                data-test="course-category"
              />
            </FormField>

            <FormField :label="t('course.labelPut-kursa')" name="path" :error="form.errors.path">
              <v-text-field v-bind="form.bind('path')" placeholder="/courses/bzp" />
            </FormField>

            <FormField :label="t('course.labelDlitelnost')" name="duration" :error="form.errors.duration">
              <v-text-field v-bind="form.bind('duration')" type="number" min="0" suffix="ч" />
            </FormField>
          </div>

          <CoursePreview
            class="u-wizard__aside"
            :label="t('course.wizard.preview')"
            :title="form.values.title"
            :short-description="form.values.short_description"
            :long-description="form.values.long_description"
            :categories="previewCategories"
            :path="form.values.path"
            :duration="form.values.duration"
            :path-label="t('course.labelPut-kursa')"
            :duration-label="t('course.labelDlitelnost')"
            :title-placeholder="t('course.wizard.previewNoTitle')"
            :categories-placeholder="t('course.wizard.previewNoCategories')"
          />
        </div>
      </template>

      <!-- ------------------------------------------------------ Структура -->
      <!-- ------------------------------------------------------ Структура -->
      <template #structure>
        <!--
          Структуру наполняют импорт классов и файловый менеджер, а эта
          форма её не создаёт: FXMLLoader существует CRUD отдельно taken
          узла, а порядок задаёт POST /api/aukstructure/reorder (полный
          список узлов курса в новом порядке). Поэтому здесь список
          с кнопками перемещения и переходы к инструментам наполнения.
        -->
        <div class="u-wizard">
          <div>
            <div class="d-flex align-center mb-2">
              <h3 class="u-section__title">{{ t('course.wizard.structureTitle') }}</h3>
              <v-spacer />
              <v-btn
                v-if="isEdit"
                icon="mdi-refresh"
                size="small"
                variant="text"
                :loading="manifestLoading"
                :aria-label="t('common.refresh')"
                @click="loadManifest"
              />
            </div>

            <AppSkeleton v-if="manifestLoading" variant="text" :count="4" />

            <template v-else-if="isEdit">
              <v-alert
                v-if="!lessons.length"
                type="info"
                variant="tonal"
                density="compact"
                class="mb-3"
                :text="t('course.wizard.structureEmpty')"
              />

              <ul v-else class="u-wizard__list">
                <li v-for="(lesson, index) in lessons" :key="lesson.id" class="u-wizard__list-item">
                  <v-icon icon="mdi-bookmark-outline" size="16" aria-hidden="true" />
                  <span>{{ lesson.title }}</span>
                  <span class="u-wizard__list-spacer"></span>
                  <v-btn
                    icon="mdi-arrow-up"
                    size="x-small"
                    variant="text"
                    :disabled="reordering || index === 0"
                    :aria-label="t('course.wizard.moveUp')"
                    @click="moveLesson(index, -1)"
                  />
                  <v-btn
                    icon="mdi-arrow-down"
                    size="x-small"
                    variant="text"
                    :disabled="reordering || index === lessons.length - 1"
                    :aria-label="t('course.wizard.moveDown')"
                    @click="moveLesson(index, 1)"
                  />
                </li>
              </ul>

              <p v-if="lessons.length" class="u-wizard__hint">
                {{ t('course.wizard.structureCount', { count: lessons.length }) }}
              </p>
            </template>

            <v-alert
              v-else
              type="info"
              variant="tonal"
              density="compact"
              :text="t('course.wizard.structureAfterCreate')"
            />
          </div>

          <SectionCard class="u-wizard__aside" :title="t('course.wizard.structureTools')">
            <v-btn
              block
              variant="tonal"
              color="primary"
              prepend-icon="mdi-import"
              class="mb-2"
              :to="{ name: 'air.store' }"
            >
              {{ t('course.wizard.importClass') }}
            </v-btn>
            <v-btn
              block
              variant="text"
              prepend-icon="mdi-folder-multiple-outline"
              :to="{ name: 'filemanager' }"
            >
              {{ t('course.wizard.fileManager') }}
            </v-btn>
          </SectionCard>
        </div>
      </template>

      <!-- ----------------------------------------------------- Материалы -->
      <template #materials>
        <div class="u-wizard">
          <div>
            <div class="d-flex align-center mb-2">
              <h3 class="u-section__title">{{ t('course.wizard.materialsTitle') }}</h3>
              <v-spacer />
              <v-btn
                v-if="selectedModule"
                color="primary"
                variant="flat"
                size="small"
                prepend-icon="mdi-plus"
                @click="openMaterialForm"
              >
                {{ t('course.wizard.addMaterial') }}
              </v-btn>
            </div>

            <v-select
              v-model="selectedModule"
              :items="modules"
              item-value="id"
              item-title="title"
              :label="t('course.wizard.selectModule')"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              class="mb-4"
            />

            <v-alert
              v-if="!selectedModule"
              type="info"
              variant="tonal"
              density="compact"
              :text="t('course.wizard.selectModuleHint')"
            />

            <div v-else class="materials__list">
              <AppSkeleton v-if="materialsLoading" variant="text" :count="3" />

              <v-alert
                v-else-if="!materials.length"
                type="info"
                variant="tonal"
                density="compact"
                :text="t('course.wizard.materialsEmpty')"
              />

              <ul v-else class="materials__items">
                <li
                  v-for="material in materials"
                  :key="material.id"
                  class="materials__item"
                >
                  <div class="materials__item-main">
                    <div class="materials__item-type">
                      <v-icon :icon="materialIcon(material.type)" :color="materialTypeColor(material.type)" size="20" />
                    </div>
                    <div class="materials__item-info">
                      <h4 class="materials__item-title">{{ material.title }}</h4>
                      <span class="materials__item-type-label">{{ materialTypeLabel(material.type) }}</span>
                    </div>
                  </div>
                  <div class="materials__item-actions">
                    <v-btn
                      variant="text"
                      size="small"
                      prepend-icon="mdi-pencil-outline"
                      @click="editMaterial(material)"
                    >
                      {{ t('common.edit') }}
                    </v-btn>
                    <v-btn
                      variant="text"
                      color="error"
                      size="small"
                      prepend-icon="mdi-delete-outline"
                      @click="confirmDeleteMaterial(material)"
                    >
                      {{ t('common.delete') }}
                    </v-btn>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <!-- Форма создания/редактирования материала -->
          <v-dialog v-model="materialForm.open" max-width="640">
            <v-card>
              <v-card-title>{{ materialForm.editing ? t('course.wizard.editMaterial') : t('course.wizard.addMaterial') }}</v-card-title>
              <v-card-text>
                <FormField
                  :label="t('course.wizard.materialTitle')"
                  name="title"
                  :error="materialForm.errors.title"
                  :required="materialForm.isRequired('title')"
                >
                  <v-text-field v-bind="materialForm.bind('title')" data-test="material-title" />
                </FormField>

                <FormField
                  :label="t('course.wizard.materialType')"
                  name="type"
                  :error="materialForm.errors.type"
                  :required="materialForm.isRequired('type')"
                >
                  <v-select
                    v-bind="materialForm.bind('type')"
                    :items="materialTypes"
                    item-value="value"
                    item-title="label"
                    clearable
                  />
                </FormField>

                <FormField
                  :label="t('course.wizard.materialDescription')"
                  name="description"
                  :error="materialForm.errors.description"
                >
                  <v-textarea
                    v-bind="materialForm.bind('description')"
                    :rows="3"
                    :maxlength="500"
                    density="compact"
                    variant="outlined"
                    hide-details
                  />
                </FormField>

                <FormField
                  :label="t('course.wizard.materialFile')"
                  name="link"
                  :error="materialForm.errors.link"
                >
                  <v-text-field
                    v-bind="materialForm.bind('link')"
                    :placeholder="t('course.wizard.materialFilePlaceholder')"
                    prepend-inner-icon="mdi-link"
                  />
                </FormField>

                <FormField
                  :label="t('course.wizard.materialIdentifier')"
                  name="identifier"
                  :error="materialForm.errors.identifier"
                >
                  <v-text-field
                    v-bind="materialForm.bind('identifier')"
                    :placeholder="t('course.wizard.materialIdentifierPlaceholder')"
                  />
                </FormField>
              </v-card-text>
              <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="closeMaterialForm">
                  {{ t('common.cancel') }}
                </v-btn>
                <v-btn
                  color="primary"
                  variant="flat"
                  :loading="materialForm.saving"
                  @click="saveMaterial"
                >
                  {{ t('common.save') }}
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-dialog>

          <!-- Подтверждение удаления материала -->
          <v-dialog v-model="materialDeleteConfirm" max-width="400">
            <v-card>
              <v-card-title>{{ t('course.wizard.deleteMaterialConfirm') }}</v-card-title>
              <v-card-text>{{ t('course.wizard.deleteMaterialConfirmText', { title: materialToDelete?.title || '' }) }}</v-card-text>
              <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="materialDeleteConfirm = false">
                  {{ t('common.cancel') }}
                </v-btn>
                <v-btn color="error" variant="flat" @click="deleteMaterial">
                  {{ t('common.delete') }}
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-dialog>

          <SectionCard class="u-wizard__aside" :title="t('course.wizard.materialsPreview')">
            <p class="u-wizard__hint mb-0">{{ t('course.wizard.materialsPreviewText') }}</p>
          </SectionCard>
        </div>
      </template>
      <template #publish>
        <div class="u-wizard">
          <div>
            <FormField :label="t('courses.visibility')" name="visible">
              <v-switch
                v-model="form.values.visible"
                color="primary"
                :label="visibleLabel"
                hide-details
                data-test="course-visible"
              />
            </FormField>

            <!--
              Окно расписания: пока курс не открыт или уже закрыт,
              материалы недоступны, независимо от флажка видимости.
              Даты сохраняются в courses.opens_at / closes_at — бэкенд
              принимает их, а раньше значения терялись молча.
            -->
            <div class="u-wizard__schedule">
              <FormField :label="t('courses.opensAt')" name="opens_at">
                <v-text-field
                  v-model="form.values.opens_at"
                  type="date"
                  data-test="course-opens-at"
                />
              </FormField>
              <FormField :label="t('courses.closesAt')" name="closes_at">
                <v-text-field
                  v-model="form.values.closes_at"
                  type="date"
                  data-test="course-closes-at"
                />
              </FormField>
            </div>

            <h3 class="u-section__title mt-4">{{ t('course.wizard.checklist') }}</h3>
            <ul class="u-wizard__list">
              <li
                v-for="item in checklist"
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
              </li>
            </ul>
          </div>

          <SectionCard class="u-wizard__aside" :title="t('course.wizard.publishNote')">
            <p class="u-wizard__hint mb-0">{{ t('course.wizard.publishNoteText') }}</p>
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
import { unwrapResponse, unwrapArray } from '../../api/envelope'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormField from '../../components/ui/FormField.vue'
import FormActions from '../../components/ui/FormActions.vue'
import SectionCard from '../../components/ui/SectionCard.vue'
import AppSkeleton from '../../components/ui/AppSkeleton.vue'
import StepperShell from '../../components/ui/StepperShell.vue'
import CoursePreview from '../../components/ui/CoursePreview.vue'
import AutosizeTextarea from '../../components/ui/fields/AutosizeTextarea.vue'
import useForm from '../../composables/useForm'
import useLeaveGuard from '../../composables/useLeaveGuard'
import { required, maxLength, min, numeric } from '../../composables/validation/rules'
import { toast } from '../../composables/useToast'
import * as courseApi from '../../api/course.api'

/**
 * Конструктор курса: один мастер вместо трёх разрозненных форм.
 *
 * Заменяет RegisterCourse (создание), UpdateCourse (правка) и AddClass
 * (импорт структуры) одним маршрутом с шагами.
 *
 * Что важно:
 *  - шаг в URL (`?step=`), поэтому шаг переживает перезагрузку и им
 *    можно поделиться ссылкой;
 *  - валидация при ПЕРЕХОДЕ между шагами, а не по «Готово»;
 *  - живое превью карточки курса;
 *  - черновик в localStorage и подтверждение ухода.
 *
 * Границы возможного: бэкенд не имеет эндпоинта правки дерева уроков,
 * поэтому шаг «Структура» показывает реальное содержимое и ведёт в
 * инструменты импорта, а не изображает несуществующий редактор.
 */
const props = defineProps({
  /** undefined — создание, иначе id редактируемого курса. */
  idEdit: { type: [Number, String], default: null },
})

const { t } = useI18n()
const store = useStore()
const route = useRoute()
const router = useRouter()

const isEdit = computed(() => props.idEdit !== null && props.idEdit !== undefined && props.idEdit !== '')

const steps = computed(() => [
  { key: 'main', title: t('course.wizard.stepMain'), subtitle: t('course.wizard.stepMainHint') },
  { key: 'structure', title: t('course.wizard.stepStructure'), subtitle: t('course.wizard.stepStructureHint') },
  { key: 'materials', title: t('course.wizard.stepMaterials'), subtitle: t('course.wizard.stepMaterialsHint') },
  { key: 'publish', title: t('course.wizard.stepPublish'), subtitle: t('course.wizard.stepPublishHint') },
])

const STEP_KEYS = ['main', 'structure', 'materials', 'publish']

/** Шаг живёт в URL: иначе перезагрузка возвращала бы на первый шаг. */
const initialStep = () => {
  const fromQuery = STEP_KEYS.indexOf(String(route.query.step ?? ''))

  return fromQuery > -1 ? fromQuery : 0
}

const activeStep = ref(initialStep())

const lessons = ref([])
const manifestLoading = ref(false)
const reordering = ref(false)

/**
 * Пересылка урока на шаг «Структура»: локальный перестановка —
 * оптимистичный, порядок отправляется целиком (конвенция reorder:
 * «прислал всё»); провал откатывает перестановку.
 */
const moveLesson = async (index, delta) => {
  const target = index + delta
  if (target < 0 || target >= lessons.value.length) return

  const next = [...lessons.value]
  ;[next[index], next[target]] = [next[target], next[index]]
  const before = lessons.value
  lessons.value = next

  reordering.value = true
  try {
    await $api.post('/api/aukstructure/reorder', {
      course_id: props.idEdit,
      ids: next.map((lesson) => lesson.id),
    })
  } catch (error) {
    lessons.value = before
    toast.error(error?.response?.data?.error || t('course.wizard.reorderFailed'))
  } finally {
    reordering.value = false
  }
}

// Геттер берём по плоскому ключу 'Course/categories': в этой сборке
// vuex namespaced-геттеры не собираются в store.getters.Course.
const categories = computed(() => store.getters['Course/categories'] || [])

const groups = computed(() => store.getters['User/groups'] || [])

const previewCategories = computed(() => {
  const id = form.values.category_id
  if (id === null || id === undefined || id === '') return []

  const category = categories.value.find((item) => item.id === id)

  return category ? [category.title] : []
})

const headerTitle = computed(() =>
  isEdit.value ? t('courses.editTitle') : t('courses.addTitle')
)

const shortCounter = computed(() => ({
  current: (form.values.short_description || '').length,
  max: 255,
}))

const visibleLabel = computed(() =>
  form.values.visible ? t('courses.showLabel', { value: t('common.yes') }) : t('courses.showLabel', { value: t('common.no') })
)

const form = useForm({
  schema: {
    title: { initial: '', rules: [required(t('course.errors.titleRequired')), maxLength(255, t('course.errors.titleTooLong'))] },
    short_description: { initial: '', rules: [maxLength(255, t('course.errors.shortTooLong'))] },
    long_description: { initial: '', rules: [maxLength(2000, t('course.errors.longTooLong'))] },
    // Одна категория, а не мультивыбор: бэкенд принимает
    // category_id как integer и делает categories()->sync([id]).
    // Список категорий при мультивыборе уходил массивом и давал 422
    // «The category id must be an integer».
    category_id: { initial: null, rules: [required(t('course.errors.categoriesRequired'))] },
    path: { initial: '', rules: [maxLength(255, t('course.errors.pathTooLong'))] },
    duration: { initial: '', rules: [min(0, t('course.errors.durationInvalid'))] },
    visible: { initial: true, rules: [] },
    opens_at: { initial: '', rules: [] },
    closes_at: { initial: '', rules: [] },
  },

  draftKey: isEdit.value ? `course.edit.${props.idEdit}` : 'course.create',

  onSubmit: async ({ values }) => {
    const payload = {
      title: values.title.trim(),
      short_description: values.short_description.trim(),
      long_description: values.long_description.trim(),
      path: values.path.trim(),
      category_id: values.category_id === '' ? null : values.category_id,
      duration: values.duration === '' ? null : Number(values.duration),
      visible: Boolean(values.visible),
      opens_at: values.opens_at || null,
      closes_at: values.closes_at || null,
    }

    if (isEdit.value) {
      await store.dispatch('Course/updateCourse', { id: props.idEdit, data: payload })
      toast.success(t('courses.saved'))
    } else {
      await store.dispatch('Course/createCourse', payload)
      toast.success(t('courses.created'))
    }

    await router.back()
  },
})

const { confirmLeave } = useLeaveGuard(() => form.dirty.value, { message: t('common.leaveConfirm') })

/**
 * Валидация при переходе между шагами.
 *
 * Проверяются ТОЛЬКО поля текущего шага: правило «категория выбрана»
 * не должно блокировать просмотр структуры, пока пользователь просто
 * туда заглянул.
 */
const STEP_FIELDS = {
  main: ['title', 'short_description', 'long_description', 'category_id', 'path', 'duration'],
  structure: [],
  publish: [],
}

const beforeNext = async () => {
  const key = STEP_KEYS[activeStep.value]
  const fields = STEP_FIELDS[key] || []

  for (const name of fields) {
    // eslint-disable-next-line no-await-in-loop
    const ok = await form.validateField(name)
    if (!ok) {
      form.scrollToFirstError()

      return false
    }
  }

  return true
}

/**
 * Шаг отражаем в URL.
 *
 * StepperShell отдаёт здесь КЛЮЧ шага, а не индекс (emit('step-change',
 * key, index)), поэтому принимаем первый аргумент как строку — иначе
 * в ?step= попадало undefined, и адрес не менялся.
 */
const onStepChange = (key) => {
  if (!key) return

  router.replace({ query: { ...route.query, step: key } }).catch(() => {})
}

const finish = () => {
  // «Готово» проверяет всю форму, а не только последний шаг.
  form.submit()
}

const readiness = computed(() => {
  const filled = checklist.value.filter((item) => item.done).length
  const total = checklist.value.length

  if (filled === total) return { text: t('course.wizard.ready'), color: 'success' }
  if (filled === 0) return { text: t('course.wizard.readinessEmpty'), color: 'warning' }

  return { text: t('course.wizard.readinessPartial', { filled, total }), color: 'warning' }
})

const checklist = computed(() => [
  {
    key: 'title',
    done: Boolean(form.values.title?.trim()),
    label: t('course.wizard.checkTitle'),
  },
  {
    key: 'description',
    done: Boolean(form.values.long_description?.trim()),
    label: t('course.wizard.checkDescription'),
  },
  {
    key: 'categories',
    done: form.values.category_id !== null && form.values.category_id !== '',
    label: t('course.wizard.checkCategories'),
  },
  {
    key: 'structure',
    done: lessons.value.length > 0,
    label: t('course.wizard.checkStructure'),
  },
])

/** Структуру курса показываем только у сохранённого курса. */
const loadManifest = async () => {
  if (!isEdit.value) return

  manifestLoading.value = true
  try {
    const response = await $api.get(`/api/coursemanifest/${props.idEdit}`)
    const manifest = unwrapResponse(response) || {}

    lessons.value = (manifest.aukstructures || []).map((item) => ({ id: item.id, title: item.title }))
  } catch (error) {
    lessons.value = []
  } finally {
    manifestLoading.value = false
  }
}

onMounted(async () => {
  // Категории нужны и превью, и полю выбора; на странице правки их могло
  // не быть вовсе — тогда категория выглядела пустой при заполненной.
  store.dispatch('Course/fetchCategories').catch(() => {})

  if (isEdit.value) {
    await store.dispatch('Course/fetchCourse', props.idEdit).catch(() => {})

    const course = store.state.Course.course || {}
    // Снимаем dirty после загрузки: загруженные данные — не правка.
    form.reset({
      title: course.title ?? '',
      short_description: course.short_description ?? '',
      long_description: course.long_description ?? '',
      category_id: normaliseCategoryId(course.category_id),
      path: course.path ?? '',
      duration: course.duration ?? '',
      visible: course.visible === undefined ? true : Boolean(course.visible),
      opens_at: course.opens_at ?? '',
      closes_at: course.closes_at ?? '',
      // Дата в ISO может идти с временем — в поле date достаточно YYYY-MM-DD.
    })
    form.values.opens_at = String(form.values.opens_at ?? '').slice(0, 10)
    form.values.closes_at = String(form.values.closes_at ?? '').slice(0, 10)

    await loadManifest()
  }
})

/**
 * Категория приходит и числом, и строкой, и списком (исторически
 * форма была мультивыбором). Бэкенд ждёт одно целое — приводим к нему.
 */
function normaliseCategoryId(value) {
  if (Array.isArray(value)) return value.length ? value[0] : null
  if (value === null || value === undefined || value === '') return null
  if (typeof value === 'string') {
    const parsed = Number(value.trim())

    return Number.isInteger(parsed) ? parsed : null
  }

  return typeof value === 'object' ? normaliseCategoryId(value.id) : value
}

/**
 * Materials step data & methods.
 */
const materialsLoading = ref(false)
const materials = ref([])
const selectedModule = ref(null)
const materialForm = useForm({
  schema: {
    title: { initial: '', rules: [required(t('course.errors.titleRequired')), maxLength(255, t('course.errors.titleTooLong'))] },
    type: { initial: null, rules: [required(t('course.wizard.materialTypeRequired')), min(1, t('course.wizard.materialTypeInvalid')), numeric()] },
    description: { initial: '', rules: [maxLength(500, t('course.wizard.materialDescTooLong'))] },
    link: { initial: '', rules: [] },
    identifier: { initial: '', rules: [maxLength(100, t('course.wizard.materialIdentifierTooLong'))] },
  },
  onSubmit: async () => {
    await saveMaterial()
  },
})

const materialToDelete = ref(null)
const materialDeleteConfirm = ref(false)

const materialTypes = computed(() => [
  { value: 1, label: t('course.wizard.materialTypeText') },
  { value: 2, label: t('course.wizard.materialTypeVideo') },
  { value: 3, label: t('course.wizard.materialTypeFile') },
  { value: 4, label: t('course.wizard.materialTypeTest') },
])

const materialIcon = (type) => {
  const icons = {
    1: 'mdi-file-document-outline',
    2: 'mdi-video-outline',
    3: 'mdi-file-outline',
    4: 'mdi-clipboard-check-outline',
  }
  return icons[type] || 'mdi-file-outline'
}

const materialTypeColor = (type) => {
  const colors = {
    1: 'primary',
    2: 'secondary',
    3: 'warning',
    4: 'success',
  }
  return colors[type] || 'primary'
}

const materialTypeLabel = (type) => {
  const typeObj = materialTypes.value.find(t => t.value === type)
  return typeObj ? typeObj.label : t('common.unknown')
}

const loadMaterials = async () => {
  if (!selectedModule.value) {
    materials.value = []
    return
  }

  materialsLoading.value = true
  try {
    const response = await courseApi.fetchAukstructures({ course_id: props.idEdit, parent_id: selectedModule.value })
    materials.value = unwrapArray(response) ?? []
  } catch (error) {
    materials.value = []
  } finally {
    materialsLoading.value = false
  }
}

const openMaterialForm = (material = null) => {
  if (material) {
    materialForm.reset({
      title: material.title,
      type: material.type,
      description: material.description || '',
      link: material.link || '',
      identifier: material.identifier || '',
    })
    materialForm.editing = material.id
  } else {
    materialForm.reset({
      title: '',
      type: 1,
      description: '',
      link: '',
      identifier: '',
    })
    materialForm.editing = null
  }
  materialForm.open = true
}

const closeMaterialForm = () => {
  materialForm.open = false
  materialForm.editing = null
}

const saveMaterial = async () => {
  if (materialForm.saving.value) return

  const ok = await materialForm.validateAll()
  if (!ok) return

  materialForm.saving.value = true

  try {
    const payload = {
      course_id: Number(props.idEdit),
      parent_id: selectedModule.value,
      title: materialForm.values.title.trim(),
      type: Number(materialForm.values.type),
      description: materialForm.values.description.trim(),
      link: materialForm.values.link.trim(),
      identifier: materialForm.values.identifier.trim(),
    }

    if (materialForm.editing) {
      await courseApi.updateAukstructure(materialForm.editing, payload)
      toast.success(t('course.wizard.materialUpdated'))
    } else {
      await courseApi.createAukstructure(payload)
      toast.success(t('course.wizard.materialCreated'))
    }

    closeMaterialForm()
    await loadMaterials()
  } catch (error) {
    toast.error(error?.response?.data?.message || t('course.wizard.materialSaveError'))
  } finally {
    materialForm.saving.value = false
  }
}

const confirmDeleteMaterial = (material) => {
  materialToDelete.value = material
  materialDeleteConfirm.value = true
}

const deleteMaterial = async () => {
  if (!materialToDelete.value) return

  try {
    await courseApi.deleteAukstructure(materialToDelete.value.id)
    toast.success(t('course.wizard.materialDeleted'))
    materialDeleteConfirm.value = false
    materialToDelete.value = null
    await loadMaterials()
  } catch (error) {
    toast.error(error?.response?.data?.message || t('course.wizard.materialDeleteError'))
  }
}

watch(selectedModule, () => {
  loadMaterials()
})

// Шаг из query меняем и при возврате «назад» по истории.
watch(
  () => route.query.step,
  (value) => {
    const index = STEP_KEYS.indexOf(String(value ?? ''))
    if (index > -1 && index !== activeStep.value) activeStep.value = index
  }
)

defineExpose({ form, submitForm: finish, lessons, checklist })
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

.u-wizard__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: var(--sp-1);
}

.u-wizard__list-spacer {
  flex: 1;
}

.u-wizard__list-item {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
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

</style>

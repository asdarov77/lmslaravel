<template>
  <div class="assignment-form">
    <v-alert
      v-if="form.serverError.value"
      type="error"
      variant="tonal"
      class="mb-4"
      :text="form.serverError.value"
    />

    <FormField
      :label="labelTitle"
      name="title"
      :error="form.errors.title"
      :required="form.isRequired('title')"
    >
      <v-text-field
        v-bind="form.bind('title')"
        :placeholder="placeholderTitle"
        :disabled="form.submitting.value"
        variant="outlined"
        density="compact"
        hide-details
        data-test="assignment-title"
      />
    </FormField>

    <FormField
      :label="labelDescription"
      name="description"
      :error="form.errors.description"
    >
      <v-textarea
        v-bind="form.bind('description')"
        :placeholder="placeholderDescription"
        :rows="4"
        :disabled="form.submitting.value"
        variant="outlined"
        density="compact"
        hide-details
        data-test="assignment-description"
      />
    </FormField>

    <FormField
      :label="labelCourse"
      name="course_id"
      :error="form.errors.course_id"
      :required="form.isRequired('course_id')"
    >
      <RemoteAutocomplete
        v-model="form.values.course_id"
        :options="courseOptions"
        :placeholder="placeholderCourse"
        :disabled="form.submitting.value"
        :clearable="true"
        :search-debounce="150"
      />
    </FormField>

    <FormField
      :label="labelGroup"
      name="group_id"
      :error="form.errors.group_id"
      :required="form.isRequired('group_id')"
    >
      <RemoteAutocomplete
        v-model="form.values.group_id"
        :options="groupOptions"
        :placeholder="placeholderGroup"
        :disabled="form.submitting.value"
        :clearable="true"
        :search-debounce="150"
      />
    </FormField>

    <div class="d-flex ga-4">
      <FormField
        :label="labelDue"
        name="due_at"
        :error="form.errors.due_at"
      >
        <MaskedField
          v-model="form.values.due_at"
          preset="datetime"
          :placeholder="placeholderDue"
          :clearable="true"
          :disabled="form.submitting.value"
        />
      </FormField>

      <FormField
        :label="labelMaxScore"
        name="max_score"
        :error="form.errors.max_score"
        :required="form.isRequired('max_score')"
      >
        <v-text-field
          v-bind="form.bind('max_score')"
          type="number"
          min="1"
          max="100"
          variant="outlined"
          density="compact"
          hide-details
          data-test="assignment-max-score"
        />
      </FormField>
    </div>

    <FormField
      :label="labelPublish"
      name="published_at"
      :error="form.errors.published_at"
    >
      <v-switch
        v-model="form.values.published_at"
        :label="labelPublish"
        color="primary"
        density="compact"
        data-test="assignment-publish"
      />
    </FormField>

    <v-alert
      v-for="(error, index) in form.nonFieldErrors"
      :key="index"
      type="error"
      variant="tonal"
      class="mb-2"
      :text="error"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import $api from '../../api/httpClient'
import { unwrapResponse, rowsOf } from '../../api/envelope'
import { toast } from '../../composables/useToast'
import useForm from '../../composables/useForm'
import FormField from '../ui/FormField.vue'
import MaskedField from '../ui/fields/MaskedField.vue'
import RemoteAutocomplete from '../ui/fields/RemoteAutocomplete.vue'

const props = defineProps({
  /** Данные для редактирования (null = создание) */
  initialData: { type: Object, default: null },
  /** Курсы для выбора */
  courses: { type: Array, default: () => [] },
  /** Группы для выбора */
  groups: { type: Array, default: () => [] },
  /** Максимальный балл по умолчанию */
  defaultMaxScore: { type: Number, default: 5 },
  /** Текст кнопки отправки */
  submitText: { type: String, default: '' },
  /** Текст кнопки отмены */
  cancelText: { type: String, default: '' },
  /** Отключить кнопки */
  disabled: { type: Boolean, default: false },
  /** Режим: create | edit */
  mode: { type: String, default: 'create' },
})

const emit = defineEmits(['submit', 'cancel'])

const { t } = useI18n()
const store = useStore()

const isEdit = computed(() => props.mode === 'edit' || !!props.initialData)

const initialValues = computed(() => {
  const base = {
    title: '',
    description: '',
    course_id: props.courses[0]?.id ?? null,
    group_id: props.groups[0]?.id ?? null,
    due_at: '',
    max_score: props.defaultMaxScore,
    published_at: true,
  }

  if (props.initialData) {
    return {
      ...base,
      title: props.initialData.title ?? '',
      description: props.initialData.description ?? '',
      course_id: props.initialData.course_id ?? base.course_id,
      group_id: props.initialData.group_id ?? base.group_id,
      due_at: props.initialData.due_at ? new Date(props.initialData.due_at).toISOString().slice(0, 16) : '',
      max_score: props.initialData.max_score ?? base.max_score,
      published_at: !!props.initialData.published_at,
    }
  }

  return base
})

const form = useForm({
  schema: {
    title: { initial: initialValues.value.title, rules: [] },
    description: { initial: initialValues.value.description, rules: [] },
    course_id: { initial: initialValues.value.course_id, rules: [] },
    group_id: { initial: initialValues.value.group_id, rules: [] },
    due_at: { initial: initialValues.value.due_at, rules: [] },
    max_score: { initial: initialValues.value.max_score, rules: [] },
    published_at: { initial: initialValues.value.published_at, rules: [] },
  },

  draftKey: `assignment.${props.mode}.${props.initialData?.id ?? 'new'}`,

  async onSubmit({ values }) {
    const errors = []
    const fieldErrors = {}

    if (!values.title?.trim()) {
      fieldErrors.title = t('assignments.errors.titleRequired')
    }
    if (!values.course_id) {
      fieldErrors.course_id = t('assignments.errors.courseRequired')
    }
    if (!values.group_id) {
      fieldErrors.group_id = t('assignments.errors.groupRequired')
    }
    if (!values.max_score || values.max_score < 1) {
      fieldErrors.max_score = t('assignments.errors.maxScoreInvalid')
    }

    if (Object.keys(fieldErrors).length > 0) {
      form.setFieldErrors(fieldErrors)
    }
    if (errors.length > 0) {
      form.setNonFieldErrors(errors)
    }

    if (Object.keys(fieldErrors).length > 0 || errors.length > 0) {
      toast.error(t('assignments.checkForm'))
      return false
    }

    const payload = {
      title: values.title.trim(),
      description: values.description?.trim() || null,
      course_id: values.course_id,
      group_id: values.group_id,
      due_at: values.due_at || null,
      max_score: Number(values.max_score),
      published_at: values.published_at ? new Date().toISOString() : null,
    }

    try {
      await emit('submit', payload)
      toast.success(t('assignments.saved'))
    } catch (error) {
      collectErrors(error)
      toast.error(form.nonFieldErrors[0] || t('assignments.saveFailed'))
      throw error
    }
  },
})

watch(() => props.initialData, (newData) => {
  if (newData) {
    form.reset(initialValues.value)
  }
}, { deep: true })

const courseOptions = computed(() =>
  props.courses.map((c) => ({ value: c.id, label: c.name ?? c.title }))
)

const groupOptions = computed(() =>
  props.groups.map((g) => ({ value: g.id, label: g.name ?? g.groupname }))
)

const collectErrors = (error) => {
  const details = error?.response?.data?.error?.details
  if (details && typeof details === 'object') {
    Object.entries(details).forEach(([field, messages]) => {
      const text = Array.isArray(messages) ? messages.join(' ') : String(messages)
      form.setFieldError(field.replace(/\.\d+$/, ''), text)
    })
    return
  }
  form.setNonFieldError(error?.response?.data?.error?.message || error?.message || t('assignments.saveFailed'))
}

const labelTitle = computed(() => isEdit.value ? t('assignments.editTitle') : t('assignments.createTitle'))
const placeholderTitle = computed(() => t('assignments.fieldTitle'))
const labelDescription = computed(() => t('assignments.fieldDescription'))
const placeholderDescription = computed(() => t('assignments.fieldDescription'))
const labelCourse = computed(() => t('assignments.fieldCourse'))
const placeholderCourse = computed(() => t('assignments.fieldCourse'))
const labelGroup = computed(() => t('assignments.fieldGroup'))
const placeholderGroup = computed(() => t('assignments.fieldGroup'))
const labelDue = computed(() => t('assignments.fieldDue'))
const placeholderDue = computed(() => t('assignments.fieldDue'))
const labelMaxScore = computed(() => t('assignments.fieldMaxScore'))
const labelPublish = computed(() => t('assignments.fieldPublish'))
</script>
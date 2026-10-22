<template>
  <!--
    Обёртка u-page + PageHeader добавлены потому, что страница шла
    «голым фрагментом» без единого заголовка: на /classes не было ни
    одного h1-h3, то есть у страницы не было имени — ни для человека,
    ни для скринридера, ни для якоря «к содержанию» (WCAG 2.2 SC 1.3.1,
    2.4.1). Заодно появился корректный единственный h1.
  -->
  <div class="u-page">
    <PageHeader :title="$t('classes.title')" />

    <v-col xs12 sm8 md4>
    <v-card class="u-card u-card--elevated">
      <v-card-text>
        <v-alert
          v-if="form.serverError"
          type="error"
          variant="tonal"
          class="mb-4"
          :text="form.serverError"
        ></v-alert>
        <v-alert
          v-if="form.draftRestored"
          type="info"
          variant="tonal"
          class="mb-4"
          :text="$t('classes.draftRestored')"
        ></v-alert>

        <v-form ref="form" @submit.prevent="handleSubmit">
          <v-combobox
            v-model="form.values.path"
            :items="tags"
            :label="$t('course.labelVyberite-klass-dlya-dobavleniya')"
            :disabled="form.submitting"
            :rules="[rules.required]"
          ></v-combobox>
          <v-text-field
            :disabled="form.submitting"
            :label="$t('course.labelOpisanie')"
            type="text"
            v-model="form.values.title"
            :rules="[rules.required]"
          ></v-text-field>

          <div>
            <v-progress-linear
              v-if="form.submitting"
              :value="100"
              height="5"
              indeterminate
              color="primary"
            ></v-progress-linear>
          </div>
        </v-form>
      </v-card-text>
      <v-card-actions class="d-flex justify-space-between">
        <!--
          Кнопка только открывает диалог. Раньше она стирала 13 таблиц
          контента одним кликом: промахнуться можно было легко, а
          отменить было нечем.
        -->
        <v-btn @click="confirmClear = true" :disabled="form.submitting" class="mr-auto" color="error">
          {{ $t("classes.clearData") }}
        </v-btn>
        <ButtonGroup
          class="mr-3"
          :busy="form.submitting"
          :submit-text="$t('common.save')"
          :cancel-text="$t('common.cancel')"
          @submit="handleSubmit"
        ></ButtonGroup>
      </v-card-actions>
    </v-card>
    </v-col>

    <ConfirmDialog
      v-model="confirmClear"
      :title="$t('classes.clearTitle')"
      :confirm-text="$t('classes.clearConfirm')"
      :cancel-text="$t('common.cancel')"
      :busy="clearing"
      @cancel="confirmClear = false"
      @confirm="clearDatabase"
    >
      {{ $t("classes.clearText") }}
    </ConfirmDialog>
  </div>
</template>

<script>
/**
 * Приводит значение v-combobox к строке.
 *
 * Vuetify в зависимости от версии отдаёт выбранный элемент строкой,
 * объектом { text, value } либо массивом (мультивыбор). Бэкенд тоже
 * принимает только непустую строку, поэтому пустые значения и не
 * строковые «обёртки» приводим к null и не отправляем запрос вовсе —
 * раньше форма уходила с path: "" и получала 422 без объяснений.
 */
export const toClassPath = (value) => {
  if (typeof value === "string") {
    return value.trim() === "" ? null : value.trim()
  }

  if (Array.isArray(value)) {
    return value.length ? toClassPath(value[0]) : null
  }

  if (value && typeof value === "object") {
    return toClassPath(value.value ?? value.text ?? value.title ?? value.name ?? null)
  }

  return null
}

/**
 * Достаёт человекочитаемый текст ошибки из ответа API.
 *
 * Раньше ошибка 422/409/500 уходила только в console.error, и пользователь
 * видел «ничего не произошло». Теперь текст показывается в v-alert.
 */
export const extractApiError = (error, fallback = "Не удалось выполнить операцию") => {
  const body = error?.response?.data

  // Конверт ApiResponseEnvelope: { success, data, error: { message } }
  const envelopeMessage = unwrapResponse(body) ?? body?.error?.message

  if (typeof envelopeMessage === "string" && envelopeMessage.trim() !== "") {
    return envelopeMessage
  }

  // Ответ валидации Laravel: { message, errors: { field: [msg, ...] } }
  if (body && typeof body === "object" && body.errors && typeof body.errors === "object") {
    const messages = Object.values(body.errors)
      .flat()
      .filter((msg) => typeof msg === "string" && msg.trim() !== "")
    if (messages.length) {
      return messages.join(" ")
    }
  }

  if (typeof body?.message === "string" && body.message.trim() !== "") {
    return body.message
  }

  if (error?.message && !/Network Error|timeout/i.test(error.message)) {
    return error.message
  }

  return fallback
}

import { unwrapResponse } from '../../api/envelope'
</script>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

import ButtonGroup from '../../components/ButtonGroup.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import $api from '../../api/httpClient'
import { unwrapArray, unwrapResponse as unwrapResponseApi } from '../../api/envelope'
import useForm from '../../composables/useForm'

const store = useStore()
const router = useRouter()
const { t } = useI18n()

// Vuex computed
const allTags = ref([])
const tags = ref([])
const auks = ref([])
const confirmClear = ref(false)
const clearing = ref(false)
const successMessage = ref("")

const rules = {
  required: (value) => {
    const normalized = toClassPath(value)
    return (normalized !== null && normalized !== "") || "Поле обязательно для заполнения"
  },
}

// useForm для полей импорта класса
const form = useForm({
  fields: ["path", "title"],
  initialValues: { path: "", title: "" },
  draftKey: "classes-add",
  draftDelay: 600,
  validate: (values) => {
    const errors = {}
    if (!toClassPath(values.path)) {
      errors.path = "Выберите класс для добавления"
    }
    if (!values.title?.trim()) {
      errors.title = "Укажите описание класса"
    }
    return Object.keys(errors).length ? errors : true
  },
  onSubmit: async ({ values, form: api }) => {
    const path = toClassPath(values.path)
    const response = await $api.post(
      "/api/classes",
      { title: values.title.trim(), path },
      {
        onUploadProgress: (progressEvent) => {
          api.progress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
        },
      }
    )

    const payload = unwrapResponseApi(response)
    const summary = response?.data?.meta ?? {}
    const imported = Array.isArray(summary.auk)
      ? summary.auk
      : Array.isArray(summary.auks)
        ? summary.auks
        : Array.isArray(payload?.auks)
          ? payload.auks
          : []
    const parsed = Number.isFinite(Number(summary.gift_files_parsed))
      ? Number(summary.gift_files_parsed)
      : null

    return { imported, parsed, path, title: values.title.trim() }
  },
})

const filteredTags = computed(() => {
  const search = toClassPath(form.values.path)

  if (search === null) {
    return allTags.value
  }

  return allTags.value.filter((tag) => tag.toLowerCase().includes(search.toLowerCase()))
})

const canSubmit = computed(() => {
  return toClassPath(form.values.path) !== null && (form.values.title?.trim() ?? "") !== ""
})

/** Загружает список каталогов-классов с диска. */
const loadTags = async () => {
  try {
    const response = await $api.get("/api/classesfs")
    allTags.value = unwrapArray(response)
    tags.value = allTags.value
  } catch (error) {
    form.serverError = extractApiError(error, "Не удалось загрузить список классов")
  }
}

/** Основной обработчик кнопки «Сохранить» (ButtonGroup @submitForm). */
const handleSubmit = async () => {
  successMessage.value = ""
  form.serverError = null

  // Валидация перед сабмитом — useForm сделает повторно внутри submit(),
  // но мы хотим ранний возврат без лишних тиков.
  const path = toClassPath(form.values.path)
  if (path === null) {
    form.serverError = "Выберите класс для добавления"
    return false
  }
  if (!form.values.title?.trim()) {
    form.serverError = "Укажите описание класса"
    return false
  }

  const result = await form.submit()

  if (result) {
    const { imported, parsed, path: importedPath } = result
    // Fallback если imported не приšlo
    const importedAuks = Array.isArray(imported) ? imported : []
    auks.value = importedAuks
    tags.value = tags.value.filter((tag) => tag !== importedPath)
    successMessage.value = parsed === null
      ? `Класс «${importedPath}» импортирован. Загружено АУК: ${importedAuks.length}.`
      : `Класс «${importedPath}» импортирован. Загружено АУК: ${importedAuks.length} (распознано документов: ${parsed}).`
    // Clear draft on success — useForm делает это внутри submit(),
    // но values сброшены только если onSubmit вернул true.
    // При успешном импорте чистим поля вручную для следующего класса.
    form.values.title = ""
    form.values.path = ""
    return true
  }
  return false
}

const clearDatabase = async () => {
  // Повторный клик во время запроса игнорируем: truncate необратим,
  // две параллельные очистки бессмысленны.
  if (clearing.value) {
    return false
  }

  form.serverError = ""
  successMessage.value = ""
  clearing.value = true

  try {
    await $api.post("/api/clear-database")
    // После очистки на диске снова доступны все каталоги, поэтому
    // список тегов возвращаем к исходному (раньше оставалась
    // урезанная копия без только что очищенных классов).
    tags.value = allTags.value
    // Диалог закрываем после ответа, а не до: при ошибке он
    // остаётся открытым, чтобы можно было повторить или отменить.
    confirmClear.value = false
    successMessage.value = "База данных очищена"
    await loadTags()
    return true
  } catch (error) {
    form.serverError = extractApiError(error, "Не удалось очистить базу данных")
    return false
  } finally {
    clearing.value = false
  }
}

const cancelBtnHead = () => {
  router.go(-1)
}

onMounted(async () => {
  await loadTags()
})

// Экспозиция для тестов (вместо export default)
defineExpose({
  form,
  tags,
  allTags,
  auks,
  confirmClear,
  clearing,
  successMessage,
  canSubmit,
  filteredTags,
  rules,
  toClassPath,
  extractApiError,
  loadTags,
  handleSubmit,
  clearDatabase,
  cancelBtnHead,
})

// Экспортируем для тестов
export default {
  components: {
    ButtonGroup,
    ConfirmDialog,
    PageHeader,
  },
  name: "AddClass",
}
</script>
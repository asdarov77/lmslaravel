<template>
  <div class="u-page">
    <PageHeader
      :title="isEdit ? t('questions.editTitle', { id: questionId }) : t('gift.editTitle')"
    />

    <div class="u-question">
      <FormCard
        :busy="form.submitting.value"
        :loading="loading"
        :submit-text="t('common.save')"
        :cancel-text="t('common.cancel')"
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

        <FormField
          :label="t('gift.questionText')"
          name="question_text"
          :error="form.errors.question_text"
          :required="form.isRequired('question_text')"
        >
          <AutosizeTextarea
            :label-id="`field-question_text-label`"
            v-model="form.values.question_text"
            :maxlength="1000"
            :rows="3"
            @blur="form.validateField('question_text')"
          />
        </FormField>

        <!--
          Варианты ответа — повторяемое поле: их порядок больше не
          фиксирован, строки можно перетащить и продублировать.
        -->
        <div class="d-flex align-center mb-2">
          <!--
            h2, а не h3: страница вопроса начинается с h1 (PageHeader),
            и h3 сразу после h1 пропускал уровень 2 — оглавление по
            заголовкам получалось рваным (WCAG 2.2 SC 1.3.1).
          -->
          <h2 class="u-section__title">{{ t('questions.answersTitle') }}</h2>
          <v-spacer />
          <span class="u-hint">{{ t('questions.answersHint') }}</span>
        </div>

        <FormField
          :label="t('questions.answersTitle')"
          name="answers"
          :error="form.errors.answers"
          :required="form.isRequired('answers')"
        >
          <!--
            Радио лежат в общей v-radio-group, а не по одному на строку:
            standalone VRadio в этой версии Vuetify сам не эмитит
            update:modelValue (только в группе) — клик по «правильному»
            варианту молча ничего не менял. Группа же дополнительно
            даёт стрелками/пробелом выбор с клавиатуры.
          -->
          <v-radio-group v-model="correctAnswer" hide-details class="mb-2">
            <RepeatableFields
            v-model="form.values.answers"
            :blank="blankAnswer"
            :add-label="t('questions.addAnswer')"
            :drag-label="t('questions.dragAnswer')"
            :remove-label="t('questions.deleteAnswer')"
            :duplicate-label="t('questions.duplicateAnswer')"
            :min="minAnswers"
          >
              <template #default="{ item, index, replace }">
                <div class="u-answer">
                  <v-radio
                    :value="index"
                    :aria-label="t('questions.markCorrect', { n: index + 1 })"
                    color="primary"
                    class="u-answer__radio"
                  />
                <v-text-field
                  :model-value="item.answer"
                  :error="form.errors[`answers.${index}`]"
                  :aria-label="t('questions.answerLabel', { n: index + 1 })"
                  hide-details
                  density="compact"
                  @update:model-value="replace({ ...item, answer: $event })"
                  @blur="validateAnswers"
                />
              </div>
              </template>
            </RepeatableFields>
          </v-radio-group>
        </FormField>
      </FormCard>

      <!--
        Предпросмотр: так вопрос увидит обучаемый на экзамене.
        Без него опечатку в формулировке или «правильный» ответ, помеченный
        не тем radio, замечают только после публикации — в банке вопросов
        для сотен сотрудников это дорого.
      -->
      <SectionCard class="u-question__preview" :title="t('questions.preview')">
        <p class="u-question__preview-text">{{ form.values.question_text || t('questions.previewEmpty') }}</p>

        <ul class="u-question__options">
          <li v-for="(answer, index) in form.values.answers" :key="index" class="u-question__option">
            <span class="u-question__option-mark" aria-hidden="true">{{ String.fromCharCode(65 + index) }}</span>
            <span>{{ answer.answer || t('questions.previewEmpty') }}</span>
          </li>
        </ul>

        <p v-if="form.values.answers.length" class="u-question__preview-hint">
          {{ t('questions.previewCorrect', { n: correctIndex + 1 }) }}
        </p>
      </SectionCard>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import SectionCard from '../../components/ui/SectionCard.vue'
import AutosizeTextarea from '../../components/ui/fields/AutosizeTextarea.vue'
import RepeatableFields from '../../components/ui/fields/RepeatableFields.vue'
import useForm from '../../composables/useForm'
import useLeaveGuard from '../../composables/useLeaveGuard'
import { required, minLength, custom } from '../../composables/validation/rules'
import { toast } from '../../composables/useToast'

/**
 * Форма вопроса: создание и правка в одном компоненте.
 *
 * Заменяет QuestionNew и QuestionEdit. Что исправлено по сути:
 *  - кнопка сохранения появлялась только после @input, а в QuestionNew
 *    этот обработчик остался в закомментированном блоке — вопрос можно
 *    было набрать и не сохранить;
 *  - правка не загружала вопрос: GET /api/questions?id=N отдаёт МАССИВ,
 *    а страница читала (question.answers). Надёжно берём один объект;
 *  - removeAnswer вызывал toast, который в файле не был импортирован,
 *    — удаление верного варианта роняло страницу в ReferenceError;
 *  - ошибки сохранения уходили в console.log, пользователь не понимал,
 *    что ничего не сохранилось;
 *  - addAnswer писал в is_correct ИНДЕКС вместо признака.
 */
const props = defineProps({
  /** undefined — создание. */
  idEdit: { type: [Number, String], default: null },
  category_id: { type: [Number, String], default: null },
  aukstructure_id: { type: [Number, String], default: null },
})

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const loading = ref(false)

const isEdit = computed(() => props.idEdit !== null && props.idEdit !== undefined && props.idEdit !== '')
const questionId = computed(() => props.idEdit ?? route.params.idEdit ?? null)

/** Два варианта — минимум, иначе вопрос не проверяем. */
const minAnswers = 2

const blankAnswer = () => ({ id: null, answer: '', is_correct: false })

const correctIndex = computed(() => form.values.answers.findIndex((answer) => answer.is_correct))

/**
 * Индекс верного варианта — двусторонняя связь с признаками is_correct.
 *
 * Хранить приходится оба вида: бэкенд ждёт is_correct у каждого ответа,
 * а радио-группа оперирует индексом. Раньше правило «нельзя удалить
 * верный вариант» держалось на сравнении индексов вручную, а здесь
 * рассинхрон после удаления строки приводил к сохранению вопроса без
 * правильного ответа.
 */
const correctAnswer = ref(0)

watch(correctAnswer, (index) => {
  form.values.answers = form.values.answers.map((answer, i) => ({ ...answer, is_correct: i === index }))
  // Индекс мог «уехать» после удаления строки — проверяем согласованность.
  validateAnswers()
})

/** Отметка верным по клику (используется в тестах и по клавиатуре). */
const markCorrect = (index) => {
  correctAnswer.value = index
}

const form = useForm({
  schema: {
    question_text: {
      initial: '',
      rules: [required(t('questions.textRequired')), minLength(5, t('questions.textTooShort'))],
    },
    answers: {
      // Первый вариант сразу верный: пустой вопрос без отмеченного
      // ответа не проходит валидацию, и пользователь спотыкается об это
      // до того, как понял, что нужно нажать.
      initial: [{ ...blankAnswer(), is_correct: true }, blankAnswer()],
      rules: [
        custom(
          (value) => (Array.isArray(value) ? value.length >= minAnswers : false),
          t('questions.answersMin')
        ),
        custom((value) => {
          const texts = (value || []).map((answer) => String(answer.answer ?? '').trim().toLowerCase()).filter(Boolean)

          return new Set(texts).size === texts.length
        }, t('questions.answersUnique')),
        custom((value) => (value || []).some((answer) => answer.is_correct), t('questions.answersNoCorrect')),
      ],
    },
  },

  onSubmit: async ({ values }) => {
    const payload = {
      question_text: values.question_text.trim(),
      category_id: props.category_id ?? values.category_id ?? null,
      aukstructure_id: props.aukstructure_id ?? values.aukstructure_id ?? null,
      answers: values.answers.map((answer) => ({
        id: answer.id ?? null,
        answer: answer.answer.trim(),
        is_correct: Boolean(answer.is_correct),
      })),
    }

    if (isEdit.value) {
      await $api.patch(`/api/questions/${questionId.value}`, payload)
      toast.success(t('questions.saved'))
    } else {
      await $api.post('/api/questions/', payload)
      toast.success(t('questions.saved'))
    }

    await router.push({ name: 'questions.main' })
  },
})

const { confirmLeave } = useLeaveGuard(() => form.dirty.value, { message: t('common.leaveConfirm') })

/**
 * Проверка вариантов после правки текста.
 *
 * Нужна отдельно от validateField('answers'): правила смотрят на весь
 * массив (минимальное число, уникальность, наличие верного), а текст
 * меняет список по одному символу, и ошибка должна исчезать сразу, а не
 * после blur.
 */
const validateAnswers = () => form.validateField('answers')

onMounted(async () => {
  if (!isEdit.value) return

  loading.value = true
  try {
    // show() возвращает объект; index() — массив. Именно поэтому здесь
    // не /api/questions?id=N: страница клала в форму массив и падала
    // на вопросе.answers.
    const response = await $api.get(`/api/questions/${questionId.value}`)
    const question = unwrapResponse(response) || {}

    const answers = (question.answers || []).map((answer) => ({
      id: answer.id ?? null,
      answer: answer.answer ?? '',
      is_correct: Boolean(answer.is_correct),
    }))

    form.reset({ question_text: question.question_text ?? '', answers })
    // Сначала данные, потом индекс: watch должен увидеть готовый список.
    correctAnswer.value = Math.max(answers.findIndex((answer) => answer.is_correct), 0)
  } catch (error) {
    toast.error(toast.fromError(error, t('questions.loadFailed')))
  } finally {
    loading.value = false
  }
})

const submitForm = async () => {
  const ok = await form.submit()

  // Клиентская невалидность уже показана под полями; тост нужен только
  // когда запрос ушёл, а сервер его отверг. Раньше ошибка уходила в
  // console.log, и пользователь нажимал «Сохранить» в пустоту.
  if (!ok && form.submitError.value) {
    toast.error(toast.fromError(form.submitError.value, t('questions.saveFailed')))
  }
}

const cancel = async () => {
  if (await confirmLeave()) router.back()
}

defineExpose({ form, submitForm, markCorrect })
</script>

<style scoped>
.u-question {
  display: grid;
  gap: var(--sp-6);
  align-items: start;
}

@media (min-width: 1100px) {
  .u-question {
    grid-template-columns: minmax(0, 1fr) 380px;
  }
}

.u-question__preview {
  position: sticky;
  top: var(--sp-4);
}

.u-answer {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  width: 100%;
}

.u-answer__radio {
  flex: 0 0 auto;
}

.u-hint {
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}

.u-question__preview-text {
  margin: 0 0 var(--sp-3);
  font-size: var(--fs-base);
  color: var(--c-text);
}

.u-question__options {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: var(--sp-2);
}

.u-question__option {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-3);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-md);
  background: var(--c-surface-2);
  font-size: var(--fs-sm);
}

.u-question__option-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  flex: 0 0 auto;
  border-radius: var(--radius-pill);
  background: var(--c-primary-soft);
  color: var(--c-primary);
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
}

.u-question__preview-hint {
  margin: var(--sp-3) 0 0;
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}
</style>

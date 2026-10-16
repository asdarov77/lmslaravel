import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { extractFieldErrors } from '../api/envelope'
import { isEmpty } from './validation/rules'

/**
 * useForm — механика сложных форм.
 *
 * Зачем: до этого каждая форма сама решала, как валидировать, где
 * держать ошибки и когда блокировать submit. В CreateGroup правило
 * «имя занято» зашито в метод validate(), ошибки лежат в ручном
 * `fieldErrors`, а submit проверялся через `if (!this.errors.length)`,
 * при этом массив ошибок нигде не наполнялся. В QuestionNew — тот же
 * сценарий, но по-своему. Из-за этого:
 *   - клиентская и серверная валидация расходились;
 *   - ошибка сервера (422) показывалась общим v-alert, а поле не
 *     подсвечивалось, и пользователь не понимал, что править;
 *   - submit мог уйти дважды;
 *   - уход с несохранённой формы терял данные молча.
 *
 * Конвенция: форма = объект-схема + FormCard + FormField + useForm.
 * Ручные fieldErrors и собственные методы validate() запрещены.
 *
 * Схема:
 *   const form = useForm({
 *     schema: {
 *       name: { initial: '', rules: [required(), maxLength(255)] },
 *       email: { initial: '', rules: [required(), email()] },
 *     },
 *     onSubmit: async ({ values }) => { await store.dispatch(...) },
 *     draftKey: 'group-create',       // включает автосохранение черновика
 *   })
 *
 * В шаблоне:
 *   <FormField :label="..." :error="form.errors.name" :required="form.isRequired('name')">
 *     <v-text-field v-bind="form.bind('name')" />
 *   </FormField>
 */
export function useForm({
  schema = {},
  initial = {},
  onSubmit = null,
  draftKey = null,
  /** Кол-во мс дебаунса автосохранения черновика. */
  draftDelay = 600,
} = {}) {
  const fields = Object.keys(schema)

  const fieldSchema = (name) => schema[name] || {}

  const normalizeRules = (name) => {
    const rules = fieldSchema(name).rules
    if (!rules) return []

    return Array.isArray(rules) ? rules : [rules]
  }

  const initialValue = (name) => {
    if (Object.prototype.hasOwnProperty.call(initial, name)) return initial[name]

    return fieldSchema(name).initial !== undefined ? fieldSchema(name).initial : null
  }

  const values = reactive({})
  fields.forEach((name) => {
    values[name] = initialValue(name)
  })

  const errors = reactive({})
  const touched = reactive({})
  const pending = reactive({})

  const submitting = ref(false)
  const submitted = ref(false)
  const serverError = ref(null)
  /** Исходная ошибка отклонённого onSubmit — нужна вызывающему коду,
   *  чтобы разобрать её по статусу (401/429/5xx), а не по тексту. */
  const submitError = ref(null)
  const draftSavedAt = ref(null)
  const draftRestored = ref(false)

  /** Снимок значений для определения «грязности» формы. */
  const snapshot = () => JSON.stringify(values)
  let baseline = snapshot()

  const dirty = computed(() => snapshot() !== baseline)

  const valid = computed(() => fields.every((name) => !errors[name]))

  const isRequired = (name) => normalizeRules(name).some((rule) => rule && rule.__required === true)

  const setFieldError = (name, message) => {
    errors[name] = message || ''
  }

  const setServerError = (message) => {
    serverError.value = message || null
  }

  const clearServerError = () => {
    serverError.value = null
  }

  /**
   * Валидация одного поля. Правила выполняются по порядку и
   * останавливаются на первой ошибке — не гоняем async-проверку
   * уникальности, если поле и так не заполнено.
   */
  const validateField = async (name) => {
    touched[name] = true
    pending[name] = true

    let message = ''
    for (const rule of normalizeRules(name)) {
      let result = true

      if (typeof rule === 'function') {
        result = await rule(values[name], values)
      } else if (rule && typeof rule.validate === 'function') {
        result = await rule.validate(values[name], values)
      }

      if (result !== true && !isEmpty(result)) {
        message = typeof result === 'string' ? result : rule?.message || 'Некорректное значение'
        break
      }
    }

    errors[name] = message
    pending[name] = false

    return message === ''
  }

  const validate = async () => {
    const results = await Promise.all(fields.map((name) => validateField(name)))
    submitted.value = true

    return results.every(Boolean)
  }

  /** Прокрутка к первому невалидному полю (по порядку схемы). */
  const scrollToFirstError = () => {
    if (typeof document === 'undefined') return
    const name = fields.find((field) => errors[field])
    if (!name) return

    const escaped = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(name) : name
    const el = document.querySelector(`[data-field="${escaped}"]`)
    if (!el) return

    el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    const focusable = el.querySelector('input, textarea, select, [tabindex]')
    if (focusable && typeof focusable.focus === 'function') focusable.focus({ preventScroll: true })
  }

  // ---------------------------------------------------------------- черновик
  const draftStorageKey = draftKey ? `form-draft:${draftKey}` : null
  let draftTimer = null

  const canUseStorage = () => {
    if (typeof window === 'undefined') return false
    try {
      return Boolean(window.localStorage)
    } catch (error) {
      return false
    }
  }

  const clearDraft = () => {
    if (draftTimer) {
      clearTimeout(draftTimer)
      draftTimer = null
    }
    if (!draftStorageKey || !canUseStorage()) return
    try {
      window.localStorage.removeItem(draftStorageKey)
    } catch (error) {
      /* приватный режим — молча продолжаем */
    }
    draftSavedAt.value = null
    draftRestored.value = false
  }

  const saveDraft = () => {
    if (!draftStorageKey || !canUseStorage()) return
    try {
      window.localStorage.setItem(
        draftStorageKey,
        JSON.stringify({ values: { ...values }, savedAt: Date.now() })
      )
      draftSavedAt.value = new Date()
    } catch (error) {
      /* см. clearDraft() */
    }
  }

  const restoreDraft = () => {
    if (!draftStorageKey || !canUseStorage()) return false
    try {
      const raw = window.localStorage.getItem(draftStorageKey)
      if (!raw) return false
      const parsed = JSON.parse(raw)
      const stored = parsed?.values
      if (!stored || typeof stored !== 'object') return false

      let applied = false
      fields.forEach((name) => {
        if (Object.prototype.hasOwnProperty.call(stored, name)) {
          values[name] = stored[name]
          applied = true
        }
      })

      if (applied) {
        draftRestored.value = true
        draftSavedAt.value = parsed.savedAt ? new Date(parsed.savedAt) : null
      }

      return applied
    } catch (error) {
      return false
    }
  }

  // Восстанавливаем ЧЕРНОВИК ДО baseline: иначе восстановленные данные
  // выглядели бы как «уже сохранённые», и dirty была бы false.
  if (draftStorageKey) restoreDraft()
  baseline = snapshot()

  if (draftStorageKey) {
    watch(
      values,
      () => {
        if (draftTimer) clearTimeout(draftTimer)
        draftTimer = setTimeout(saveDraft, draftDelay)
      },
      { deep: true }
    )
  }

  // ---------------------------------------------------------------- submit
  const submit = async () => {
    if (submitting.value) return false
    // Флаг поднимаем СРАЗУ, до async-валидации: иначе второй клик,
    // пришедший за время проверки полей, проходил мимо защиты и форма
    // уходила на сервер дважды.
    submitting.value = true
    serverError.value = null
    submitError.value = null
    submitted.value = true

    const previousBaseline = baseline

    try {
      if (!(await validate())) {
        await nextTick()
        scrollToFirstError()

        return false
      }

      if (!onSubmit) return true

      // Снимаем «грязность» ДО onSubmit, а не после. Почти каждая форма
      // в onSubmit уходит с другой страницы (router.push), а useLeaveGuard
      // видел бы несохранённые изменения, спросил подтверждение и
      // отменил редирект — то есть после успешного сохранения пользователь
      // остался бы на форме.
      baseline = snapshot()
      clearDraft()

      await onSubmit({ values: { ...values }, form: api })

      return true
    } catch (error) {
      // Сохранения не вышло: изменения снова несохранённые. Возвращаем
      // baseline и поднимаем черновик заново — иначе неудачная отправка
      // стирала бы автосохранение и данные пропали бы при закрытии вкладки.
      baseline = previousBaseline
      if (draftStorageKey) saveDraft()

      // Ошибки 422 раскладываем по полям, общий текст — в serverError.
      // Раньше общий v-alert висел отдельно от полей, и было неясно,
      // какое именно поле сервер отверг.
      const { fields: fieldErrors, general } = extractFieldErrors(error)
      Object.entries(fieldErrors).forEach(([name, message]) => {
        errors[name] = message
      })
      serverError.value = general
      submitError.value = error
      await nextTick()
      scrollToFirstError()

      return false
    } finally {
      submitting.value = false
    }
  }

  /** Сброс к исходным значениям (или к переданным). */
  const reset = (nextInitial = null) => {
    fields.forEach((name) => {
      values[name] = nextInitial && Object.prototype.hasOwnProperty.call(nextInitial, name)
        ? nextInitial[name]
        : initialValue(name)
      errors[name] = ''
      touched[name] = false
      pending[name] = false
    })
    submitted.value = false
    serverError.value = null
    submitError.value = null
    clearDraft()
    baseline = snapshot()
  }

  const api = {
    values,
    errors,
    touched,
    pending,
    submitting,
    submitted,
    serverError,
    submitError,
    draftSavedAt,
    draftRestored,
    dirty,
    valid,
    fields,
    isRequired,
    bind,
    setFieldError,
    setServerError,
    clearServerError,
    validateField,
    validate,
    submit,
    reset,
    clearDraft,
    saveDraft,
    restoreDraft,
    scrollToFirstError,
  }

  /**
   * Пропсы для поля: v-model + валидация по blur.
   * Возвращает свежий объект на каждый рендер — значения читаются
   * из реактивных values/errors, поэтому объект остаётся актуальным.
   */
  function bind(name) {
    return {
      modelValue: values[name],
      'onUpdate:modelValue': (value) => {
        values[name] = value
        // Если поле уже «трогали» — ошибку пересчитываем сразу,
        // чтобы текст не висел, когда пользователь начал исправлять.
        if (touched[name]) validateField(name)
      },
      onBlur: () => validateField(name),
      error: Boolean(errors[name]),
      /*
        Доступное имя поля.
        
        Vuetify жёстко вешает на нативный input
        aria-labelledby="{свой-id}-label", а сам элемент с таким id
        рисует ТОЛЬКО если компоненту передан проп label. В наших
        формах подпись рисует FormField, и Vuetify её не знает — ссылка
        вела в пустоту, и скринридер объявлял поле просто «текстовым».
        
        Здесь атрибут переопределяется на id подписи FormField, который
        детерминирован: field-<имя поля>-label. Именно поэтому порядок
        важен — bind() кладёт его последним, а inputAttrs в Vuetify
        мержится после его значений по умолчанию.
      */
      'aria-labelledby': `field-${name}-label`,
    }
  }

  onBeforeUnmount(() => {
    if (draftTimer) clearTimeout(draftTimer)
  })

  return api
}

export default useForm

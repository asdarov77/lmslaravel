// @vitest-environment jsdom
/**
 * useForm — механика форм: значения, валидация (sync/async), серверные
 * 422, dirty-состояние и автосохранение черновика.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import useForm from '../../resources/js/composables/useForm'
import { required, maxLength, minLength } from '../../resources/js/composables/validation/rules'

/** useForm требует setup-контекст (watch/onBeforeUnmount) — монтируем. */
const withForm = (options) => {
  let form = null
  const Comp = defineComponent({
    setup() {
      form = useForm(options)

      return () => h('div')
    },
  })
  const wrapper = mount(Comp)

  return { form, wrapper }
}

const makeServerError = () => ({
  response: {
    data: {
      error: {
        message: 'Проверьте поля',
        details: { name: ['Название уже занято'] },
      },
    },
  },
})

beforeEach(() => {
  localStorage.clear()
})

describe('useForm: значения и dirty', () => {
  it('берёт initial, затем initial из схемы', () => {
    const { form } = withForm({
      schema: { name: { initial: 'schema' }, age: { initial: 1 } },
      initial: { name: 'initial' },
    })
    expect(form.values.name).toBe('initial')
    expect(form.values.age).toBe(1)
  })

  it('dirty становится true при изменении и false после reset', () => {
    const { form } = withForm({ schema: { name: { initial: '' } } })
    expect(form.dirty.value).toBe(false)

    form.values.name = 'x'
    expect(form.dirty.value).toBe(true)

    form.reset()
    expect(form.dirty.value).toBe(false)
  })
})

describe('useForm: валидация', () => {
  it('validateField помечает поле тронутым и кладёт ошибку', async () => {
    const { form } = withForm({
      schema: { name: { initial: '', rules: [required('Нужно'), maxLength(3, 'Длинно')] } },
    })

    expect(await form.validateField('name')).toBe(false)
    expect(form.errors.name).toBe('Нужно')
    expect(form.touched.name).toBe(true)

    form.values.name = 'abcd'
    expect(await form.validateField('name')).toBe(false)
    expect(form.errors.name).toBe('Длинно')

    form.values.name = 'ab'
    expect(await form.validateField('name')).toBe(true)
    expect(form.errors.name).toBe('')
  })

  it('останавливается на первой ошибке и ждёт async-правило', async () => {
    const asyncRule = vi.fn(async (value) => (value === 'busy' ? 'занято' : true))
    const laterRule = vi.fn(() => true)

    const { form } = withForm({
      schema: { name: { initial: '', rules: [minLength(1), asyncRule, laterRule] } },
    })

    form.values.name = 'busy'
    expect(await form.validateField('name')).toBe(false)
    expect(form.errors.name).toBe('занято')
    // Правило после ошибочного не выполняется.
    expect(laterRule).not.toHaveBeenCalled()
  })

  it('isRequired видит правило required', () => {
    const { form } = withForm({
      schema: {
        name: { initial: '', rules: [required()] },
        note: { initial: '', rules: [maxLength(10)] },
      },
    })
    expect(form.isRequired('name')).toBe(true)
    expect(form.isRequired('note')).toBe(false)
  })

  it('validate помечает отправку и заполняет все ошибки', async () => {
    const { form } = withForm({
      schema: {
        a: { initial: '', rules: [required('A')] },
        b: { initial: '', rules: [required('B')] },
      },
    })
    expect(await form.validate()).toBe(false)
    expect(form.submitted.value).toBe(true)
    expect(form.errors).toMatchObject({ a: 'A', b: 'B' })
  })
})

describe('useForm: submit', () => {
  it('не вызывает onSubmit, пока форма невалидна', async () => {
    const onSubmit = vi.fn()
    const { form } = withForm({
      schema: { name: { initial: '', rules: [required('Нужно')] } },
      onSubmit,
    })

    expect(await form.submit()).toBe(false)
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('вызывает onSubmit со снапшотом значений и сбрасывает dirty', async () => {
    const onSubmit = vi.fn(async () => {})
    const { form } = withForm({
      schema: { name: { initial: 'n', rules: [required()] } },
      onSubmit,
    })
    form.values.name = 'Группа'

    expect(await form.submit()).toBe(true)
    expect(onSubmit).toHaveBeenCalledTimes(1)
    expect(onSubmit.mock.calls[0][0].values.name).toBe('Группа')
    expect(form.dirty.value).toBe(false)
    expect(form.submitting.value).toBe(false)
  })

  it('защищает от двойной отправки', async () => {
    let resolve
    const onSubmit = vi.fn(() => new Promise((r) => { resolve = r }))
    const { form } = withForm({
      schema: { name: { initial: 'n', rules: [required()] } },
      onSubmit,
    })

    const first = form.submit()
    const second = form.submit()
    // Даём первой отправке пройти async-валидацию и дойти до onSubmit;
    // вторая за это время должна быть отброшена синхронной блокировкой.
    await new Promise((resolveTick) => setTimeout(resolveTick, 0))
    expect(onSubmit).toHaveBeenCalledTimes(1)

    resolve()
    await Promise.all([first, second])
    expect(onSubmit).toHaveBeenCalledTimes(1)
  })

  it('раскладывает серверные 422 по полям и пишет общий текст', async () => {
    const onSubmit = vi.fn(async () => {
      throw makeServerError()
    })
    const { form } = withForm({
      schema: { name: { initial: 'занят', rules: [required()] } },
      onSubmit,
    })

    expect(await form.submit()).toBe(false)
    expect(form.errors.name).toBe('Название уже занято')
    expect(form.serverError.value).toBe('Проверьте поля')
    expect(form.submitting.value).toBe(false)
  })
})

describe('useForm: черновик', () => {
  it('автосохраняет и восстанавливает значения', async () => {
    vi.useFakeTimers()
    const { form, wrapper } = withForm({
      schema: { name: { initial: '' } },
      draftKey: 'test-draft',
    })

    form.values.name = 'черновик'
    await vi.advanceTimersByTimeAsync(700)

    expect(localStorage.getItem('form-draft:test-draft')).toContain('черновик')
    expect(form.draftSavedAt.value).toBeInstanceOf(Date)

    wrapper.unmount()

    // Повторный вход в ту же форму поднимает сохранённое.
    const second = withForm({ schema: { name: { initial: '' } }, draftKey: 'test-draft' })
    expect(second.form.values.name).toBe('черновик')
    expect(second.form.draftRestored.value).toBe(true)

    vi.useRealTimers()
  })

  it('clearDraft удаляет черновик', async () => {
    vi.useFakeTimers()
    const { form } = withForm({
      schema: { name: { initial: '' } },
      draftKey: 'test-draft-2',
    })
    form.values.name = 'x'
    await vi.advanceTimersByTimeAsync(700)
    expect(localStorage.getItem('form-draft:test-draft-2')).toBeTruthy()

    form.clearDraft()
    expect(localStorage.getItem('form-draft:test-draft-2')).toBeNull()
    expect(form.draftSavedAt.value).toBeNull()

    vi.useRealTimers()
  })

  it('после успешного submit черновик очищается', async () => {
    vi.useFakeTimers()
    const { form } = withForm({
      schema: { name: { initial: 'n', rules: [required()] } },
      draftKey: 'test-draft-3',
      onSubmit: async () => {},
    })
    form.values.name = 'y'
    await vi.advanceTimersByTimeAsync(700)
    expect(localStorage.getItem('form-draft:test-draft-3')).toBeTruthy()

    await form.submit()
    expect(localStorage.getItem('form-draft:test-draft-3')).toBeNull()

    vi.useRealTimers()
  })
})

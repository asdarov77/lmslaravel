// @vitest-environment jsdom
/**
 * Форма вопроса (Фаза 2.3.3): динамические варианты и предпросмотр.
 *
 * Что защищаем — всё это было сломано в QuestionNew/QuestionEdit:
 *  - сохранение вопроса без отмеченного верного варианта не уходит;
 *  - выбор «правильного» радио реально помечает вариант, и такой
 *    вариант всегда ровно один;
 *  - дубль вариантов ловится до запроса;
 *  - is_correct уходит булевым, а не индексом (addAnswer писал индекс);
 *  - при ошибке сервера введённое не теряется;
 *  - правка загружает вопрос: GET по id отдаёт объект, а не массив.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { nextTick } from 'vue'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import QuestionForm from '../../resources/js/Pages/Gift/QuestionForm.vue'
import ru from '../../resources/js/locales/ru.json'
import { toastItems, clear } from '../../resources/js/composables/useToast'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

const mountForm = async (props = {}) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div/>' } },
      { path: '/questions-main', name: 'questions.main', component: { template: '<div/>' } },
    ],
  })
  await router.push('/courses/list')
  await router.isReady()

  const wrapper = mount(QuestionForm, {
    props: { category_id: 1, aukstructure_id: 1, ...props },
    global: {
      plugins: [vuetify, i18n, router],
      stubs: {
        VProgressCircular: { name: 'VProgressCircular', template: '<span />' },
        VTextarea: {
          name: 'VTextarea',
          props: ['modelValue'],
          emits: ['update:modelValue'],
          template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)"></textarea>',
        },
      },
    },
  })
  await flushPromises()

  return { wrapper, router }
}

/**
 * Отметить вариант верным.
 *
 * В jsdom клик по обёртке радио не доходит до модели (обработчик висит
 * на скрытом input), поэтому отмечаем через сам input — ровно то, что
 * делает браузер при клике пользователем.
 */
const setCorrect = async (wrapper, index) => {
  await wrapper.findAll('.u-answer input[type="radio"]')[index].setValue(true)
  await flushPromises()
}

const setAnswers = async (wrapper, texts) => {
  const inputs = wrapper.findAll('.u-answer input[type="text"]')
  for (let i = 0; i < Math.min(texts.length, inputs.length); i += 1) {
    await inputs[i].setValue(texts[i])
  }
  await nextTick()
}

const addRow = async (wrapper) => {
  // Кнопка добавления приходит из RepeatableFields с подписью
  // questions.addAnswer («Добавить ответ»), а не «Добавить строку».
  const btn = wrapper.findAll('button').find((b) => b.text().includes('Добавить'))
  await btn.trigger('click')
  await flushPromises()
}

const fillQuestion = async (wrapper, text = 'Текст вопроса') => {
  wrapper.vm.form.values.question_text = text
  await nextTick()
}

beforeEach(() => {
  vi.clearAllMocks()
  clear()
  http.get.mockResolvedValue({ data: { success: true, data: { question_text: 'Вопрос', answers: [] } } })
})

describe('QuestionForm: варианты ответа', () => {
  it('стартует с двух вариантов, первый верный, и кнопка сохранения видна сразу', async () => {
    const { wrapper } = await mountForm()

    expect(wrapper.findAll('.u-answer').length).toBe(2)
    // Раньше кнопка появлялась только после ввода, а в QuestionNew
    // обработчик ввода остался в закомментированном блоке — вопрос
    // можно было набрать и не сохранить.
    expect(wrapper.find('.u-card__foot button[type="submit"]').exists()).toBe(true)
    expect(wrapper.vm.form.values.answers[0].is_correct).toBe(true)

    wrapper.unmount()
  })

  it('выбор другого радио переносит отметку', async () => {
    const { wrapper } = await mountForm()

    await setAnswers(wrapper, ['Вариант А', 'Вариант Б'])
    await setCorrect(wrapper, 1)

    expect(wrapper.vm.form.values.answers[1].is_correct).toBe(true)
    expect(wrapper.vm.form.values.answers[0].is_correct).toBe(false)

    wrapper.unmount()
  })

  it('верный вариант всегда один и после добавления строк', async () => {
    const { wrapper } = await mountForm()

    await addRow(wrapper)
    await setAnswers(wrapper, ['А', 'Б', 'В'])
    await setCorrect(wrapper, 2)

    const correct = wrapper.vm.form.values.answers.filter((a) => a.is_correct)
    expect(correct.length).toBe(1)
    expect(correct[0].answer).toBe('В')

    wrapper.unmount()
  })

  it('удаление строки не оставляет отметку на несуществующем ответе', async () => {
    const { wrapper } = await mountForm()

    await addRow(wrapper)
    await setAnswers(wrapper, ['А', 'Б', 'В'])
    await setCorrect(wrapper, 2)

    // В строке три кнопки с aria-label (перетаскивание, дублирование,
    // удаление) — берём именно удаление, иначе кликалась ручка.
    await wrapper.findAll('.u-repeatable__row')[2].find('button[aria-label="Удалить ответ"]').trigger('click')
    await flushPromises()

    const answers = wrapper.vm.form.values.answers
    expect(answers.length).toBe(2)
    expect(answers.some((a) => a.is_correct)).toBe(false)

    wrapper.unmount()
  })

  it('предпросмотр показывает варианты буквами и подсказывает верный', async () => {
    const { wrapper } = await mountForm()

    await fillQuestion(wrapper, 'Что верно?')
    await setAnswers(wrapper, ['Первое', 'Второе'])
    await setCorrect(wrapper, 1)

    const preview = wrapper.find('.u-question__preview').text()
    expect(preview).toContain('Что верно?')
    expect(preview).toContain('Первое')
    expect(preview).toContain('Второе')
    expect(wrapper.find('.u-question__preview-hint').text()).toContain('2')

    wrapper.unmount()
  })
})

describe('QuestionForm: валидация перед отправкой', () => {
  it('не отправляет вопрос без текста', async () => {
    const { wrapper } = await mountForm()

    await setAnswers(wrapper, ['А', 'Б'])
    await wrapper.vm.submitForm()
    await flushPromises()

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Введите текст вопроса')

    wrapper.unmount()
  })

  it('не отправляет вопрос без отмеченного верного варианта', async () => {
    const { wrapper } = await mountForm()

    await fillQuestion(wrapper)
    await setAnswers(wrapper, ['А', 'Б'])
    // Снимаем отметку — пользователь мог удалить верный вариант.
    wrapper.vm.form.values.answers = wrapper.vm.form.values.answers.map((a) => ({ ...a, is_correct: false }))
    await nextTick()

    await wrapper.vm.submitForm()
    await flushPromises()

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Отметьте правильный вариант')

    wrapper.unmount()
  })

  it('ловит повторяющиеся варианты', async () => {
    const { wrapper } = await mountForm()

    await fillQuestion(wrapper)
    await setAnswers(wrapper, ['Одинаковый', 'Одинаковый'])

    await wrapper.vm.submitForm()
    await flushPromises()

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Варианты не должны повторяться')

    wrapper.unmount()
  })

  it('требует не менее двух вариантов', async () => {
    const { wrapper } = await mountForm()

    await fillQuestion(wrapper)
    await wrapper.findAll('.u-repeatable__row')[1].find('button[aria-label="Удалить ответ"]').trigger('click')
    await flushPromises()

    wrapper.vm.form.values.answers = [{ id: null, answer: 'Единственный', is_correct: true }]
    await nextTick()

    await wrapper.vm.submitForm()
    await flushPromises()

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Нужно не менее двух вариантов')

    wrapper.unmount()
  })
})

describe('QuestionForm: сохранение', () => {
  it('отправляет is_correct булевым, а не индексом', async () => {
    const { wrapper } = await mountForm()

    await fillQuestion(wrapper)
    await setAnswers(wrapper, ['А', 'Б'])
    await setCorrect(wrapper, 1)

    await wrapper.vm.submitForm()
    await flushPromises()

    expect(http.post).toHaveBeenCalled()
    const payload = http.post.mock.calls[0][1]
    expect(payload.answers[0].is_correct).toBe(false)
    expect(payload.answers[1].is_correct).toBe(true)

    wrapper.unmount()
  })

  it('при ошибке сервера сохраняет введённое и показывает тост', async () => {
    const { wrapper } = await mountForm()

    await fillQuestion(wrapper, 'Текст вопроса')
    await setAnswers(wrapper, ['А', 'Б'])

    http.post.mockRejectedValueOnce(new Error('boom'))
    await wrapper.vm.submitForm()
    await flushPromises()

    expect(wrapper.vm.form.values.question_text).toBe('Текст вопроса')
    expect(wrapper.vm.form.values.answers[0].answer).toBe('А')
    expect(toastItems.some((t) => t.type === 'error')).toBe(true)

    wrapper.unmount()
  })
})

describe('QuestionForm: правка', () => {
  it('загружает вопрос и сохраняет по PATCH с его id', async () => {
    http.get.mockResolvedValueOnce({
      data: {
        success: true,
        data: {
          id: 7,
          question_text: 'Что является правильным?',
          answers: [
            { id: 1, answer: 'Да', is_correct: true },
            { id: 2, answer: 'Нет', is_correct: false },
          ],
        },
      },
    })

    const { wrapper } = await mountForm({ idEdit: 7 })

    expect(wrapper.vm.form.values.question_text).toBe('Что является правильным?')
    expect(wrapper.vm.form.values.answers.length).toBe(2)
    expect(wrapper.vm.form.values.answers[0].is_correct).toBe(true)

    await wrapper.vm.submitForm()
    await flushPromises()

    expect(http.patch).toHaveBeenCalled()
    expect(http.patch.mock.calls[0][0]).toBe('/api/questions/7')

    wrapper.unmount()
  })

  it('при недоступном вопросе показывает тост и не падает', async () => {
    http.get.mockRejectedValueOnce(new Error('boom'))

    const { wrapper } = await mountForm({ idEdit: 999 })

    expect(toastItems.some((t) => t.type === 'error')).toBe(true)
    expect(wrapper.find('.u-answer').exists()).toBe(true)

    wrapper.unmount()
  })
})

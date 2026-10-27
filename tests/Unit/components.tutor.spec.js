// @vitest-environment jsdom
/**
 * Тренажёр: стартовая страница и окно тренировки.
 *
 * Проверяется то, что ломает тренировку по-тихому:
 *  - галка и кнопка не должны предлагать действие, когда движок выключен
 *    или модель не установлена (пользователь нажимает и ждёт пустоту);
 *  - эталон не должен появляться в разборе сам — только по нажатию;
 *  - «вопросы кончились» должно быть объяснено, а не выглядеть как пустая
 *    карточка.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import TutorMain from '../../resources/js/Pages/Tutor/TutorMain.vue'
import TutorRunner from '../../resources/js/Pages/Tutor/TutorRunner.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} }
global.visualViewport = { addEventListener() {}, removeEventListener() {}, offsetLeft: 0, offsetTop: 0, width: 1024, height: 768, scale: 1 }

// vi.mock поднимается выше обычных объявлений, поэтому spies создаются
// через vi.hoisted: иначе фабрика мока обращалась бы к переменной до её
// инициализации (ReferenceError) и падала до старта тестов.
const api = vi.hoisted(() => ({
  fetchTutorHealth: vi.fn(),
  fetchTutorMaterials: vi.fn(),
  fetchTutorStats: vi.fn(),
  startTutorSession: vi.fn(),
  fetchTutorSession: vi.fn(),
  fetchNextQuestion: vi.fn(),
  answerQuestion: vi.fn(),
}))

vi.mock('../../resources/js/api/tutor.api', () => ({ ...api, default: api }))

const envelope = (data, meta = null) => ({ data: { success: true, data, error: null, meta } })

const HEALTHY = {
  enabled: true,
  available: true,
  model: 'qwen3.5:9b-q4_K_M',
  model_present: true,
  models: ['qwen3.5:9b-q4_K_M'],
  embeddings: false,
  backcheck: true,
  async: true,
  default_types: ['mcq', 'short'],
  question_types: ['mcq', 'short', 'open'],
}

const MATERIALS = [
  { id: 1, course_id: 7, course_title: 'Конструкция самолета', title: 'Курс 01', source: 'course', status: 'indexed', chunks_count: 276, sessions: 0 },
]

const ITEM = {
  id: 5,
  chunk_id: 3,
  qtype: 'mcq',
  question: 'При каком значении тока срабатывает предохранитель ПП-5?',
  options: ['Более 100 А', 'Более 50 А'],
}

const mountMain = () =>
  mount(TutorMain, { global: { plugins: [vuetify, i18n], mocks: { $router: { push: vi.fn() } } } })

const mountRunner = () =>
  mount(TutorRunner, {
    global: {
      plugins: [vuetify, i18n],
      mocks: { $route: { params: { session: '42' } } },
    },
  })

beforeEach(() => {
  vi.clearAllMocks()
  api.fetchTutorHealth.mockResolvedValue(envelope(HEALTHY))
  api.fetchTutorMaterials.mockResolvedValue(envelope(MATERIALS))
  api.fetchTutorStats.mockResolvedValue(envelope({ sessions: 1, answers: 4, correct: 3, percent: 75, graded: 4, by_type: [] }))
  api.startTutorSession.mockResolvedValue(envelope({ session_id: 42, available: 0, generating: true }))
  api.fetchTutorSession.mockResolvedValue(envelope({ session_id: 42, material: { id: 1, title: 'Курс 01' }, answered: 0, generating: false, async: true }))
  api.fetchNextQuestion.mockResolvedValue(envelope({ item: ITEM, remaining: 3 }))
  api.answerQuestion.mockResolvedValue(envelope({ response_id: 9, verdict: 'correct', score: 1, feedback: null }))
})

describe('TutorMain', () => {
  it('показывает материалы и состояние движка', async () => {
    const wrapper = mountMain()
    await flushPromises()

    expect(api.fetchTutorHealth).toHaveBeenCalled()
    expect(api.fetchTutorMaterials).toHaveBeenCalled()
    expect(wrapper.findAll('[data-test="tutor-start"]')).toHaveLength(1)
    expect(wrapper.find('[data-test="tutor-model"]').text()).toContain('qwen3.5')
  })

  it('предупреждает, когда модель не установлена', async () => {
    // Модель в конфиге есть, на машине нет: тренажёр включён, но
    // спрашивать нечем. Молчаливый список материалов выглядел бы как
    // поломка.
    api.fetchTutorHealth.mockResolvedValue(envelope({ ...HEALTHY, model_present: false }))

    const wrapper = mountMain()
    await flushPromises()

    expect(wrapper.find('[data-test="tutor-model-missing"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="tutor-engine-down"]').exists()).toBe(false)
  })

  it('предупреждает, когда движок недоступен', async () => {
    api.fetchTutorHealth.mockResolvedValue(envelope({ ...HEALTHY, available: false, error: 'connection refused' }))

    const wrapper = mountMain()
    await flushPromises()

    expect(wrapper.find('[data-test="tutor-engine-down"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('connection refused')
  })

  it('сообщает о выключенном тренажёре', async () => {
    api.fetchTutorHealth.mockResolvedValue(envelope({ ...HEALTHY, enabled: false, available: false }))

    const wrapper = mountMain()
    await flushPromises()

    expect(wrapper.find('[data-test="tutor-disabled"]').exists()).toBe(true)
  })

  it('отмечает синхронную очередь', async () => {
    // При QUEUE_CONNECTION=sync запрос «начать» выполняет генерацию в
    // себе и длится десятки секунд. Пользователь должен понимать, что
    // это не зависание.
    api.fetchTutorHealth.mockResolvedValue(envelope({ ...HEALTHY, async: false }))

    const wrapper = mountMain()
    await flushPromises()

    expect(wrapper.text()).toContain('синхронная')
  })

  it('пустое состояние при отсутствии материалов', async () => {
    api.fetchTutorMaterials.mockResolvedValue(envelope([]))

    const wrapper = mountMain()
    await flushPromises()

    expect(wrapper.find('[data-test="tutor-materials"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Материалов нет')
  })

  it('переходит в окно тренировки', async () => {
    const push = vi.fn()
    const wrapper = mount(TutorMain, { global: { plugins: [vuetify, i18n], mocks: { $router: { push } } } })
    await flushPromises()

    await wrapper.vm.start(MATERIALS[0])
    await flushPromises()

    expect(api.startTutorSession).toHaveBeenCalledWith(1, 6)
    expect(push).toHaveBeenCalledWith({ name: 'tutor.runner', params: { session: 42 } })
  })

  it('ошибка сохранения показывается текстом, а не «ошибкой сети»', async () => {
    api.fetchTutorMaterials.mockRejectedValue({
      response: { data: { error: { message: 'Материал не назначен вашей группе' } } },
    })

    const wrapper = mountMain()
    await flushPromises()

    expect(wrapper.vm.alert.text).toBe('Материал не назначен вашей группе')
  })
})

describe('TutorRunner', () => {
  it('показывает вопрос', async () => {
    const wrapper = mountRunner()
    await flushPromises()

    expect(api.fetchNextQuestion).toHaveBeenCalledWith('42')
    expect(wrapper.find('[data-test="tutor-question"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('При каком значении тока')
  })

  it('эталон не приходит вместе с ответом', async () => {
    const wrapper = mountRunner()
    await flushPromises()

    wrapper.vm.choice = 'Более 100 А'

    await wrapper.vm.send()
    await flushPromises()

    expect(api.answerQuestion).toHaveBeenCalledWith(5, { answer: 'Более 100 А' })

    // Эталона в состоянии нет — он не подмешивался в ответ сервера.
    expect(wrapper.vm.result.reference_answer).toBeUndefined()
    expect(wrapper.vm.revealed).toBe(false)
  })

  it('эталон запрашивается отдельным действием', async () => {
    api.answerQuestion
      .mockResolvedValueOnce(envelope({ response_id: 9, verdict: 'correct', score: 1, feedback: null }))
      .mockResolvedValueOnce(
        envelope({
          response_id: 10,
          verdict: 'correct',
          score: 1,
          feedback: null,
          reference_answer: 'Более 100 А',
          source_quote: 'Он срабатывает при перегрузке по току более 100 А.',
        })
      )

    const wrapper = mountRunner()
    await flushPromises()

    wrapper.vm.choice = 'Более 100 А'

    await wrapper.vm.send()
    await flushPromises()
    await wrapper.vm.showReference()
    await flushPromises()

    expect(api.answerQuestion).toHaveBeenLastCalledWith(5, {
      answer: 'Более 100 А',
      show_reference: true,
    })
    expect(wrapper.vm.revealed).toBe(true)
    expect(wrapper.text()).toContain('Более 100 А')
  })

  it('показывает вердикт неверного ответа', async () => {
    api.answerQuestion.mockResolvedValue(
      envelope({ response_id: 9, verdict: 'wrong', score: 0, feedback: 'Верно 100 А' })
    )

    const wrapper = mountRunner()
    await flushPromises()

    wrapper.vm.choice = 'Более 50 А'

    await wrapper.vm.send()
    await flushPromises()

    expect(wrapper.vm.result.verdict).toBe('wrong')
    expect(wrapper.text()).toContain('Неверно')
    expect(wrapper.text()).toContain('Верно 100 А')
  })

  it('объясняет, что вопросы кончились', async () => {
    // Пустой item без объяснения выглядит как сломанная страница:
    // карточка есть, вопроса нет.
    api.fetchNextQuestion.mockResolvedValue(envelope(null, { exhausted: true, hint: 'готовятся' }))

    const wrapper = mountRunner()
    await flushPromises()

    expect(wrapper.find('[data-test="tutor-question"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Вопросы закончились')
  })

  it('предупреждает о долгой генерации при синхронной очереди', async () => {
    api.fetchTutorSession.mockResolvedValue(
      envelope({ session_id: 42, material: { id: 1, title: 'Курс' }, answered: 0, generating: true, async: false })
    )
    // exhausted: false — вопросы не кончились, их готовят. Именно в этом
    // состоянии и показывается предупреждение о долгой синхронной генерации.
    api.fetchNextQuestion.mockResolvedValue(envelope(null, { exhausted: false }))

    const wrapper = mountRunner()
    await flushPromises()

    expect(wrapper.vm.generating).toBe(true)
    expect(wrapper.text()).toContain('Очередь синхронная')
  })

  it('кнопка ответа заблокирована без выбранного варианта', async () => {
    const wrapper = mountRunner()
    await flushPromises()

    expect(wrapper.vm.canAnswer).toBe(false)

    wrapper.vm.choice = 'Более 100 А'

    expect(wrapper.vm.canAnswer).toBe(true)
  })

  it('свободный ответ требует непустого текста', async () => {
    api.fetchNextQuestion.mockResolvedValue(
      envelope({ ...ITEM, qtype: 'short', options: null }, null)
    )

    const wrapper = mountRunner()
    await flushPromises()

    expect(wrapper.vm.canAnswer).toBe(false)

    wrapper.vm.freeAnswer = '   '

    expect(wrapper.vm.canAnswer).toBe(false)

    wrapper.vm.freeAnswer = 'раз в 6 месяцев'

    expect(wrapper.vm.canAnswer).toBe(true)
  })
})
// @vitest-environment jsdom
/**
 * Экзамены: список и прохождение.
 *
 * Регрессы, которые закрывает файл.
 *
 *  1. Страница прохождения считала результат В БРАУЗЕРЕ по is_correct,
 *     который приезжал вместе с вопросами. Тест требует, чтобы вопросы
 *     приходили из экзамена и не содержали правильности ответа, а вердикт
 *     приходил с сервера.
 *
 *  2. Ответ не отмечался: в обработчике стоял this.$set — API Vue 2,
 *     которого в Vue 3 нет. Вызов падал «this.$set is not a function»,
 *     счётчик оставался «Отвечено 0 из N», и кнопка «Закончить» не
 *     разблокировалась. Тест ловит именно это.
 *
 *  3. Клиент отправлял ответы на несуществующий /api/student-answers,
 *     причём метод был написан, но не вызывался — результаты не
 *     сохранялись вообще. Тест проверяет вызов правильного эндпоинта.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import ExamList from '../../resources/js/Pages/Exam/ExamList.vue'
import ExamRunner from '../../resources/js/Pages/Exam/ExamRunner.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})

const authModule = {
  namespaced: true,
  state: () => ({ accessToken: 't', user: { fio: 'Петров Иван Иванович', role: 'Обучаемый' }, permissionSlugs: ['exams.take'] }),
  getters: { permissionSet: s => new Set(s.permissionSlugs), isSuperAdmin: () => false },
}

const stubs = {
  'v-dialog': { template: '<div><slot /></div>' },
  AppToast: { props: ['modelValue', 'text'], template: '<div v-if="modelValue">{{ text }}</div>' },
}

const tick = () => new Promise(r => setTimeout(r, 0))
const envelope = (data, meta = null) => ({ data: { success: true, data, error: null, meta } })

const question = (id, answers = 3) => ({
  id,
  question_text: `Вопрос ${id}`,
  answers: Array.from({ length: answers }, (_, i) => ({ id: id * 10 + i, answer: `Вариант ${i + 1}` })),
})

const exam = (over = {}) => ({
  id: 1,
  title: 'Проверка знаний',
  module_title: 'Модуль',
  category_title: 'Специальность',
  opens_at: '2026-10-01 00:00:00',
  closes_at: '2026-10-31 00:00:00',
  max_attempts: 2,
  passing_score: 0.5,
  question_limit: 10,
  state: 'available',
  state_label: 'Доступен',
  available: true,
  attempts_used: 0,
  attempts_left: 2,
  best_score: null,
  passed: false,
  ...over,
})

const mountList = async () => {
  const w = mount(ExamList, {
    global: { plugins: [vuetify, i18n, createStore({ modules: { Auth: authModule } })], stubs },
  })
  await w.vm.$nextTick()
  await tick()
  return w
}

const mountRunner = async () => {
  const w = mount(ExamRunner, {
    props: { idEdit: 1 },
    global: { plugins: [vuetify, i18n, createStore({ modules: { Auth: authModule } })], stubs },
  })
  await w.vm.$nextTick()
  await tick()
  return w
}

beforeEach(() => {
  vi.clearAllMocks()

  // jsdom не реализует ResizeObserver, а он нужен внутренностям Vuetify.
  global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
  http.get.mockResolvedValue(envelope([exam()]))
  http.post.mockResolvedValue(envelope({
    attempt_id: 1, total_count: 2, correct_count: 2, score: 1, passed: true,
    passing_score: 0.5, attempts_left: 1,
  }))
})

describe('Список экзаменов', () => {
  it('показывает назначенные экзамены и состояние', async () => {
    const w = await mountList()

    expect(http.get).toHaveBeenCalledWith('/api/my/exams')
    expect(w.vm.exams).toHaveLength(1)
    expect(w.text()).toContain('Проверка знаний')
    expect(w.text()).toContain('использовано 0 из 2')
  })

  it('считает показатели: назначено / доступно / сдано', async () => {
    http.get.mockResolvedValue(envelope([
      exam(),
      exam({ id: 2, state: 'planned', available: false }),
      exam({ id: 3, passed: true, available: false }),
    ]));

    const w = await mountList();
    expect(w.vm.stats.map(s => s.value)).toEqual([3, 1, 1]);
  })

  it('кнопка «Закончить» ведёт на прохождение', async () => {
    const w = await mountList();
    const btn = w.find('.exams__item-actions').findComponent({ name: 'VBtn' });

    // Без vue-router v-btn не рендерит <a>, поэтому проверяем сам
    // целевой маршрут, а не атрибут href.
    expect(btn.props('to')).toEqual({ name: 'exams.take', params: { idEdit: 1 } });
  })

  it('недоступный экзамен не показывает кнопку прохождения', async () => {
    http.get.mockResolvedValue(envelope([exam({ available: false, state: 'closed' })]));
    const w = await mountList();

    expect(w.find('.exams__item-actions .v-btn').exists()).toBe(false);
  });

  it('пустое состояние объясняет, что экзаменов нет', async () => {
    http.get.mockResolvedValue(envelope([]));
    const w = await mountList();

    expect(w.text()).toContain('Экзаменов нет');
  });

  it('ошибка загрузки показывается тостом', async () => {
    http.get.mockRejectedValue({ response: { status: 500 } });
    const w = await mountList();

    expect(w.vm.alert).toBe(true);
    expect(w.vm.alertType).toBe('error');
  })
})

describe('Прохождение экзамена', () => {
  it('берёт вопросы из экзамена, а не из банка', async () => {
    await mountRunner();

    // Банк вопросов отдавал is_correct и был открыт любому
    // авторизованному, поэтому для сдачи используется отдельный
    // эндпоинт без правильных ответов.
    expect(http.get).toHaveBeenCalledWith('/api/exams/1/questions');
  })

  it('в вопросах нет признака правильности', async () => {
    http.get.mockResolvedValue(envelope({
      exam: exam(),
      questions: [question(1)],
    }));

    const w = await mountRunner();

    expect(w.vm.questions).toHaveLength(1);
    for (const answer of w.vm.questions[0].answers) {
      expect(answer).not.toHaveProperty('is_correct');
    }
  })

  it('выбор ответа отмечается — без $set из Vue 2', async () => {
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [question(1), question(2)] }));
    const w = await mountRunner();

    expect(w.vm.answeredCount).toBe(0);

    await w.find('.runner__answer input[type=radio]').trigger('change');
    await tick();

    expect(w.vm.answeredCount, 'ответ должен засчитываться').toBe(1);
    expect(w.vm.allAnswered).toBe(false);
  });

  it('кнопка завершения активна только когда отвечены все вопросы', async () => {
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [question(1), question(2)] }));
    const w = await mountRunner();

    expect(w.vm.allAnswered).toBe(false);

    // select() пишет ответ для ТЕКУЩЕГО вопроса, поэтому индекс надо
    // двигать — иначе второй ответ перезапишет первый.
    w.vm.select(w.vm.questions[0].answers[0].id);
    await tick();
    expect(w.vm.allAnswered).toBe(false);

    w.vm.currentIndex = 1;
    w.vm.select(w.vm.questions[1].answers[0].id);
    await tick();
    expect(w.vm.allAnswered).toBe(true);
  })

  it('отправляет answer_id на серверный эндпоинт', async () => {
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [question(1)] }));
    const w = await mountRunner();

    w.vm.select(w.vm.questions[0].answers[0].id);
    await tick();
    await w.vm.submit();

    // Не /api/student-answers (маршрута не существует) и не «правильно»,
    // а answer_id: вердикт обязан считать сервер.
    expect(http.post).toHaveBeenCalledWith('/api/exams/1/attempts', {
      answers: [{ question_id: 1, answer_id: 10 }],
    });

    const payload = http.post.mock.calls[0][1];
    expect(payload.answers[0]).not.toHaveProperty('is_correct');
  })

  it('показывает результат, посчитанный сервером', async () => {
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [question(1)] }));
    const w = await mountRunner();

    w.vm.select(w.vm.questions[0].answers[0].id);
    await tick();
    await w.vm.submit();
    await w.vm.$nextTick();

    expect(w.vm.result.scorePercent).toBe(100);
    expect(w.vm.result.passingScorePercent).toBe(50);
    expect(w.vm.result.passed).toBe(true);
    expect(w.text()).toContain('Сдано');
  })

  it('показывает «Не сдано», если сервер так решил', async () => {
    http.post.mockResolvedValue(envelope({
      attempt_id: 2, total_count: 2, correct_count: 0, score: 0, passed: false,
      passing_score: 0.5, attempts_left: 1,
    }));
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [question(1)] }));

    const w = await mountRunner();
    w.vm.select(w.vm.questions[0].answers[0].id);
    await tick();
    await w.vm.submit();
    await w.vm.$nextTick();

    expect(w.vm.result.scorePercent).toBe(0);
    expect(w.vm.result.passed).toBe(false);
    expect(w.text()).toContain('Не сдано');
  })

  it('экзамен без вопросов объясняет причину', async () => {
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [] }));
    const w = await mountRunner();

    expect(w.vm.loadError).not.toBe('');
    expect(w.text()).toContain('нет вопросов');
  })

  it('403 от сервера показывается текстом, а не «неизвестной ошибкой»', async () => {
    http.get.mockRejectedValue({
      response: { status: 403, data: { error: { message: 'Экзамен недоступен: Закрыт' } } },
    });

    const w = await mountRunner();

    expect(w.vm.loadError).toBe('Экзамен недоступен: Закрыт');
  })

  it('ошибка отправки не показывает результат', async () => {
    http.post.mockRejectedValue({ response: { status: 403, data: { error: { message: 'Попытки исчерпаны' } } } });
    http.get.mockResolvedValue(envelope({ exam: exam(), questions: [question(1)] }));

    const w = await mountRunner();
    w.vm.select(w.vm.questions[0].answers[0].id);
    await tick();
    await w.vm.submit();

    expect(w.vm.result).toBeNull();
    expect(w.vm.alertType).toBe('error');
    expect(w.vm.alertText).toBe('Попытки исчерпаны');
  })
})

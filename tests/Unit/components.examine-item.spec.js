// @vitest-environment jsdom
/**
 * Навигатор вопросов на странице экзамена (ExamineItem).
 *
 * Закрываем регресс цветов состояния. Карточки вопросов красились
 * инлайновыми строками `backgroundColor: 'green' | 'red' | 'gray' |
 * 'white'` с принудительным `color: 'black'`:
 *
 *  - белые карточки в тёмной теме давали белое пятно на тёмном экране;
 *  - чёрный текст поверх цветной заливки не держал контраст;
 *  - цвета из палитры браузера не связаны с токенами проекта и не
 *    переключаются вместе с темой.
 *
 * Теперь состояние задаёт класс из токенов, и проверяется именно то,
 * что состояние — правильное, а не «цвет подставился».
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { readFileSync } from 'node:fs'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

// v-pagination внутри страницы измеряет себя через ResizeObserver,
// которого нет в jsdom.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

import ExamineItem from '../../resources/js/Pages/Gift/ExamineItem.vue'
import ru from '../../resources/js/locales/ru.json'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru },
})

const QUESTIONS = [
  {
    id: 1,
    text: 'Первый',
    answers: [
      { id: 11, text: 'верный', is_correct: true },
      { id: 12, text: 'неверный', is_correct: false },
    ],
  },
  {
    id: 2,
    text: 'Второй',
    answers: [
      { id: 21, text: 'верный', is_correct: true },
      { id: 22, text: 'неверный', is_correct: false },
    ],
  },
]

const envelope = data => ({ data: { success: true, data } })

const mountPage = async () => {
  http.get.mockImplementation(url => {
    // Порядок важен: /api/questions/1 содержит в себе /api/questions.
    if (url.includes('/api/grade-boundary')) return Promise.resolve(envelope([]))
    if (/\/api\/questions(\?|$)/.test(url)) return Promise.resolve(envelope(QUESTIONS))
    return Promise.resolve(envelope({ id: 1, answers: QUESTIONS[0].answers }))
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div/>' } }],
  })
  await router.push('/questions')
  await router.isReady()

  const wrapper = mount(ExamineItem, {
    props: { idEdit: 1, idCategory: 1 },
    global: {
      plugins: [vuetify, i18n, router],
      stubs: {
        VProgressCircular: { template: '<span />' },
        MyProgress: { props: ['progressColor'], template: '<span />' },
      },
    },
  })

  await flushPromises()
  return wrapper
}

describe('ExamineItem: состояние карточки вопроса', () => {
  beforeEach(() => {
    http.get.mockReset()
  })

  it('неотвеченный вопрос — «unanswered»', async () => {
    const wrapper = await mountPage()

    expect(wrapper.vm.answerState({ id: 1 })).toBe('unanswered')

    wrapper.unmount()
  })

  it('отвеченный, но ещё не проверенный — «answered»', async () => {
    const wrapper = await mountPage()

    wrapper.vm.selectedAnswers[1] = 11
    expect(wrapper.vm.answerState({ id: 1 })).toBe('answered')

    wrapper.unmount()
  })

  it('верный ответ после проверки — «success»', async () => {
    const wrapper = await mountPage()

    wrapper.vm.selectedAnswers[1] = 11
    wrapper.vm.checkAnswers()

    expect(wrapper.vm.answerState({ id: 1 })).toBe('success')

    wrapper.unmount()
  })

  it('неверный ответ после проверки — «danger»', async () => {
    const wrapper = await mountPage()

    wrapper.vm.selectedAnswers[1] = 12
    wrapper.vm.checkAnswers()

    expect(wrapper.vm.answerState({ id: 1 })).toBe('danger')

    wrapper.unmount()
  })

  it('вердикт хранится одной картой, без русских строк', async () => {
    const wrapper = await mountPage()

    wrapper.vm.selectedAnswers[1] = 11
    wrapper.vm.checkAnswers()

    // Раньше здесь лежали «Правильно»/«Неправильно», которые нигде не
    // отображались, а рядом держалась вторая карта под цвета. Две карты
    // разъезжались: цвет мог остаться от прошлой проверки.
    expect(wrapper.vm.answerStatus[1]).toBe('correct')
    expect(Object.values(wrapper.vm.answerStatus)).toContain('wrong')
    expect(wrapper.vm.$data.answerStateMap).toBeUndefined()

    wrapper.unmount()
  })
})

describe('ExamineItem: оформление держится на токенах', () => {
  /**
   * Только разметка: поясняющие комментарии в script и style законно
   * упоминают те самые строки, которые проверка ищет.
   */
  const templateOf = () => {
    const src = readFileSync('resources/js/Pages/Gift/ExamineItem.vue', 'utf-8')
    return src.slice(src.indexOf('<template>'), src.indexOf('</template>'))
  }

  it('не задаёт цвета инлайновым style', () => {
    // Регресс: backgroundColor:'white' и color:'black' в :style.
    expect(templateOf()).not.toMatch(/backgroundColor:\s*['"](white|black|gray|grey)['"]/)
    expect(templateOf()).not.toMatch(/color:\s*['"]black['"]/)
  })

  it('не возвращает цвета из палитры браузера', () => {
    const source = readFileSync('resources/js/Pages/Gift/ExamineItem.vue', 'utf-8')

    // progressColor отдавал 'green'/'orange'/'red' в проп color.
    // Теперь это имена цветов темы Vuetify, собранной из токенов.
    expect(source).not.toMatch(/return\s*['"](green|orange|red)['"]/)
    expect(source).toMatch(/return\s*['"]success['"]/)
  })

  it('цвета состояния взяты из токенов', () => {
    const source = readFileSync('resources/js/Pages/Gift/ExamineItem.vue', 'utf-8')

    expect(source).toContain('--c-success-soft')
    expect(source).toContain('--c-danger-soft')
  })

  it('блок стилей scoped — правила не утекают на другие страницы', () => {
    const source = readFileSync('resources/js/Pages/Gift/ExamineItem.vue', 'utf-8')

    expect(source).not.toMatch(/^<style>$/m)
    expect(source).toContain('<style scoped>')
  })
})
// @vitest-environment jsdom
/**
 * Спеки LessonWorkspace (R2, blueprint §2.2): контракт remount-key и статусов.
 * Ключевое: iframe пересоздаётся при смене epoch — проигравший гонку не может
 * «перетереть» DOM нового урока; старый кадр уничтожается Vue-ключом.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { createI18n } from 'vue-i18n'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import LessonWorkspace from '../../resources/js/components/player/LessonWorkspace.vue'

const i18n = createI18n({
  legacy: true,
  locale: 'en',
  fallbackLocale: 'en',
  messages: {
    en: {
      coursePlayer: {
        loading: 'Loading lesson…',
        locked: 'locked',
        loginRequired: 'login',
        loadError: 'error',
        selectLesson: 'select',
        openFile: 'open',
        contentTypePending: 'pending',
      },
      courseManifest: {
        prevLesson: 'prev', nextLesson: 'next', retry: 'Retry',
      },
    },
  },
})
const vuetify = createVuetify({ components, directives })

vi.mock('../../resources/js/api/player.api', () => ({
  contentFrameUrl: (courseId, itemId, opts = {}) =>
    `/api/course/${courseId}/content/${itemId}${opts.highlight ? '?highlight=' + opts.highlight : ''}`,
}))

const lessonHtml = { id: 5, title: 'L5', content_type: 'html', documentHtml: '<p>x</p>' }

function mk(props = {}) {
  return mount(LessonWorkspace, {
    props: {
      courseId: 7,
      lesson: null,
      status: 'idle',
      errorKey: null,
      epoch: 0,
      materialIds: [],
      currentItemId: null,
      ...props,
    },
    global: { plugins: [vuetify, i18n], stubs: { 'v-progress-circular': true } },
  })
}

beforeEach(() => vi.clearAllMocks())

describe('LessonWorkspace — статусы', () => {
  it('idle → подсказка выбрать урок, кадра нет', () => {
    const w = mk({ status: 'idle' })
    expect(w.find('[data-test="lw-idle"]').exists()).toBe(true)
    expect(w.find('[data-test="lw-frame"]').exists()).toBe(false)
  })

  it('loading → aria-busy, без кадра', () => {
    const w = mk({ status: 'loading' })
    expect(w.find('[data-test="lw-loading"]').exists()).toBe(true)
    expect(w.get('section').attributes('aria-busy')).toBe('true')
  })

  it('locked → alert с i18n-ключом из errorKey', () => {
    const w = mk({ status: 'locked', errorKey: 'coursePlayer.locked' })
    expect(w.find('[data-test="lw-locked"]').exists()).toBe(true)
    expect(w.text()).toContain('locked')
  })

  it('error → alert + кнопка retry эмитит retry', async () => {
    const w = mk({ status: 'error', errorKey: 'coursePlayer.loadError' })
    expect(w.find('[data-test="lw-error"]').exists()).toBe(true)
    await w.find('[data-test="lw-error"] button').trigger('click')
    expect(w.emitted('retry')).toBeTruthy()
  })

  it('ready html → iframe с серверным URL контента', () => {
    const w = mk({ status: 'ready', lesson: lessonHtml, epoch: 3 })
    const frame = w.find('[data-test="lw-frame"]')
    expect(frame.exists()).toBe(true)
    expect(frame.attributes('src')).toBe('/api/course/7/content/5')
  })

  it('ready video с delivery.url → <video>, без iframe', () => {
    const w = mk({
      status: 'ready',
      lesson: { id: 9, title: 'V', content_type: 'video', delivery: { url: 'https://cdn/v.mp4', mode: 'signed' } },
    })
    expect(w.find('[data-test="lw-frame"]').exists()).toBe(false)
    expect(w.find('[data-test="lw-media"] video').attributes('src')).toBe('https://cdn/v.mp4')
  })

  it('ready неизвестный тип без delivery → заглушка pending (до S2)', () => {
    const w = mk({ status: 'ready', lesson: { id: 1, title: 'X', content_type: 'quiz' } })
    expect(w.find('[data-test="lw-empty"]').exists()).toBe(true)
  })
})

describe('LessonWorkspace — remount по epoch', () => {
  it('смена epoch пересоздаёт iframe (новый DOM-элемент)', async () => {
    const w = mk({ status: 'ready', lesson: lessonHtml, epoch: 1 })
    const elBefore = w.find('[data-test="lw-frame"]').element
    await w.setProps({ epoch: 2, lesson: { ...lessonHtml, id: 6 } })
    const elAfter = w.find('[data-test="lw-frame"]').element
    expect(elAfter).not.toBe(elBefore) // ключ убил старый кадр
    expect(elAfter.getAttribute('src')).toBe('/api/course/7/content/6')
  })

  it('смена epoch сбрасывает автовысоту кадра к базовой', async () => {
    const w = mk({ status: 'ready', lesson: lessonHtml, epoch: 1 })
    w.vm.autoHeight = 1234
    await w.setProps({ epoch: 2 })
    expect(w.vm.autoHeight).toBe(480)
  })
})

describe('LessonWorkspace — навигация по соседям', () => {
  it('prev/next из materialIds; на краях кнопки disabled', async () => {
    // id=5 → prev=4, next=6 — оба активны
    const w2 = mk({ status: 'ready', lesson: lessonHtml, currentItemId: 5, materialIds: [4, 5, 6] })
    const b2 = w2.findAll('.lw__nav button')
    expect(b2.length).toBe(2)
    expect(b2[0].attributes('disabled')).toBeUndefined()
    await b2[1].trigger('click')
    expect(w2.emitted('select')?.[0]).toEqual([6])
    await b2[0].trigger('click')
    expect(w2.emitted('select')?.[1]).toEqual([4])
    // край: первый материал — prev disabled
    const w3 = mk({ status: 'ready', lesson: lessonHtml, currentItemId: 4, materialIds: [4, 5, 6] })
    expect(w3.findAll('.lw__nav button')[0].attributes('disabled')).toBeDefined()
  })
})

// @vitest-environment jsdom
/**
 * Юнит-тесты useLessonProgress — серверный + локальный прогресс чтения.
 *
 * Покрывают: тайминг тиков (только активная вкладка), flush каждые 20 с,
 * приоритет resume (сервер > localStorage), валидацию id узла, накопление
 * max scroll, и ДЕФЕКТ Д-LP1: unbindUnloadGuards снимает visibilitychange
 * неверным слушателем (onPageHide вместо анонимной стрелки) — слушатель
 * утекает и после unmount продолжает дёргать flushViewEvent на чужом стейте.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

const { apiPost } = vi.hoisted(() => ({ apiPost: vi.fn() }))
vi.mock('../../resources/js/api/player.api', () => ({
  saveProgress: (...a) => apiPost('progress', ...a),
  sendViewEvent: (...a) => apiPost('view', ...a),
}))

import { useLessonProgress } from '../../resources/js/composables/useLessonProgress'

const COURSE = 7

beforeEach(() => {
  apiPost.mockReset().mockResolvedValue({})
  localStorage.clear()
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
})

/* ───────────────────────── openLesson / saveProgress ───────────────────────── */

it('openLesson writes local resume and posts progress immediately', async () => {
  const lp = useLessonProgress(COURSE)
  lp.openLesson(11, '#sec-2')

  const saved = JSON.parse(localStorage.getItem(`course:${COURSE}:resume`))
  expect(saved.last_item_id).toBe(11)
  expect(saved.last_anchor).toBe('#sec-2')

  const call = apiPost.mock.calls.find(([kind]) => kind === 'progress')
  expect(call).toBeTruthy()
  expect(call[1]).toBe(COURSE)
  expect(call[2]).toEqual({ lastItemId: 11, lastAnchor: '#sec-2', percent: 0 })
})

it('openLesson flushes view data of the PREVIOUS lesson first', async () => {
  const lp = useLessonProgress(COURSE)
  lp.openLesson(1)
  vi.advanceTimersByTime(5000) // 5 секунд чтения урока 1
  lp.reportScroll(60)

  lp.openLesson(2) // переключение — предыдущий урок обязан быть дослан

  const viewCall = apiPost.mock.calls.find(([kind]) => kind === 'view')
  expect(viewCall[2]).toEqual({
    aukstructureId: 1,
    secondsViewed: 5,
    scrollPercent: 60,
  })
})

/* ─────────────────────────── тики и flush по таймеру ──────────────────────── */

it('counts seconds only while document is not hidden', () => {
  const lp = useLessonProgress(COURSE)
  lp.openLesson(1)

  // Вкладка активна 8 с
  vi.advanceTimersByTime(8000)

  // Скрываем вкладку: Object.defineProperty, т.к. getter встроен.
  const hiddenSpy = vi.spyOn(document, 'hidden', 'get').mockReturnValue(true)
  vi.advanceTimersByTime(10_000) // не должны считаться
  hiddenSpy.mockRestore()

  vi.advanceTimersByTime(2000) // снова активна: +2 → всего 10

  lp.flushViewEvent()
  const viewCall = apiPost.mock.calls.filter(([k]) => k === 'view').pop()
  expect(viewCall[2].secondsViewed).toBe(10)
})

it('auto-flushes accumulated view every 20 s', () => {
  const lp = useLessonProgress(COURSE)
  lp.openLesson(3)

  vi.advanceTimersByTime(20_000)

  const calls = apiPost.mock.calls.filter(([k]) => k === 'view')
  expect(calls.length).toBe(1)
  expect(calls[0][2].secondsViewed).toBeGreaterThanOrEqual(19)
  expect(calls[0][2].secondsViewed).toBeLessThanOrEqual(20)

  // Повторный flush при нулевом накоплении — пустых запросов быть не должно.
  vi.advanceTimersByTime(20_000)
  expect(apiPost.mock.calls.filter(([k]) => k === 'view').length).toBe(1)
})

it('reportScroll keeps only the maximum and clamps to 100', () => {
  const lp = useLessonProgress(COURSE)
  lp.openLesson(4)
  lp.reportScroll(40)
  lp.reportScroll(95.6) // округление вверх до 96
  lp.reportScroll(10) // назад — меньше максимума, игнорируется
  lp.reportScroll(150) // clamp к 100

  lp.flushViewEvent()
  const call = apiPost.mock.calls.filter(([k]) => k === 'view').pop()
  expect(call[2].scrollPercent).toBe(100)
})

/* ───────────────────────────── pagehide guard ─────────────────────────────── */

it('pagehide flushes view event and re-saves resume', () => {
  const lp = useLessonProgress(COURSE)
  lp.bindUnloadGuards()
  lp.openLesson(5, '1200')
  vi.advanceTimersByTime(3000)

  window.dispatchEvent(new Event('pagehide'))

  const viewCall = apiPost.mock.calls.filter(([k]) => k === 'view').pop()
  expect(viewCall[2]).toEqual({ aukstructureId: 5, secondsViewed: 3, scrollPercent: 0 })

  const progCalls = apiPost.mock.calls.filter(([k]) => k === 'progress')
  expect(progCalls.at(-1)[2]).toEqual({ lastItemId: 5, lastAnchor: '1200', percent: 0 })

  lp.unbindUnloadGuards()
})

/*
 * ДЕФЕКТ Д-LP1 — ИСПРАВЛЕН в resources/js/composables/useLessonProgress.js:
 * bindUnloadGuards вешал анонимную стрелку, а unbind снимал её неверным
 * слушателем (onPageHide) → removeEventListener был no-op, слушатель
 * visibilitychange утекал навсегда и после размонтирования продолжал
 * слать POST /view («фантомный двойной flush мёртвого плеера»).
 * Фикс: именованный слушатель onVisibilityChange, симметричный bind/unbind.
 * Тест включён (был it.skip до фикса).
 */
it('unbind removes visibilitychange listener (defect Д-LP1 fixed)', () => {
  const lp = useLessonProgress(COURSE)
  lp.bindUnloadGuards()
  lp.openLesson(6)
  lp.unbindUnloadGuards()
  apiPost.mockClear()

  // После unbind скрытие вкладки НЕ должно порождать сетевых событий.
  vi.spyOn(document, 'hidden', 'get').mockReturnValue(true)
  document.dispatchEvent(new Event('visibilitychange'))

  expect(apiPost).not.toHaveBeenCalled()
})

/* ─────────────────────────── resolveStartItem ─────────────────────────── */

it('server resume has priority over localStorage', () => {
  localStorage.setItem(`course:${COURSE}:resume`, JSON.stringify({ last_item_id: 2 }))
  const lp = useLessonProgress(COURSE)

  const start = lp.resolveStartItem({ last_item_id: 9, last_anchor: '#x' }, new Set([2, 9]))
  expect(start).toEqual({ itemId: 9, anchor: '#x' })
})

it('falls back to localStorage when server resume is null (offline)', () => {
  localStorage.setItem(`course:${COURSE}:resume`, JSON.stringify({ last_item_id: 2, last_anchor: null }))
  const lp = useLessonProgress(COURSE)

  const start = lp.resolveStartItem(null, new Set([2]))
  expect(start).toEqual({ itemId: 2, anchor: null })
})

it('ignores resume pointing at a deleted node (forged/stale id)', () => {
  localStorage.setItem(`course:${COURSE}:resume`, JSON.stringify({ last_item_id: 666 }))
  const lp = useLessonProgress(COURSE)

  // 666 нет в дереве — открываем курс сначала, а не падает iframe.
  expect(lp.resolveStartItem({ last_item_id: 777 }, new Set([1, 2]))).toBeNull()
})

it('survives corrupted localStorage JSON without throwing', () => {
  localStorage.setItem(`course:${COURSE}:resume`, '{broken json!')
  const lp = useLessonProgress(COURSE)

  expect(() => lp.readLocalResume()).not.toThrow()
  expect(lp.resolveStartItem(null, new Set([1]))).toBeNull()
})

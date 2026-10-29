/**
 * useLessonProgress — серверный + локальный прогресс чтения (Д6, событийный P2).
 *
 * Источники:
 *  - resume из player-endpoint (сервер) — точка возврата на любом устройстве;
 *  - localStorage — мгновенный fallback/кэш для офлайна;
 *  - POST /progress — при смене материала и на pagehide;
 *  - POST /view — накопленное время в кадре + максимальный процент прокрутки
 *    («пройден» = ≥15с ИЛИ скролл ≥90%, а не только «доскроллил»).
 */
import { saveProgress, sendViewEvent } from '../api/player.api'

const LS_RESUME_KEY = (courseId) => `course:${courseId}:resume`
const VIEW_FLUSH_MS = 20_000 // отправляем событие каждые 20 с активного просмотра

export function useLessonProgress(courseId) {
  let currentItemId = null
  let currentAnchor = null
  let secondsViewed = 0
  let maxScrollPercent = 0
  let tickTimer = null
  let flushTimer = null

  function readLocalResume() {
    try {
      return JSON.parse(localStorage.getItem(LS_RESUME_KEY(courseId)) || 'null')
    } catch (e) {
      return null
    }
  }

  function writeLocalResume(resume) {
    try {
      localStorage.setItem(LS_RESUME_KEY(courseId), JSON.stringify(resume))
    } catch (e) {
      /* приватный режим — игнорируем */
    }
  }

  /** Вызывать при каждой смене материала. */
  function openLesson(itemId, anchor = null) {
    flushViewEvent() // сначала досылаем данные по предыдущему уроку
    currentItemId = itemId
    currentAnchor = anchor
    resetAccumulators()

    const resume = { last_item_id: itemId, last_anchor: anchor, updated_at: Date.now() }
    writeLocalResume(resume)
    saveProgress(courseId, {
      lastItemId: itemId,
      lastAnchor: anchor,
      percent: 0,
    }).catch(() => { /* сеть недоступна — localStorage страхует */ })

    startTicking()
  }

  /** Фронт зовёт при прокрутке кадра (throttled самим вызывающим). */
  function reportScroll(percent) {
    if (percent > maxScrollPercent) maxScrollPercent = Math.min(100, Math.round(percent))
  }

  function startTicking() {
    stopTicking()
    tickTimer = setInterval(() => {
      // считаем секунды только пока вкладка активна
      if (!document.hidden) secondsViewed += 1
    }, 1000)
    flushTimer = setInterval(flushViewEvent, VIEW_FLUSH_MS)
  }

  function stopTicking() {
    if (tickTimer) clearInterval(tickTimer)
    if (flushTimer) clearInterval(flushTimer)
    tickTimer = null
    flushTimer = null
  }

  function flushViewEvent() {
    if (currentItemId == null || (secondsViewed === 0 && maxScrollPercent === 0)) return
    const payload = {
      aukstructureId: currentItemId,
      secondsViewed,
      scrollPercent: maxScrollPercent,
    }
    // P0 (аудит): оба накопителя сбрасываются ДО отправки, симметрично.
    // Раньше здесь обнулялся только secondsViewed, а maxScrollPercent —
    // позже, в openLesson(). Между этими двумя моментами lived async
    // sendViewEvent: быстрый переход «урок A → B» вызывал
    // flush(A) → openLesson(B) → resetAccumulators(), и уже захваченный
    // payload всё равно отправлялся — но следующий flush(B) мог унести
    // чужой scroll. Единая точка сброса исключает обе гонки.
    secondsViewed = 0
    maxScrollPercent = 0
    sendViewEvent(courseId, payload).catch(() => {})
  }

  /** Полный сброс накоплений — вызывается только при открытии нового урока. */
  function resetAccumulators() {
    secondsViewed = 0
    maxScrollPercent = 0
  }

  function onPageHide() {
    flushViewEvent()
    if (currentItemId != null) {
      saveProgress(courseId, {
        lastItemId: currentItemId,
        lastAnchor: currentAnchor,
        percent: 0,
      }).catch(() => {})
    }
  }

  // Именованный слушатель ОБЯЗАТЕЛЕН: анонимную стрелку в bind невозможно
  // снять в unbind — removeEventListener('visibilitychange', onPageHide) с
  // другим слушателем ничего не удаляет, и после unmount скрытие вкладки
  // продолжало бы дёргать flushViewEvent на чужом стейте (утечка Д-LP1).
  function onVisibilityChange() {
    if (document.hidden) flushViewEvent()
  }

  function bindUnloadGuards() {
    window.addEventListener('pagehide', onPageHide)
    document.addEventListener('visibilitychange', onVisibilityChange)
  }

  function unbindUnloadGuards() {
    window.removeEventListener('pagehide', onPageHide)
    document.removeEventListener('visibilitychange', onVisibilityChange)
    stopTicking()
    flushViewEvent()
  }

  /**
   * На какую точку открывать курс: серверный resume приоритетнее локального,
   * но если сервер молчит (офлайн) — берём localStorage. Валидируем, что
   * узел всё ещё существует в дереве (set of ids передаёт вызывающий).
   */
  function resolveStartItem(serverResume, validIds) {
    const candidates = [serverResume, readLocalResume()].filter(Boolean)
    for (const c of candidates) {
      if (c.last_item_id && validIds.has(c.last_item_id)) {
        return { itemId: c.last_item_id, anchor: c.last_anchor || null }
      }
    }
    return null
  }

  return {
    openLesson,
    reportScroll,
    bindUnloadGuards,
    unbindUnloadGuards,
    resolveStartItem,
    readLocalResume,
    flushViewEvent,
  }
}

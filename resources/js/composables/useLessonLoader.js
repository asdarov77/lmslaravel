/**
 * useLessonLoader — реактивная загрузка урока без гонок и утечек (плеер v2, S1).
 *
 * Проблема, которую он решает (см. docs/course-player-blueprint.md §0):
 * при быстрой смене уроков цепочка «detail → контент» в CourseManifestNew.vue
 * не отменяется — ответы приходят в обратном порядке, старый контент «залипает».
 *
 * Механика защиты:
 *  1) loadToken — монотонный счётчик. Каждый async-шаг перед записью результата
 *     сверяет свой токен с текущим; не совпал — результат выбрасывается. Это
 *     надёжнее флага isLoading: флаг нельзя «сверить» на момент await.
 *  2) AbortController — физическая отмена fetch предыдущей загрузки. Один
 *     controller хранится в замыкании scope; новый load() отменяет старый.
 *  3) docCache (LRU) — повторное открытие документа мгновенно, без сети;
 *     кэш контента переживает смену уроков, но очищается при logout/route-leave.
 *  4) signed-TTL — подписанный URL живёт в памяти урока до истечения exp−60c;
 *     перематка/повтор не перевыпускают подпись (требование X-Accel, §2.4).
 *  5) epoch — счётчик remount'а: компонент рабочей области вешает :key="epoch",
 *     гарантируя полное уничтожение DOM/слушателей старого рендера.
 *
 * Все таймеры/контроллеры умирают в onScopeDispose — composable нельзя вызывать
 * вне setup-контекста компонента урока.
 */
import { computed, onScopeDispose, ref, shallowRef, unref } from 'vue'
import { createDocCache } from '../utils/docCache'
import { contentFrameUrl } from '../api/player.api'

// Подпись/URL контента перевыпускаем за минуту до реального истечения.
const SIGNED_SAFETY_MS = 60_000

export function useLessonLoader({ courseId, api }) {
  const docCache = createDocCache()

  // shallowRef: html-документы тяжёлые — deep-reactivity по строке бессмысленна.
  const lesson = shallowRef(null)        // payload урока из GET /lesson/{id}
  const status = ref('idle')             // idle | loading | ready | error | locked
  const errorKey = ref(null)             // i18n-ключ ошибки (не хардкод текста)
  const epoch = ref(0)                   // :key для remount рабочей области

  let loadToken = 0
  let inflightAbort = null
  let signed = null                      // { url, expiresAt } — только для video/pdf/file

  /** Отменить любую незавершённую загрузку (внешний вызов: unmount, logout). */
  function cancelInflight() {
    if (inflightAbort) {
      inflightAbort.abort()
      inflightAbort = null
    }
  }

  async function load(lessonId, { highlight = '', force = false } = {}) {
    if (lessonId == null) return
    // Физическая отмена предыдущего запроса + логическая отбраковка его ответа.
    cancelInflight()
    const token = ++loadToken
    const controller = new AbortController()
    inflightAbort = controller

    status.value = 'loading'
    errorKey.value = null

    try {
      const cacheKey = { courseId: unref(courseId), lessonId }

      // Шаг 1: detail урока (правила доступа проверяются сервером; 403 → locked).
      let model = lesson.value && lesson.value.id === lessonId && !force ? lesson.value : null
      if (!model) {
        model = await api.fetchLesson(unref(courseId), lessonId, { signal: controller.signal })
      }
      if (token !== loadToken) return // нас опередил более новый load()

      if (model?.access && model.access.allowed === false) {
        lesson.value = model
        status.value = 'locked'
        bumpEpoch(token)
        return
      }

      // Шаг 2: контент по типу. Для html/article — документ (cache-first);
      // для video/pdf/file — подписанный URL с TTL (не перевыпускаем без нужды).
      if (model.content_type === 'html' || model.content_type === 'article') {
        const cached = docCache.get(cacheKey.courseId, lessonId)
        if (cached && !force) {
          model = { ...model, documentHtml: cached }
        } else {
          const html = await api.fetchLessonDocument(unref(courseId), lessonId, {
            signal: controller.signal,
            highlight,
          })
          if (token !== loadToken) return
          docCache.put(cacheKey.courseId, lessonId, html)
          model = { ...model, documentHtml: html }
        }
      } else if (['video', 'pdf', 'file'].includes(model.content_type)) {
        const fresh = signed && signed.lessonId === lessonId && Date.now() < signed.expiresAt
        if (!fresh || force) {
          const s = await api.fetchSignedDelivery(unref(courseId), lessonId, {
            signal: controller.signal,
          })
          if (token !== loadToken) return
          signed = {
            lessonId,
            url: s.url,
            mode: s.mode || 'php',
            expiresAt: Date.now() + Math.max(0, (s.expires_in || 900) * 1000 - SIGNED_SAFETY_MS),
          }
        }
        model = { ...model, delivery: { url: signed.url, mode: signed.mode } }
      }

      if (token !== loadToken) return
      lesson.value = model
      status.value = 'ready'
      bumpEpoch(token)

      // Prefetch соседа — дёшево и только если пользователь не менял урок 1.5с.
      schedulePrefetch(model.next_id)
    } catch (e) {
      if (e?.name === 'CanceledError' || e?.name === 'AbortError' || e?.code === 'ERR_CANCELED') {
        return // отменённый запрос — не ошибка, новый load уже идёт
      }
      if (token !== loadToken) return
      const code = e?.response?.status
      if (code === 403) {
        status.value = 'locked'
        errorKey.value = 'coursePlayer.locked'
      } else if (code === 401) {
        status.value = 'error'
        errorKey.value = 'coursePlayer.loginRequired'
      } else {
        status.value = 'error'
        errorKey.value = 'coursePlayer.loadError'
      }
      bumpEpoch(token)
    }
  }

  // epoch меняется только победителем гонки (чей token актуален) — иначе
  // проигравший ответ сбросил бы DOM нового урока.
  function bumpEpoch(token) {
    if (token === loadToken) epoch.value += 1
  }

  let prefetchTimer = null
  function schedulePrefetch(nextId) {
    if (!nextId) return
    clearTimeout(prefetchTimer)
    prefetchTimer = setTimeout(() => {
      api.prefetchLesson?.(unref(courseId), nextId)?.catch?.(() => {})
    }, 1500)
  }

  /** Точечный сброс кэша документа (материал отредактирован методистом). */
  function invalidateDoc(lessonId) {
    docCache.delete(unref(courseId), lessonId)
  }

  onScopeDispose(() => {
    cancelInflight()
    clearTimeout(prefetchTimer)
  })

  return {
    lesson,
    status,
    errorKey,
    epoch,
    isLoading: computed(() => status.value === 'loading'),
    load,
    retry: () => lesson.value && load(lesson.value.id, { force: true }),
    cancelInflight,
    invalidateDoc,
  }
}

// Реализация api по умолчанию поверх существующих player-эндпоинтов
// (CoursePlayerController/CourseContentController). Новый /api/lesson/{id}
// появится в S2; пока detail выводится из дерева плеера, а документ — из
// content-endpoint с токеном в query (iframe-совместимая авторизация).
export function createDefaultLessonApi({ treeSource }) {
  return {
    async fetchLesson(courseId, lessonId, { signal }) {
      const node = treeSource.nodeById.value.get(Number(lessonId))
      if (!node) throw Object.assign(new Error('not found'), { response: { status: 404 } })
      return {
        id: node.id,
        title: node.title,
        // content_type появится на сервере в S2; сейчас все материалы — html.
        content_type: node.content_type || 'html',
        access: node.status === 'locked' ? { allowed: false, reason: node.lock_reason } : { allowed: true },
        next_id: treeSource.nextIdOf ? treeSource.nextIdOf(node.id) : null,
      }
    },
    async fetchLessonDocument(courseId, lessonId, { signal, highlight }) {
      const url = contentFrameUrl(courseId, lessonId, { highlight })
      const res = await fetch(url, { signal, credentials: 'omit' })
      if (!res.ok) {
        throw Object.assign(new Error(`HTTP ${res.status}`), { response: { status: res.status } })
      }
      return res.text()
    },
    async fetchSignedDelivery(courseId, lessonId) {
      // Появится в S2 вместе с video/pdf/file — сейчас типы не существуют.
      throw Object.assign(new Error('content type not delivered yet'), { response: { status: 501 } })
    },
  }
}

/**
 * useContentFrame — управление iframe с материалом курса.
 *
 * Закрывает дефекты:
 *  Д9 — высота кадра пересчитывается через ResizeObserver на <body> документа,
 *       а не один раз на load (картинки, подгружаемые позже, больше не обрезаются);
 *  Д10 — доступность: у iframe обязательный title, есть aria-live-статус загрузки;
 *  resume-point — прокрутка к last_anchor после загрузки документа.
 */
import { onBeforeUnmount, ref, shallowRef } from 'vue'

const MIN_HEIGHT = 320
const MAX_HEIGHT = 20000 // защита от pathological layout

export function useContentFrame(iframeRef) {
  const frameHeight = ref(MIN_HEIGHT)
  const loading = ref(false)
  const failed = ref(false)
  const docReady = shallowRef(false)

  let resizeObserver = null
  let imageLoadHandlers = []

  function detachObservers() {
    if (resizeObserver) {
      resizeObserver.disconnect()
      resizeObserver = null
    }
    imageLoadHandlers.forEach(({ img, handler }) => img.removeEventListener('load', handler))
    imageLoadHandlers = []
  }

  function recalcHeight(doc) {
    if (!doc || !doc.body) return
    const h = Math.min(
      MAX_HEIGHT,
      Math.max(MIN_HEIGHT, doc.body.scrollHeight + 24)
    )
    frameHeight.value = h
  }

  /** Подписаться на рост документа: ResizeObserver + догрузка изображений. */
  function observeDocument(iframeEl) {
    detachObservers()
    docReady.value = false
    try {
      const doc = iframeEl.contentDocument
      if (!doc || !doc.body) return
      docReady.value = true
      recalcHeight(doc)

      if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => recalcHeight(doc))
        resizeObserver.observe(doc.body)
      }

      // тяжёлые сканы доезжают позже load — пересчитываем на каждую картинку
      doc.querySelectorAll('img').forEach((img) => {
        if (img.complete) return
        const handler = () => recalcHeight(doc)
        img.addEventListener('load', handler)
        imageLoadHandlers.push({ img, handler })
      })
    } catch (e) {
      // кросс-доменный доступ невозможен — оставляем базовую высоту
      docReady.value = false
    }
  }

  function onLoad() {
    loading.value = false
    failed.value = false
    const el = iframeRef.value
    if (el) observeDocument(el)
  }

  function navigate(url) {
    loading.value = true
    failed.value = false
    // Сбрасываем высоту перед загрузкой нового материала: иначе кадр
    // держит высоту предыдущего (более длинного) документа до первого
    // события ResizeObserver — «прыжок» и пустые белые полосы снизу.
    detachObservers()
    frameHeight.value = MIN_HEIGHT
    if (iframeRef.value) {
      iframeRef.value.src = url
    }
  }

  /** Ручной режим высоты кадра: fit (авто) | window (в экран) | fixed (слайдер). */
  const heightMode = ref(localStorage.getItem('cp.heightMode') || 'fit')
  const fixedHeight = ref(Number(localStorage.getItem('cp.fixedHeight')) || 640)

  function setHeightMode(mode) {
    heightMode.value = mode
    localStorage.setItem('cp.heightMode', mode)
  }

  function setFixedHeight(px) {
    fixedHeight.value = Math.min(2000, Math.max(MIN_HEIGHT, Number(px) || MIN_HEIGHT))
    localStorage.setItem('cp.fixedHeight', String(fixedHeight.value))
  }

  /** Итоговая высота кадра с учётом выбранного режима. */
  function effectiveHeight() {
    if (heightMode.value === 'window') {
      return Math.max(MIN_HEIGHT, window.innerHeight - 260)
    }
    if (heightMode.value === 'fixed') return fixedHeight.value
    return frameHeight.value
  }

  /** Прокрутка к якорю/позиции из resume-point (Д6). */
  function scrollToAnchor(anchor) {
    if (!anchor) return
    const el = iframeRef.value
    if (!el || !el.contentDocument) return
    try {
      const id = String(anchor).replace(/^#/, '')
      const target = /^\d+$/.test(id)
        ? null
        : el.contentDocument.getElementById(id)
      if (target) {
        target.scrollIntoView({ block: 'start' })
      } else if (/^\d+$/.test(id)) {
        el.contentWindow.scrollTo({ top: Number(id) })
      }
    } catch (e) {
      /* best-effort */
    }
  }

  onBeforeUnmount(detachObservers)

  return {
    frameHeight,
    loading,
    failed,
    docReady,
    navigate,
    onLoad,
    scrollToAnchor,
    recalcHeight,
    heightMode,
    fixedHeight,
    setHeightMode,
    setFixedHeight,
    effectiveHeight,
  }
}

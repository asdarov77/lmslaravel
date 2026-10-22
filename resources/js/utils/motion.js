/**
 * Motion utilities для поддержки prefers-reduced-motion и stagger-анимаций.
 *
 * Использование:
 *   import { prefersReducedMotion, staggerChildren } from '@/utils/motion'
 *
 * В компоненте:
 *   const reducedMotion = prefersReducedMotion()
 *   const delays = staggerChildren(items.length)
 */

import { ref, computed, onMounted, onUnmounted } from 'vue'

/**
 * Реактивный флаг prefers-reduced-motion.
 *
 * Возвращает ref<boolean>, который обновляется при изменении
 * системной настройки.
 *
 * @returns {import('vue').Ref<boolean>}
 */
export function prefersReducedMotion() {
  const reduced = ref(false)

  let mediaQuery = null

  const canMatch = () => typeof window !== 'undefined' && typeof window.matchMedia === 'function'

  const update = () => {
    if (canMatch()) {
      reduced.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    }
  }

  // Инициализация
  const init = () => {
    if (!canMatch()) return

    update()

    const mq = window.matchMedia('(prefers-reduced-motion: reduce)')
    const handler = () => update()
    mq.addEventListener('change', handler)

    return () => mq.removeEventListener('change', handler)
  }

  // Ленивая инициализация при первом обращении
  let cleanup = null
  const getValue = () => {
    if (cleanup === null) {
      cleanup = init()
    }
    return reduced.value
  }

  // Возвращаем computed, который лениво инициализируется
  return computed(() => {
    if (cleanup === null) {
      cleanup = init()
    }
    return reduced.value
  })
}

/**
 * Генерирует массив задержек для stagger-анимации.
 *
 * @param {number} count - количество элементов
 * @param {number} baseDelay - базовая задержка (ms), по умолчанию из CSS токена --stagger-delay
 * @returns {number[]} массив задержек в ms
 */
export function staggerChildren(count, baseDelay = 40) {
  if (count <= 0) return []

  // Если включён reduced-motion, возвращаем нули
  if (typeof window !== 'undefined') {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (reduced) return Array(count).fill(0)
  }

  return Array.from({ length: count }, (_, i) => i * 40)
}

/**
 * Хук для получения задержек stagger для массива элементов.
 *
 * @param {import('vue').Ref<number> | number} count - количество элементов (ref или число)
 * @returns {import('vue').ComputedRef<number[]>}
 */
export function useStagger(count) {
  return computed(() => {
    const n = typeof count === 'object' ? count.value : count
    return staggerChildren(n)
  })
}

/**
 * Анимация появления элемента с заданной задержкой.
 *
 * @param {HTMLElement} el - элемент для анимации
 * @param {number} delay - задержка в ms
 * @param {object} options - опции анимации
 * @returns {Promise<void>}
 */
export function animateIn(el, delay = 0, options = {}) {
  const {
    duration = 300,
    ease = 'var(--stagger-ease, cubic-bezier(0.16, 1, 0.3, 1))',
    from = { opacity: 0, transform: 'translateY(16px)' },
    to = { opacity: 1, transform: 'translateY(0)' },
  } = options

  return new Promise(resolve => {
    if (!el) return resolve()

    // Reduced motion check
    if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      Object.assign(el.style, to)
      return resolve()
    }

    const keyframes = [from, to]
    const timing = {
      duration,
      easing: ease,
      fill: 'forwards',
      delay,
    }

    const animation = el.animate(keyframes, timing)
    animation.onfinish = resolve
    animation.oncancel = resolve
  })
}

/**
 * Запуск stagger-анимации для списка элементов.
 *
 * @param {HTMLElement[]} elements - элементы для анимации
 * @param {object} options - опции (те же что у animateIn)
 * @returns {Promise<void[]>}
 */
export function staggerIn(elements, options = {}) {
  if (!elements.length) return Promise.resolve([])

  const delays = staggerChildren(elements.length)

  return Promise.all(
    elements.map((el, i) => animateIn(el, delays[i], options))
  )
}

/**
 * Хук для управления анимацией списка.
 *
 * @param {import('vue').Ref<HTMLElement[]> | HTMLElement[]} elementsRef - ref на массив элементов
 * @returns {object} { animate: Function }
 */
export function useListAnimation(elementsRef) {
  const animate = async (options = {}) => {
    const elements = elementsRef.value || elementsRef
    if (!elements?.length) return

    await staggerIn(elements, options)
  }

  return { animate }
}

/**
 * Класс для управления сложными анимациями (последовательные, параллельные).
 */
export class AnimationSequence {
  constructor() {
    this.steps = []
  }

  /** Добавить шаг анимации */
  add(fn, options = {}) {
    this.steps.push({ fn, options })
    return this
  }

  /** Запустить последовательно */
  async run() {
    for (const step of this.steps) {
      await step.fn(step.options)
    }
  }

  /** Запустить параллельно */
  async runParallel() {
    await Promise.all(this.steps.map(s => s.fn(s.options)))
  }

  /** Очистить последовательность */
  clear() {
    this.steps = []
  }
}

/**
 * Утилита для анимации высоты (slide down/up).
 *
 * @param {HTMLElement} el - элемент
 * @param {boolean} open - true = открыть (slide down), false = закрыть (slide up)
 * @param {number} duration - длительность в ms
 * @returns {Promise<void>}
 */
export async function slideToggle(el, open, duration = 250) {
  if (!el) return

  const reduced = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  if (reduced) {
    el.style.height = open ? 'auto' : '0'
    el.style.overflow = 'hidden'
    return
  }

  const startHeight = open ? 0 : el.scrollHeight
  const endHeight = open ? el.scrollHeight : 0

  el.style.height = `${startHeight}px`
  el.style.overflow = 'hidden'
  el.style.transition = `height ${duration}ms var(--ease-out, cubic-bezier(0.16, 1, 0.3, 1))`

  // Force reflow
  el.offsetHeight

  el.style.height = `${endHeight}px`

  return new Promise(resolve => {
    const handler = () => {
      el.removeEventListener('transitionend', handler)
      if (open) {
        el.style.height = 'auto'
      } else {
        el.style.height = '0'
      }
      el.style.overflow = ''
      el.style.transition = ''
      resolve()
    }
    el.addEventListener('transitionend', handler)

    // Fallback timeout
    setTimeout(handler, duration + 50)
  })
}

/**
 * Хук для простой анимации появления/исчезновения.
 *
 * @param {import('vue').Ref<boolean>} visible - видимость
 * @param {object} options - опции анимации
 * @returns {object} { style: ComputedRef<object>, classes: ComputedRef<string[]> }
 */
export function useTransition(visible, options = {}) {
  const {
    enterClass = 'animate-enter',
    leaveClass = 'animate-leave',
    enterActiveClass = 'animate-enter-active',
    leaveActiveClass = 'animate-leave-active',
    duration = 200,
  } = options

  const classes = computed(() => [
    visible.value ? enterClass : leaveClass,
    visible.value ? enterActiveClass : leaveActiveClass,
  ])

  const style = computed(() => ({
    transitionDuration: `${duration}ms`,
    transitionTimingFunction: 'var(--ease-out, cubic-bezier(0.16, 1, 0.3, 1))',
  }))

  return { classes, style }
}
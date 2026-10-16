/**
 * v-stagger directive для stagger-анимации списков.
 *
 * Использование:
 *   <ul v-stagger="items">
 *     <li v-for="item in items">{{ item }}</li>
 *   </ul>
 *
 * Опции:
 *   v-stagger="{ delay: 40, duration: 300, from: { opacity: 0, y: 16 } }"
 */

import { staggerChildren, animateIn } from '../utils/motion'

export default {
  mounted(el, binding) {
    const items = el.children
    if (!items.length) return

    const options = binding.value || {}
    const {
      delay = 40,
      duration = 300,
      from = { opacity: 0, transform: 'translateY(16px)' },
      to = { opacity: 1, transform: 'translateY(0)' },
      ease = 'var(--stagger-ease, cubic-bezier(0.16, 1, 0.3, 1))',
    } = options

    // Reduced motion check
    const reduced = typeof window !== 'undefined' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches

    if (reduced) {
      // Без анимации - просто показываем
      Array.from(items).forEach(item => {
        item.style.opacity = '1'
        item.style.transform = 'none'
      })
      return
    }

    // Инициализация: скрываем элементы
    Array.from(items).forEach(item => {
      item.style.opacity = '0'
      item.style.transform = 'translateY(16px)'
      item.style.transition = `opacity ${300}ms var(--ease-out), transform ${300}ms var(--ease-out)`
    })

    // Анимация с задержкой
    const staggerDelay = 40 // ms

    Array.from(items).forEach((item, index) => {
      setTimeout(() => {
        item.style.opacity = '1'
        item.style.transform = 'translateY(0)'
      }, index * 40)
    })

    // Сохраняем очистку
    el._staggerCleanup = () => {
      Array.from(items).forEach(item => {
        item.style.transition = ''
        item.style.opacity = ''
        item.style.transform = ''
      })
    }
  },

  unmounted(el) {
    if (el._staggerCleanup) {
      el._staggerCleanup()
      delete el._staggerCleanup
    }
  },

  updated(el, binding) {
    // Переинициализация при изменении данных
    if (binding.value !== binding.oldValue) {
      // Можно добавить реанимацию при изменении данных
    }
  },
}
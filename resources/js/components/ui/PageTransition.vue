<template>
  <transition
    name="page"
    mode="out-in"
    :duration="{ enter: pageEnterDur, leave: pageLeaveDur }"
    @before-enter="onBeforeEnter"
    @after-enter="onAfterEnter"
    @before-leave="onBeforeLeave"
    @after-leave="onAfterLeave"
  >
    <slot />
  </transition>
</template>

<script>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { prefersReducedMotion } from '../../utils/motion'

export default {
  name: 'PageTransition',

  props: {
    /** Имя перехода (можно переопределить для разных страниц) */
    name: { type: String, default: 'page' },
    /** Длительность входа (ms) */
    enterDuration: { type: Number, default: 200 },
    /** Длительность выхода (ms) */
    leaveDuration: { type: Number, default: 150 },
    /** Расстояние слайда (px) */
    slideDistance: { type: Number, default: 20 },
    /** Включить/выключить анимацию */
    enabled: { type: Boolean, default: true },
  },

  setup(props) {
    const route = useRoute()
    const { t } = useI18n()
    const reducedMotion = prefersReducedMotion()

    // Direction-aware: глубина маршрута по числу сегментов пути.
    // «Вглубь» (подробная страница) слайдит влево, «наверх» (к списку)
    // — вправо, на той же глубине — flat.
    const direction = ref('forward')
    const depthOf = (path) =>
      String(path || '').replace(/\?.*$/, '').replace(/\/+$/, '').split('/').filter(Boolean).length

    watch(
      () => route.fullPath,
      (newPath, oldPath) => {
        if (!oldPath) return
        const to = depthOf(newPath)
        const from = depthOf(oldPath)
        direction.value = to === from ? 'flat' : to > from ? 'forward' : 'back'
      },
      { flush: 'pre' },
    )

    // CSS переменные для длительностей (передаются в CSS через style binding)
    const pageEnterDur = computed(() => reducedMotion.value ? 0 : props.enterDuration)
    const pageLeaveDur = computed(() => reducedMotion.value ? 0 : props.leaveDuration)

    const transitionStyles = computed(() => ({
      '--page-slide-distance': `${props.slideDistance}px`,
      '--page-enter-dur': `${pageEnterDur.value}ms`,
      '--page-leave-dur': `${pageLeaveDur.value}ms`,
      '--page-direction': direction.value,
    }))

    // Переключатель reduced-motion (слушаем системную настройку)
    let mediaQuery = null
    let cleanup = () => {}

    onMounted(() => {
      if (typeof window !== 'undefined') {
        mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
        const handler = () => {
          // Пересчёт происходит через reactive computed
        }
        mediaQuery.addEventListener('change', handler)
        cleanup = () => mediaQuery.removeEventListener('change', handler)
      }
    })

    onUnmounted(() => {
      cleanup()
    })

    return {
      reducedMotion,
      pageEnterDur,
      pageLeaveDur,
      direction,
      transitionStyles,
    }
  },

  methods: {
    onBeforeEnter(el) {
      if (this.reducedMotion) return
      // Направление: forward — слайд влево (углубление), back — вправо,
      // flat — слабый слайд без смены глубины.
      const sign = this.direction === 'back' ? -1 : 1
      const dist = this.direction === 'flat' ? Math.round(this.slideDistance / 2) : this.slideDistance
      el.style.opacity = '0'
      el.style.transform = `translateX(${dist * sign}px)`
    },

    onAfterEnter(el) {
      el.style.opacity = ''
      el.style.transform = ''
    },

    onBeforeLeave(el) {
      if (this.reducedMotion) return
      el.style.opacity = '1'
      el.style.transform = 'translateX(0)'
    },

    onAfterLeave(el) {
      el.style.opacity = ''
      el.style.transform = ''
    },
  },
}
</script>

<style scoped>
/*
 * Базовые стили перехода страницы.
 *
 * Использует CSS переменные из tokens.css:
 * --page-enter-dur, --page-leave-dur, --page-slide-distance
 * --page-transition-ease, --page-transition-dur
 */

.page-enter-active,
.page-leave-active {
  transition:
    opacity var(--page-transition-dur) var(--page-transition-ease),
    transform var(--page-transition-dur) var(--page-transition-ease);
}

.page-enter-from {
  opacity: 0;
  transform: translateX(var(--page-slide-distance));
}

.page-leave-to {
  opacity: 0;
  transform: translateX(calc(var(--page-slide-distance) * -1));
}

/* Reduced motion: мгновенный переход */
@media (prefers-reduced-motion: reduce) {
  .page-enter-active,
  .page-leave-active {
    transition: none !important;
  }

  .page-enter-from,
  .page-leave-to {
    opacity: 1 !important;
    transform: none !important;
  }
}

/* Дополнительные варианты переходов */

/* Fade only */
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity var(--page-transition-dur) var(--page-transition-ease);
}

.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}

/* Scale + fade */
.page-scale-enter-active,
.page-scale-leave-active {
  transition:
    opacity var(--page-transition-dur) var(--page-transition-ease),
    transform var(--page-transition-dur) var(--ease-spring);
}

.page-scale-enter-from {
  opacity: 0;
  transform: scale(0.96);
}

.page-scale-leave-to {
  opacity: 0;
  transform: scale(1.02);
}

/* Slide up (для модалок/дробдаунов) */
.page-slide-up-enter-active,
.page-slide-up-leave-active {
  transition:
    opacity var(--page-transition-dur) var(--page-transition-ease),
    transform var(--page-transition-dur) var(--ease-out);
}

.page-slide-up-enter-from {
  opacity: 0;
  transform: translateY(16px);
}

.page-slide-up-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
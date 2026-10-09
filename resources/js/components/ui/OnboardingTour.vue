<template>
  <Teleport to="body">
    <div v-if="isOpen" class="u-tour" role="dialog" aria-modal="true" :aria-label="current.title">
      <!-- Затемнение с «дыркой» под целевой элемент. -->
      <div class="u-tour__overlay" @click="skip" />

      <!-- Подсветка цели: рамка вокруг активного шага. -->
      <div v-if="targetRect" class="u-tour__spotlight" :style="spotlightStyle" aria-hidden="true" />

      <div class="u-tour__popover" :style="popoverStyle" role="document">
        <div class="u-tour__header">
          <span class="u-tour__counter">{{ index + 1 }} / {{ steps.length }}</span>
          <button type="button" class="u-tour__skip" @click="skip">Пропустить</button>
        </div>

        <h3 class="u-tour__title">{{ current.title }}</h3>
        <p v-if="current.text" class="u-tour__text">{{ current.text }}</p>

        <div class="u-tour__footer">
          <v-btn v-if="index > 0" variant="text" size="small" @click="prev">Назад</v-btn>
          <span class="u-tour__spacer" />
          <v-btn color="primary" variant="flat" size="small" @click="next">
            {{ isLast ? 'Понятно' : 'Далее' }}
          </v-btn>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Обучающий тур по интерфейсу.
 *
 * Новичку и преподавателю неочевидно, где создавать курс, как
 * назначить группу, что такое банк вопросов. Тур по шагам подсвечивает
 * нужные элементы — без отдельной страницы справки и без «прочитайте
 * документацию».
 *
 * Показывается один раз: факт прохождения хранится в localStorage по
 * ключу tourKey. Сброс — удалить ключ (или вызвать reset() извне).
 *
 * Шаг — { target: CSS-селектор, title, text, placement: 'bottom'|'top'
 * |'left'|'right' }. Если target не найден, шаг пропускается.
 */
const props = defineProps({
  /** [{ target, title, text, placement }] */
  steps: { type: Array, default: () => [] },
  /** Ключ хранения в localStorage — уникальный для каждого тура. */
  tourKey: { type: String, default: 'lms-tour' },
  /** Запускать автоматически при монтировании, если не пройден. */
  autoStart: { type: Boolean, default: true },
})

const emit = defineEmits(['finish', 'skip', 'step'])

const isOpen = ref(false)
const index = ref(0)
const targetRect = ref(null)

const current = computed(() => props.steps[index.value] ?? {})
const isLast = computed(() => index.value >= props.steps.length - 1)

const storageKey = computed(() => `app:tour:${props.tourKey}`)

const spotlightStyle = computed(() => {
  if (!targetRect.value) return {}
  const pad = 6
  return {
    top: `${targetRect.value.top - pad}px`,
    left: `${targetRect.value.left - pad}px`,
    width: `${targetRect.value.width + pad * 2}px`,
    height: `${targetRect.value.height + pad * 2}px`,
  }
})

const popoverStyle = computed(() => {
  if (!targetRect.value) {
    // Центрируем, если цель не найдена — fallback.
    return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }
  }

  const rect = targetRect.value
  const gap = 14
  const width = 320
  const placement = current.value.placement || 'bottom'

  let top = rect.bottom + gap
  let left = rect.left + rect.width / 2 - width / 2

  if (placement === 'top') top = rect.top - gap - 160
  if (placement === 'left') {
    top = rect.top
    left = rect.left - width - gap
  }
  if (placement === 'right') {
    top = rect.top
    left = rect.right + gap
  }

  // Держим в пределах окна.
  left = Math.max(12, Math.min(left, window.innerWidth - width - 12))
  top = Math.max(12, Math.min(top, window.innerHeight - 180))

  return {
    top: `${top}px`,
    left: `${left}px`,
    width: `${width}px`,
  }
})

async function measure() {
  const selector = current.value.target
  if (!selector) {
    targetRect.value = null
    return
  }

  await nextTick()
  const el = document.querySelector(selector)

  if (!el) {
    // Цель ещё не в DOM (лента грузится асинхронно) — пробуем позже.
    targetRect.value = null
    return
  }

  targetRect.value = el.getBoundingClientRect()
}

function start() {
  if (!props.steps.length) return
  index.value = 0
  isOpen.value = true
  measure()
  emit('step', current.value, index.value)
}

function next() {
  if (isLast.value) {
    finish()
    return
  }
  index.value += 1
  measure()
  emit('step', current.value, index.value)
}

function prev() {
  if (index.value > 0) {
    index.value -= 1
    measure()
    emit('step', current.value, index.value)
  }
}

function finish() {
  isOpen.value = false
  markSeen()
  emit('finish')
}

function skip() {
  isOpen.value = false
  markSeen()
  emit('skip')
}

function markSeen() {
  try {
    localStorage.setItem(storageKey.value, '1')
  } catch {
    // Приватный режим — тур просто покажется снова, это не ошибка.
  }
}

/** Сбросить прогресс и запустить заново (кнопка «Показать тур»). */
function reset() {
  try {
    localStorage.removeItem(storageKey.value)
  } catch {
    // см. markSeen
  }
  start()
}

watch(index, () => measure())

function onResize() {
  if (isOpen.value) measure()
}

onMounted(() => {
  window.addEventListener('resize', onResize)
  window.addEventListener('scroll', onResize, true)

  if (props.autoStart) {
    let seen = false
    try {
      seen = localStorage.getItem(storageKey.value) === '1'
    } catch {
      seen = false
    }
    if (!seen) start()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  window.removeEventListener('scroll', onResize, true)
})

defineExpose({ start, reset })
</script>

<style scoped>
.u-tour {
  position: fixed;
  inset: 0;
  z-index: var(--z-toast);
}

.u-tour__overlay {
  position: fixed;
  inset: 0;
  background: rgba(16, 24, 40, 0.55);
}

.u-tour__spotlight {
  position: fixed;
  border: 2px solid var(--c-accent);
  border-radius: var(--radius-md);
  box-shadow: 0 0 0 9999px rgba(16, 24, 40, 0.55), var(--shadow-glow);
  pointer-events: none;
  transition: all var(--dur-base) var(--ease-out);
  z-index: 1;
}

.u-tour__popover {
  position: fixed;
  padding: var(--sp-4);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
  box-shadow: var(--shadow-4);
  z-index: 2;
}

.u-tour__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--sp-2);
}

.u-tour__counter {
  font-size: var(--fs-xs);
  font-weight: var(--fw-semibold);
  color: var(--c-accent);
}

.u-tour__skip {
  border: none;
  background: transparent;
  color: var(--c-text-muted);
  font: inherit;
  font-size: var(--fs-xs);
  cursor: pointer;
}

.u-tour__title {
  margin: 0 0 var(--sp-1);
  font-size: var(--fs-md);
  font-weight: var(--fw-semibold);
  color: var(--c-text);
}

.u-tour__text {
  margin: 0;
  font-size: var(--fs-sm);
  line-height: var(--lh-normal);
  color: var(--c-text-secondary);
}

.u-tour__footer {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
}

.u-tour__spacer {
  flex: 1;
}
</style>

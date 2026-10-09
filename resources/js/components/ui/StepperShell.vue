<template>
  <div class="u-stepper">
    <!-- Шаги. Нумерация и состояние — в одном месте, чтобы полоса
         прогресса и подписи не расходились. -->
    <ol class="u-stepper__nav" role="tablist">
      <li
        v-for="(step, i) in steps"
        :key="step.key"
        class="u-stepper__nav-item"
        :class="{
          'is-active': i === activeIndex,
          'is-done': i < activeIndex,
          'is-clickable': canNavigate(i),
        }"
      >
        <button
          type="button"
          class="u-stepper__nav-btn"
          role="tab"
          :aria-selected="i === activeIndex"
          :disabled="!canNavigate(i)"
          @click="goTo(i)"
        >
          <span class="u-stepper__dot">
            <v-icon v-if="i < activeIndex" icon="mdi-check" size="16" aria-hidden="true" />
            <template v-else>{{ i + 1 }}</template>
          </span>
          <span class="u-stepper__nav-text">
            <span class="u-stepper__nav-title">{{ step.title }}</span>
            <span v-if="step.subtitle" class="u-stepper__nav-subtitle">{{ step.subtitle }}</span>
          </span>
        </button>
        <span v-if="i < steps.length - 1" class="u-stepper__connector" aria-hidden="true" />
      </li>
    </ol>

    <div class="u-stepper__body">
      <slot :name="currentStep.key" :step="currentStep">
        <slot :step="currentStep" />
      </slot>
    </div>

    <div class="u-stepper__foot">
      <v-btn
        v-if="activeIndex > 0"
        variant="text"
        :disabled="busy"
        @click="prev"
      >
        Назад
      </v-btn>

      <span class="u-stepper__spacer" />

      <slot name="footer" :step="currentStep" :next="next" :busy="busy">
        <v-btn
          v-if="!isLast"
          color="primary"
          variant="flat"
          :loading="busy"
          @click="next"
        >
          Далее
        </v-btn>
        <v-btn
          v-else
          color="primary"
          variant="flat"
          :loading="busy"
          @click="finish"
        >
          {{ finishText }}
        </v-btn>
      </slot>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

/**
 * Каркас многошагового мастера (wizard).
 *
 * Конструктор курса, создание экзамена и настройки раньше были
 * отдельными страницами: пользователь заполнял первую форму, жал
 * «Сохранить», попадал на вторую, терял контекст и не видел, сколько
 * шагов осталось. StepperShell собирает шаги в один экран и даёт им
 * общую навигацию, прогресс и проверку на каждом переходе.
 *
 * Валидация выполняется ПРИ ПЕРЕХОДЕ, а не при финальной отправке:
 * ошибка на шаге 2 всплывает на шаге 2, а не после «Готово», когда
 * пользователь уже забыл, что там вводил.
 *
 * beforeNext — функция (fromKey, toKey) => boolean | Promise<boolean>.
 * Она решает, можно ли уйти вперёд; сам шаг при этом остаётся
 * управляемым снаружи.
 */
const props = defineProps({
  /** [{ key, title, subtitle }] */
  steps: { type: Array, required: true },
  /** Активный шаг (индекс). Поддерживает v-model. */
  modelValue: { type: Number, default: 0 },
  /** Текст кнопки на последнем шаге. */
  finishText: { type: String, default: 'Готово' },
  /** Идёт сохранение/проверка — блокирует навигацию. */
  busy: { type: Boolean, default: false },
  /**
   * Разрешить клики по уже пройденным шагам. Возврат «назад» по
   * шагам никогда не блокируется — блокируется только прыжок вперёд.
   */
  clickableCompleted: { type: Boolean, default: true },
  /**
   * Гард перехода вперёд. Может быть async.
   * @type {(fromKey: string, toKey: string) => boolean | Promise<boolean>}
   */
  beforeNext: { type: Function, default: null },
})

const emit = defineEmits(['update:modelValue', 'finish', 'step-change'])

const activeIndex = ref(props.modelValue)

watch(() => props.modelValue, (v) => {
  activeIndex.value = v
})

watch(activeIndex, (v) => {
  emit('update:modelValue', v)
  emit('step-change', props.steps[v]?.key, v)
})

const currentStep = computed(() => props.steps[activeIndex.value] ?? props.steps[0])
const isLast = computed(() => activeIndex.value >= props.steps.length - 1)

function canNavigate(i) {
  if (props.busy) return false
  if (i <= activeIndex.value) return true
  return props.clickableCompleted && false
}

async function prev() {
  if (activeIndex.value > 0) activeIndex.value -= 1
}

async function next() {
  if (isLast.value || props.busy) return

  const from = props.steps[activeIndex.value]?.key
  const to = props.steps[activeIndex.value + 1]?.key

  if (props.beforeNext) {
    const ok = await props.beforeNext(from, to)
    if (ok === false) return
  }

  activeIndex.value += 1
}

async function goTo(i) {
  if (!canNavigate(i) || i === activeIndex.value) return
  // Назад разрешаем свободно; вперёд — только по одному шагу.
  if (i < activeIndex.value) activeIndex.value = i
}

function finish() {
  if (!props.busy) emit('finish')
}

defineExpose({ next, prev, goTo, activeIndex })
</script>

<style scoped>
.u-stepper {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
}

.u-stepper__nav {
  display: flex;
  align-items: flex-start;
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.u-stepper__nav-item {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.u-stepper__nav-btn {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
  padding: var(--sp-2);
  border: none;
  background: transparent;
  color: var(--c-text-muted);
  font: inherit;
  text-align: left;
  cursor: default;
}

.is-clickable .u-stepper__nav-btn {
  cursor: pointer;
}

.u-stepper__dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  flex-shrink: 0;
  border: 2px solid var(--c-border-strong);
  border-radius: var(--radius-pill);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  transition: background-color var(--dur-base) var(--ease),
    border-color var(--dur-base) var(--ease), color var(--dur-base) var(--ease);
}

.is-active .u-stepper__dot {
  background: var(--c-primary);
  border-color: var(--c-primary);
  color: var(--c-on-primary);
}

.is-done .u-stepper__dot {
  background: var(--c-success-soft);
  border-color: var(--c-success);
  color: var(--c-success);
}

.u-stepper__nav-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.u-stepper__nav-title {
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--c-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.is-active .u-stepper__nav-title {
  color: var(--c-text);
  font-weight: var(--fw-semibold);
}

.u-stepper__nav-subtitle {
  font-size: var(--fs-2xs);
  color: var(--c-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.u-stepper__connector {
  flex: 1;
  height: 2px;
  min-width: var(--sp-2);
  margin: 0 var(--sp-1);
  background: var(--c-border);
}

.is-done + .u-stepper__nav-item .u-stepper__connector,
.u-stepper__nav-item.is-done .u-stepper__connector {
  background: var(--c-success);
}

.u-stepper__body {
  min-height: 120px;
}

.u-stepper__foot {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding-top: var(--sp-4);
  border-top: 1px solid var(--c-border);
}

.u-stepper__spacer {
  flex: 1;
}

@media (max-width: 720px) {
  .u-stepper__nav-subtitle {
    display: none;
  }

  .u-stepper__nav-title {
    font-size: var(--fs-xs);
  }
}
</style>

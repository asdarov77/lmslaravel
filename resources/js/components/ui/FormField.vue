<template>
  <div
    class="u-field"
    :class="{ 'u-field--invalid': !!error, 'u-field--disabled': disabled }"
    :data-field="name || undefined"
  >
    <label v-if="label" class="u-field__label" :class="{ 'u-field__label--required': required }" :for="fieldId">
      {{ label }}
    </label>

    <div class="u-field__control">
      <slot :id="fieldId" :invalid="!!error" />
    </div>

    <div class="u-field__meta">
      <p v-if="error" class="u-field__error">{{ error }}</p>
      <p v-else-if="hint" class="u-field__hint">{{ hint }}</p>
      <span v-if="counter" class="u-field__counter" :class="{ 'u-field__counter--over': counter.current > counter.max }">
        {{ counter.current }} / {{ counter.max }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { useId } from 'vue'

/**
 * Обёртка поля формы: подпись, подсказка, ошибка и счётчик символов
 * в одном месте.
 *
 * Раньше каждая форма решала это сама и по-разному: где-то label
 * писался пропом самого v-text-field, где-то отдельным <label> над
 * ним, ошибка рисовалась либо через :error-messages поля, либо общим
 * v-alert вверху формы со списком всех ошибок. Пользователь видел
 * «Ошибка: название уже занято» в шапке и не понимал, какое поле
 * виновато.
 *
 * Здесь ошибка всегда стоит под своим полем. Слот получает id —
 * чтобы связать <label for> с полем и не плодить дубли — и invalid,
 * чтобы поле могло подсветиться тем же состоянием.
 */
const props = defineProps({
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  /** Текст ошибки. Пусто — поле валидно. */
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  /**
   * Счётчик символов: { current, max }. Показывается только когда
   * задан — не всем полям нужен лимит.
   */
  counter: { type: Object, default: null },
  /** Явный id поля; иначе генерируется автоматически. */
  id: { type: String, default: '' },
  /**
   * Имя поля в схеме useForm. Попадает в data-field на корне — по
   * нему useForm находит первое невалидное поле и прокручивает к нему.
   */
  name: { type: String, default: '' },
})

const fieldId = props.id || `field-${useId()}`
</script>

<style scoped>
.u-field {
  margin-bottom: var(--sp-4);
}

.u-field__control {
  position: relative;
}

/* Ошибка приходит извне (validation), поэтому подсветку границы
   задаём обёртке, а не пытаемся пробить scoped-стили вглубь Vuetify. */
.u-field--invalid :deep(.v-field) {
  border-color: var(--c-danger);
}

.u-field__meta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--sp-2);
  min-height: 18px;
}

.u-field__counter {
  margin: var(--sp-1) 0 0;
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.u-field__counter--over {
  color: var(--c-danger);
  font-weight: var(--fw-semibold);
}
</style>

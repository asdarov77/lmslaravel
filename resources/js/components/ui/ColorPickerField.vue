<template>
  <div class="color-picker-field">
    <label v-if="label" :for="fieldId" class="u-field__label">
      {{ label }}
      <span v-if="required" class="u-field__label--required">*</span>
    </label>

    <div class="color-picker-field__input">
      <input
        :id="fieldId"
        type="color"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        class="color-picker-field__color-input"
        data-test="color-picker-input"
      />

      <input
        :id="fieldId + '_text'"
        type="text"
        :value="modelValue"
        @input="onTextInput"
        class="color-picker-field__text-input"
        placeholder="#rrggbb"
        data-test="color-picker-text"
      />
    </div>

    <p v-if="hint" class="u-field__hint">{{ hint }}</p>
  </div>
</template>

<script>
export default {
  name: 'ColorPickerField',

  props: {
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    modelValue: { type: String, default: '#1976d2' },
    required: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  data() {
    return {
      fieldId: `color-picker-${Math.random().toString(36).slice(2)}`,
    }
  },

  methods: {
    onTextInput(event) {
      const value = event.target.value.trim()
      // Валидация: #rrggbb
      if (/^#[0-9a-fA-F]{6}$/.test(value)) {
        this.$emit('update:modelValue', value)
      }
    },
  },
}
</script>

<style scoped>
.color-picker-field__input {
  align-items: center;
  display: flex;
  gap: 8px;
}

.color-picker-field__color-input {
  appearance: none;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  height: 40px;
  width: 60px;
}

.color-picker-field__color-input::-webkit-color-swatch-wrapper {
  padding: 0;
}

.color-picker-field__color-input::-webkit-color-swatch {
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 4px;
}

.color-picker-field__text-input {
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 6px;
  flex: 1;
  font-family: monospace;
  font-size: 0.875rem;
  max-width: 140px;
  padding: 8px 12px;
}
</style>
<template>
  <div class="logo-upload">
    <label :for="fieldId" class="u-field__label">
      {{ label }}
      <span v-if="required" class="u-field__label--required">*</span>
    </label>

    <div class="logo-upload__preview" v-if="previewSrc">
      <img :src="previewSrc" alt="" class="logo-upload__img" />
      <button
        type="button"
        class="logo-upload__remove"
        @click="remove"
        :aria-label="$t('whiteLabel.remove')"
        data-test="logo-remove"
      >
        <v-icon size="20">mdi-close</v-icon>
      </button>
    </div>

    <v-btn
      v-else
      outlined
      :color="required ? 'primary' : 'default'"
      @click="pick"
      class="logo-upload__pick-btn"
      data-test="logo-pick"
    >
      {{ $t('whiteLabel.pick') }}
    </v-btn>

    <input
      :id="fieldId"
      ref="fileInput"
      type="file"
      :accept="accept"
      class="d-none"
      @change="onFileSelected"
    />

    <p v-if="hint" class="u-field__hint">{{ hint }}</p>
  </div>
</template>

<script>
export default {
  name: 'LogoUpload',

  props: {
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    modelValue: { type: File, default: null },
    currentUrl: { type: String, default: null },
    removeFlag: { type: Boolean, default: false },
    accept: { type: String, default: 'image/png,image/jpeg,image/svg+xml' },
    required: { type: Boolean, default: false },
  },

  emits: ['update:modelValue', 'update:removeFlag'],

  data() {
    return {
      fieldId: `logo-upload-${Math.random().toString(36).slice(2)}`,
      previewSrc: null,
    }
  },

  computed: {
    previewSrc() {
      // Приоритет: новый файл > текущий URL
      if (this.modelValue) {
        return URL.createObjectURL(this.modelValue)
      }
      return this.currentUrl
    },
  },

  watch: {
    modelValue(newFile) {
      if (newFile) {
        this.$emit('update:removeFlag', false)
      }
    },
    removeFlag(val) {
      if (val && !this.modelValue) {
        // Удаление без нового файла — сбрасываем флаг после уведомления родителя
        this.$emit('update:removeFlag', false)
      }
    },
  },

  mounted() {
    // Начальный превью от currentUrl
    if (!this.modelValue && this.currentUrl) {
      // previewSrc computed handles this
    }
  },

  methods: {
    pick() {
      this.$refs.fileInput.click()
    },

    onFileSelected(event) {
      const file = event.target.files?.[0]
      event.target.value = '' // Сброс для повторного выбора того же файла

      if (!file) return

      // Валидация размера (512KB для логотипов, 64KB для фавикона)
      const maxSize = this.accept.includes('ico') ? 64 * 1024 : 512 * 1024
      if (file.size > maxSize) {
        // TODO: показать ошибку через toast/notification
        console.warn('File too large:', file.size, 'max:', maxSize)
        return
      }

      this.$emit('update:modelValue', file)
    },

    remove() {
      this.$emit('update:modelValue', null)
      this.$emit('update:removeFlag', true)
    },
  },
}
</script>

<style scoped>
.logo-upload__preview {
  align-items: center;
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
  position: relative;
}

.logo-upload__img {
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 8px;
  height: 64px;
  object-fit: contain;
  width: auto;
  max-width: 200px;
}

.logo-upload__remove {
  background: rgba(0, 0, 0, 0.6);
  border-radius: 50%;
  color: #fff;
  height: 28px;
  position: absolute;
  right: -10px;
  top: -10px;
  width: 28px;
}

.logo-upload__pick-btn {
  min-width: 160px;
}
</style>
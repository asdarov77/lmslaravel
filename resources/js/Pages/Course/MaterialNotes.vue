<template>
  <div class="cm-notes" v-if="!isLoading">
    <v-alert v-if="error" type="error" dense text class="mb-3" data-test="notes-error">
      {{ error }}
    </v-alert>

    <v-sheet class="cm-notes__editor" elevation="1">
      <div class="cm-notes__header">
        <h4 class="cm-notes__title">{{ $t('courseManifest.notes') }}</h4>
        <span class="cm-notes__status" v-if="lastSaved">
          {{ $t('courseManifest.notesSaved', { time: lastSaved }) }}
        </span>
      </div>

      <v-textarea
        v-model="content"
        :label="$t('courseManifest.notesLabel')"
        :placeholder="$t('courseManifest.notesPlaceholder')"
        :counter="5000"
        rows="8"
        :loading="saving"
        @input="onInput"
        :disabled="saving"
        data-test="notes-textarea"
      />

      <div class="cm-notes__footer">
        <span class="cm-notes__counter">
          {{ content.length }} / 5000
        </span>
        <div class="cm-notes__actions">
          <v-btn
            variant="text"
            @click="clearNotes"
            :disabled="!content || saving"
            data-test="notes-clear"
          >
            {{ $t('courseManifest.notesClear') }}
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            @click="saveNotes"
            :disabled="!content.trim() || saving"
            data-test="notes-save"
          >
            {{ $t('courseManifest.notesSave') }}
          </v-btn>
        </div>
      </div>
    </v-sheet>
  </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import $api from '../../api/httpClient'

export default {
  name: 'MaterialNotes',

  props: {
    materialId: { type: [Number, String], required: true },
    courseId: { type: [Number, String], required: true },
  },

  emits: ['saved', 'error'],

  setup(props, { emit }) {
    const { t } = useI18n()

    const content = ref('')
    const saving = ref(false)
    const error = ref(null)
    const lastSaved = ref(null)

    const canSave = computed(() => content.value.trim().length > 0 && !saving.value)

    let debounceTimer = null

    const loadNote = async () => {
      try {
        const response = await $api.get(`/api/materials/${props.materialId}/note`)
        const data = response?.data?.data
        if (data) {
          content.value = data.content ?? ''
        }
      } catch (e) {
        console.debug('[MaterialNotes] loadNote:', e?.response?.data?.message || e.message)
      }
    }

    const saveNotes = async () => {
      if (!content.value.trim()) return

      saving.value = true

      try {
        const response = await $api.put(`/api/materials/${props.materialId}/note`, {
          content: content.value,
        })

        const data = response?.data?.data
        if (data) {
          lastSaved.value = data.updated_at ? new Date(data.updated_at).toLocaleTimeString() : new Date().toLocaleTimeString()
          emit('saved', data)
        }
      } catch (e) {
        error.value = e?.response?.data?.message || t('courseManifest.notesSaveError')
      } finally {
        saving.value = false
      }
    }

    const clearNotes = async () => {
      content.value = ''

      try {
        await $api.delete(`/api/materials/${props.materialId}/note`)
        lastSaved.value = null
      } catch (e) {
        console.warn('[MaterialNotes] clear:', e?.response?.data?.message || e.message)
      }
    }

    const onInput = () => {
      if (debounceTimer) clearTimeout(debounceTimer)

      debounceTimer = setTimeout(() => {
        if (content.value.trim()) {
          saveNotes()
        }
      }, 2000)
    }

    const debouncedSave = () => {
      if (debounceTimer) {
        clearTimeout(debounceTimer)
        if (content.value.trim()) {
          saveNotes()
        }
      }
    }

    onMounted(() => {
      loadNote()
    })

    onUnmounted(() => {
      if (debounceTimer) clearTimeout(debounceTimer)
    })

    return {
      content,
      saving,
      error,
      lastSaved,
      saveNotes,
      clearNotes,
      onInput,
    }
  },
}
</script>

<style scoped>
/*
  min-width: 0 обязателен: блок лежит во flex-панели шириной ~300px,
  а v-textarea по умолчанию не сжимается уже своей минимальной
  ширины и выталкивал панель за край (проверено: scrollWidth 320 при
  clientWidth 299).
*/
.cm-notes {
  margin-top: var(--sp-6);
  min-width: 0;
  max-width: 100%;
}

.cm-notes__editor {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--c-radius-md);
  overflow: hidden;
}

.cm-notes__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--c-border);
  background: var(--c-surface-2);
}

.cm-notes__title {
  font-size: var(--fs-lg);
  font-weight: 600;
  color: var(--c-text);
}

.cm-notes__status {
  font-size: var(--fs-xs);
  color: var(--c-success);
  font-weight: 500;
}

.cm-notes__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-3) var(--sp-4);
  border-top: 1px solid var(--c-border);
  background: var(--c-surface-2);
}

.cm-notes__counter {
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
  font-family: var(--font-mono);
}

.cm-notes__actions {
  display: flex;
  gap: var(--sp-2);
  /*
   * Перенос по строкам: панель содержания курса — 300px, а «Очистить» и
   * «Сохранить» вместе с зазором в неё не помещались и вылезали за край
   * (у панели появлялась горизонтальная прокрутка).
   */
  flex-wrap: wrap;
  min-width: 0;
}
</style>
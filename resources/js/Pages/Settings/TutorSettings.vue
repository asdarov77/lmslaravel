<template>
  <div class="tutor-settings">
    <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('settings.tutor.intro') }}</p>

    <v-alert v-if="alert" :type="alert.type" density="compact" class="mb-4">{{ alert.text }}</v-alert>

    <v-switch
      :model-value="enabled"
      :label="$t('settings.tutor.switchLabel')"
      :hint="$t('settings.tutor.switchHint')"
      persistent-hint
      color="primary"
      :loading="saving"
      data-test="tutor-switch"
      @update:model-value="save"
    />

    <!-- Состояние движка: переключатель включает тренажёр, но не
         запускает модель. Если её нет, включённый тренажёр — это
         «ничего не работает», и сказать об этом нужно здесь. -->
    <v-card class="mt-6" data-test="tutor-engine-card">
      <v-card-title class="text-subtitle-1">{{ $t('settings.tutor.engine') }}</v-card-title>
      <v-card-text>
        <v-alert v-if="loading" type="info" density="compact">{{ $t('common.loading') }}</v-alert>

        <template v-else-if="health">
          <v-alert v-if="!health.available" type="warning" density="compact" class="mb-3">
            {{ $t('settings.tutor.engineDown') }}
          </v-alert>

          <DataTable
            :columns="healthColumns"
            :rows="healthRows"
            :row-key="(row) => row.id"
            :caption="$t('settings.tutor.diagnostics')"
            class="mt-3"
          >
            <template #cell-value="{ row }">
              <template v-if="row.id === 'model'">
                <span data-test="tutor-model">{{ health.model || '—' }}</span>
                <v-chip
                  v-if="health.available"
                  size="x-small"
                  class="ml-2"
                  :color="health.model_present ? 'success' : 'warning'"
                >
                  {{ health.model_present ? $t('settings.tutor.installed') : $t('settings.tutor.notInstalled') }}
                </v-chip>
              </template>
              <template v-else>{{ row.value }}</template>
            </template>
          </DataTable>

          <v-alert v-if="health.available && !health.model_present" type="info" density="compact" class="mt-3">
            <code>ollama pull {{ health.model }}</code>
          </v-alert>
        </template>

        <v-alert v-else type="warning" density="compact">{{ $t('settings.tutor.noProbe') }}</v-alert>
      </v-card-text>
    </v-card>

    <v-card v-if="canManage" class="mt-4">
      <v-card-title class="text-subtitle-1">{{ $t('settings.tutor.materials') }}</v-card-title>
      <v-card-text>
        <v-alert v-if="!enabled" type="info" density="compact" class="mb-3">
          {{ $t('settings.tutor.enableFirst') }}
        </v-alert>

        <v-list v-if="materials.length" data-test="tutor-admin-materials">
          <v-list-item
            v-for="material in materials"
            :key="material.id"
            :title="material.title"
            data-test="tutor-admin-material"
          >
            <template #subtitle>
              <span>{{ material.course_title || material.id }}</span>
              <span v-if="material.category_title" class="ml-2 text-medium-emphasis">
                · {{ material.category_title }}
              </span>
            </template>
            <template #append>
              <v-chip size="small" class="mr-2" :color="material.ready ? 'success' : 'warning'">
                {{ statusLabel(material.status) }}
              </v-chip>
              <span class="text-caption mr-3">{{ material.chunks_count }}</span>
            </template>
          </v-list-item>
        </v-list>

        <p v-else class="text-body-2 text-medium-emphasis">{{ $t('settings.tutor.noMaterials') }}</p>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>
/**
 * Настройки тренажёра.
 *
 * Страница делает две разные вещи, и их важно не путать:
 *  - галка включает ТРЕНАЖЁР (значение settings.tutor_enabled);
 *  - таблица показывает СОСТОЯНИЕ ДВИЖКА (доступен ли Ollama, есть ли
 *    нужная модель).
 *
 * Второе — диагностика, а не управление: включённый тренажёр при
 * неработающем движке не «сломан», он просто не может работать, и без
 * этой таблицы администратор видит только пустой список материалов.
 */
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import DataTable from '../../components/ui/DataTable.vue'

export default {
  name: 'TutorSettings',
  components: { DataTable },
  data() {
    return {
      enabled: false,
      canManage: false,
      health: null,
      materials: [],
      loading: true,
      saving: false,
      alert: null,
    }
  },
  computed: {
    healthColumns() {
      return [
        { key: 'label', title: this.$t('settings.tutor.url'), width: '40%' },
        { key: 'value', title: this.$t('settings.tutor.diagnostics') },
      ]
    },
    healthRows() {
      if (!this.health) return []
      return [
        { id: 'url', label: this.$t('settings.tutor.url'), value: this.health.url || '—' },
        { id: 'model', label: this.$t('settings.tutor.model'), value: this.health.model || '—' },
        { id: 'models', label: this.$t('settings.tutor.models'), value: (this.health.models || []).length },
        { id: 'embeddings', label: this.$t('settings.tutor.embeddings'), value: this.health.embeddings ? '✓' : '—' },
        {
          id: 'queue',
          label: this.$t('settings.tutor.queue'),
          value: this.health.async ? this.$t('settings.tutor.queueAsync') : this.$t('settings.tutor.queueSync'),
        },
      ]
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true

      try {
        // Право manage читаем из профиля: экран материалов методиста
        // доступен не всем, кто видит настройки.
        const me = unwrapResponse(await $api.get('/api/v1/me'))
        const slugs = me?.permission_slugs ?? []
        this.canManage = slugs.includes('tutor.manage')

        const state = unwrapResponse(await $api.get('/api/settings/tutor'))
        this.enabled = Boolean(state?.enabled)

        await this.probe()
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      } finally {
        this.loading = false
      }
    },

    /**
     * Диагностика движка.
     *
     * Отдельный эндпоинт тренажёра, а не его /health: тот требует
     * tutor.use, которого у администратора может не быть, хотя право
     * на настройку есть.
     */
    async probe() {
      try {
        this.health = unwrapResponse(await $api.get('/api/settings/tutor/probe'))
      } catch (error) {
        this.health = null
      }
    },

    async save(value) {
      const previous = this.enabled

      this.saving = true
      this.alert = null

      try {
        const state = unwrapResponse(await $api.put('/api/settings/tutor', { enabled: value }))

        // Ответ сервера — источник истины, как и в переключателе
        // раздачи материалов.
        this.enabled = Boolean(state?.enabled ?? previous)

        if (this.enabled) {
          await this.probe()
        }
      } catch (error) {
        this.enabled = previous
        this.alert = { type: 'error', text: this.errorText(error) }
      } finally {
        this.saving = false
      }
    },

    statusLabel(status) {
      return this.$t(`settings.tutor.status.${status ?? 'pending'}`)
    },

    errorText(error) {
      return (
        error?.response?.data?.error?.message ||
        error?.message ||
        this.$t('settings.tutor.genericError')
      )
    },
  },
}
</script>
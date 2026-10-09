<template>
  <div class="content-delivery">
    <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('settings.delivery.intro') }}</p>

    <v-alert v-if="alert" :type="alert.type" density="compact" class="mb-4">{{ alert.text }}</v-alert>

    <v-switch
      :model-value="nginx"
      :label="$t('settings.delivery.switchLabel')"
      :hint="$t('settings.delivery.switchHint')"
      persistent-hint
      color="primary"
      :loading="saving"
      :disabled="loading"
      data-test="delivery-switch"
      @update:model-value="save"
    />

    <v-alert
      v-if="nginx"
      type="warning"
      density="compact"
      class="mt-4"
      data-test="delivery-nginx-warning"
    >
      {{ $t('settings.delivery.nginxWarning') }}
    </v-alert>

    <DataTable
      class="mt-6"
      :title="$t('settings.delivery.stateTitle')"
      :columns="stateColumns"
      :rows="stateRows"
      :row-key="(row) => row.id"
      :caption="$t('settings.delivery.stateTitle')"
    >
      <template #cell-value="{ row }">
        <span v-if="row.id === 'current'" data-test="delivery-current">{{ mode || '—' }}</span>
        <span v-else-if="row.id === 'source'" data-test="delivery-source">
          {{ $t(`settings.delivery.source_${source}`) }}
        </span>
        <code v-else>{{ internal }}</code>
      </template>
    </DataTable>

    <p class="text-caption text-medium-emphasis mt-4">
      {{ $t('settings.delivery.workerHint') }}
    </p>
  </div>
</template>

<script>
/**
 * Переключатель способа раздачи материалов курсов.
 *
 * php    — файл читает и отдаёт Laravel. Режим по умолчанию: работает
 *          везде, где есть PHP, и не требует ничего от веб-сервера.
 * nginx  — Laravel проверяет подпись и отвечает пустым телом с
 *          X-Accel-Redirect, файл отдаёт nginx сам (sendfile, без
 *          копирования в userspace, ни одного занятого worker'а PHP).
 *
 * Значение живёт в settings.content_delivery и перекрывает
 * PRIVATE_CONTENT_DELIVERY из .env. Страница требует settings.manage.
 *
 * Переключение уходит на сервер сразу, без кнопки «Сохранить»: режим
 * влияет на выдачу материала всем сразу, и состояние «галка включена,
 * а по факту php» — худший вид расхождения. Состояние галки берётся из
 * ответа сервера, а не из предположения клиента: если запись не
 * сохранилась, галка возвращается обратно.
 */
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import DataTable from '../../components/ui/DataTable.vue'

export default {
  name: 'ContentDeliverySettings',
  components: { DataTable },
  data() {
    return {
      loading: true,
      saving: false,
      mode: '',
      source: '',
      internal: '',
      alert: null,
    }
  },
  computed: {
    stateColumns() {
      return [
        { key: 'label', title: this.$t('settings.delivery.current'), width: '40%' },
        { key: 'value', title: this.$t('settings.delivery.stateTitle') },
      ]
    },
    stateRows() {
      return [
        { id: 'current', label: this.$t('settings.delivery.current'), value: this.mode },
        { id: 'source', label: this.$t('settings.delivery.source'), value: this.source },
        { id: 'internal', label: this.$t('settings.delivery.internal'), value: this.internal },
      ]
    },
    nginx() {
      return this.mode === 'nginx'
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      this.alert = null

      try {
        const data = unwrapResponse(await $api.get('/api/settings/content-delivery')) || {}

        this.mode = data.mode || 'php'
        this.source = data.source || 'config'
        this.internal = data.accel_internal || ''
      } catch (error) {
        this.alert = {
          type: 'error',
          text: error?.response?.data?.error?.message || this.$t('settings.delivery.loadFailed'),
        }
      } finally {
        this.loading = false
      }
    },

    async save(value) {
      const mode = value ? 'nginx' : 'php'
      const previous = this.mode

      this.saving = true
      this.alert = null

      try {
        const data = unwrapResponse(await $api.put('/api/settings/content-delivery', { mode })) || {}

        // Источник истины — ответ сервера, а не то, что клиент хотел.
        this.mode = data.mode || previous
        this.source = data.source || 'settings'
      } catch (error) {
        // Откат обязателен: иначе галка осталась бы включённой при
        // php-режиме, и это выглядело бы как «включено, но не работает».
        this.mode = previous
        this.alert = {
          type: 'error',
          text: error?.response?.data?.error?.message || this.$t('settings.delivery.saveFailed'),
        }
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

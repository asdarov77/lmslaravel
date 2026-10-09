<template>
  <v-container class="tutor-main">
    <PageHeader :title="$t('tutor.title')" />
    <p class="text-body-2 text-medium-emphasis mb-6">{{ $t('tutor.intro') }}</p>

    <!-- Состояние движка: показывается первым, потому что без него
         остальная страница бессмысленна. -->
    <v-alert
      v-if="health && !health.enabled"
      type="info"
      density="compact"
      class="mb-4"
      data-test="tutor-disabled"
    >
      {{ $t('tutor.disabled') }}
    </v-alert>

    <v-alert
      v-else-if="health && health.enabled && !health.available"
      type="warning"
      density="compact"
      class="mb-4"
      data-test="tutor-engine-down"
    >
      <div>{{ $t('tutor.engineDown') }}</div>
      <div v-if="health.error" class="text-caption mt-1">{{ health.error }}</div>
    </v-alert>

    <v-alert
      v-else-if="health && health.enabled && health.model_present === false"
      type="warning"
      density="compact"
      class="mb-4"
      data-test="tutor-model-missing"
    >
      {{ $t('tutor.modelMissing', { model: health.model }) }}
    </v-alert>

    <v-alert v-if="alert" :type="alert.type" density="compact" class="mb-4">{{ alert.text }}</v-alert>

    <v-row>
      <v-col cols="12" md="7">
        <v-card>
          <v-card-title>{{ $t('tutor.materials') }}</v-card-title>
          <v-card-text>
            <v-list v-if="materials.length" data-test="tutor-materials">
              <v-list-item
                v-for="material in materials"
                :key="material.id"
                :title="material.title"
                data-test="tutor-material"
              >
                <template #subtitle>
                  <span>{{ material.course_title }}</span>
                  <!--
                    Специальность показывается явно: материалы приходят по
                    парам (курс, специальность), и без подписи два материала
                    одного курса выглядели бы одинаково — пользователь не
                    понял бы, чей это материал.
                  -->
                  <span v-if="material.category_title" class="ml-2 text-medium-emphasis">
                    · {{ material.category_title }}
                  </span>
                </template>
                <template #append>
                  <span class="text-caption text-medium-emphasis mr-3">
                    {{ $t('tutor.chunks', { n: material.chunks_count }) }}
                  </span>
                  <v-btn
                    size="small"
                    color="primary"
                    variant="flat"
                    :loading="startingId === material.id"
                    data-test="tutor-start"
                    @click="start(material)"
                  >
                    {{ $t('tutor.start') }}
                  </v-btn>
                </template>
              </v-list-item>
            </v-list>

            <EmptyState
              v-else-if="!loadingMaterials"
              :title="$t('tutor.noMaterialsTitle')"
              :text="$t('tutor.noMaterialsText')"
            />

            <v-skeleton-loader v-if="loadingMaterials" type="list-item-three-line" />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card class="mb-4">
          <v-card-title>{{ $t('tutor.stats') }}</v-card-title>
          <v-card-text>
            <v-row v-if="stats" dense data-test="tutor-stats">
              <v-col cols="6">
                <div class="dash__stat">
                  <span class="dash__stat-value">{{ stats.percent }}%</span>
                  <span class="dash__stat-label">{{ $t('tutor.correctPercent') }}</span>
                </div>
              </v-col>
              <v-col cols="6">
                <div class="dash__stat">
                  <span class="dash__stat-value">{{ stats.answers }}</span>
                  <span class="dash__stat-label">{{ $t('tutor.answers') }}</span>
                </div>
              </v-col>
              <v-col cols="6">
                <div class="dash__stat">
                  <span class="dash__stat-value">{{ stats.sessions }}</span>
                  <span class="dash__stat-label">{{ $t('tutor.sessions') }}</span>
                </div>
              </v-col>
              <v-col cols="6">
                <div class="dash__stat">
                  <span class="dash__stat-value">{{ stats.correct }}</span>
                  <span class="dash__stat-label">{{ $t('tutor.correct') }}</span>
                </div>
              </v-col>
            </v-row>

            <v-skeleton-loader v-if="!stats" type="image" />
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title>{{ $t('tutor.engine') }}</v-card-title>
          <v-card-text>
            <DataTable
              v-if="health"
              :title="$t('tutor.engineState')"
              :columns="healthColumns"
              :rows="healthRows"
              :row-key="(row) => row.id"
              :caption="$t('tutor.engineState')"
            >
              <template #cell-value="{ row }">
                <span v-if="row.id === 'model'" data-test="tutor-model">{{ health.model || '—' }}</span>
                <template v-else>{{ row.value }}</template>
              </template>
            </DataTable>
            <v-skeleton-loader v-else type="table" />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
/**
 * Стартовая страница тренажёра: состояние движка, материалы, статистика.
 *
 * Состояние движка запрашивается до материалов и показывается первым:
 * при выключенном тренажёре или недоступном движке список материалов
 * бесполезен, и показывать его — значит предлагать действие, которое
 * не сработает.
 *
 * Синхронная очередь отмечена явно: при QUEUE_CONNECTION=sync запрос
 * «начать тренировку» выполняет генерацию внутри себя и длится десятки
 * секунд. Пользователь должен понимать, что это не зависание.
 */
import {
  fetchTutorHealth,
  fetchTutorMaterials,
  fetchTutorStats,
  startTutorSession,
} from '../../api/tutor.api'
import { unwrapResponse } from '../../api/envelope'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import DataTable from '../../components/ui/DataTable.vue'

export default {
  name: 'TutorMain',
  components: { EmptyState, PageHeader, DataTable },
  data() {
    return {
      health: null,
      materials: [],
      stats: null,
      loadingMaterials: true,
      startingId: null,
      alert: null,
    }
  },
  computed: {
    healthColumns() {
      return [
        { key: 'label', title: this.$t('tutor.model'), width: '40%' },
        { key: 'value', title: this.$t('tutor.engineState') },
      ]
    },
    healthRows() {
      if (!this.health) return []
      const yes = this.$t('tutor.yes')
      const no = this.$t('tutor.no')
      return [
        { id: 'model', label: this.$t('tutor.model'), value: this.health.model || '—' },
        { id: 'backcheck', label: this.$t('tutor.backcheck'), value: this.health.backcheck ? yes : no },
        { id: 'embeddings', label: this.$t('tutor.embeddings'), value: this.health.embeddings ? yes : no },
        {
          id: 'queue',
          label: this.$t('tutor.queue'),
          value: this.health.async ? this.$t('tutor.queueAsync') : this.$t('tutor.queueSync'),
        },
      ]
    },
  },
  async mounted() {
    await Promise.all([this.loadHealth(), this.loadMaterials(), this.loadStats()])
  },
  methods: {
    async loadHealth() {
      try {
        this.health = unwrapResponse(await fetchTutorHealth())
      } catch (error) {
        this.health = { enabled: false, available: false, error: this.errorText(error) }
      }
    },

    async loadMaterials() {
      this.loadingMaterials = true

      try {
        this.materials = unwrapResponse(await fetchTutorMaterials()) || []
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      } finally {
        this.loadingMaterials = false
      }
    },

    async loadStats() {
      try {
        this.stats = unwrapResponse(await fetchTutorStats())
      } catch (error) {
        // Статистика не критична: её отсутствие не должно мешать начать.
        this.stats = null
      }
    },

    async start(material) {
      this.startingId = material.id
      this.alert = null

      try {
        const session = unwrapResponse(await startTutorSession(material.id, 6))

        if (!session?.session_id) {
          this.alert = { type: 'error', text: this.$t('tutor.startFailed') }

          return
        }

        this.$router.push({
          name: 'tutor.runner',
          params: { session: session.session_id },
        })
      } catch (error) {
        this.alert = { type: 'error', text: this.errorText(error) }
      } finally {
        this.startingId = null
      }
    },

    /**
     * Текст ошибки из конверта.
     *
     * Конверт кладёт текст в error.message, а axios-ошибка лежит в
     * response.data. Без разбора пользователь увидел бы «ошибка сети».
     */
    errorText(error) {
      return (
        error?.response?.data?.error?.message ||
        error?.message ||
        this.$t('tutor.genericError')
      )
    },
  },
}
</script>

<style scoped>
.tutor-main {
  max-width: 1200px;
}
</style>
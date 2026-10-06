<template>
  <v-container class="tutor-main">
    <h1 class="text-h5 mb-1">{{ $t('tutor.title') }}</h1>
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
            <v-table v-if="health" density="compact">
              <tbody>
                <tr>
                  <td>{{ $t('tutor.model') }}</td>
                  <td data-test="tutor-model">{{ health.model || '—' }}</td>
                </tr>
                <tr>
                  <td>{{ $t('tutor.backcheck') }}</td>
                  <td>{{ health.backcheck ? $t('tutor.yes') : $t('tutor.no') }}</td>
                </tr>
                <tr>
                  <td>{{ $t('tutor.embeddings') }}</td>
                  <td>{{ health.embeddings ? $t('tutor.yes') : $t('tutor.no') }}</td>
                </tr>
                <tr>
                  <td>{{ $t('tutor.queue') }}</td>
                  <td>{{ health.async ? $t('tutor.queueAsync') : $t('tutor.queueSync') }}</td>
                </tr>
              </tbody>
            </v-table>
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

export default {
  name: 'TutorMain',
  components: { EmptyState },
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
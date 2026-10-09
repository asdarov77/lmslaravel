<template>
  <div>
    <PageHeader :title="$t('certificates.pageTitle')" :subtitle="$t('certificates.pageHint')" />

    <div class="u-card u-card__body" v-if="loading">
      <v-skeleton-loader type="list-item-three-line" />
    </div>

    <EmptyState
      v-else-if="!items.length"
      icon="mdi-certificate-outline"
      :title="$t('certificates.emptyTitle')"
      :text="$t('certificates.emptyText')"
    />

    <DataTable
      v-else
      :title="$t('certificates.tableTitle')"
      :count="items.length"
      :columns="columns"
      :rows="items"
      :row-key="(row) => row.course_id"
      :caption="$t('certificates.tableTitle')"
    >
      <template #cell-lessons="{ row }">
        <span :class="{ 'text-success': row.available }">
          {{ row.lessons_done }} / {{ row.lessons_total }}
        </span>
        <span v-if="row.available" class="u-badge u-badge--success ml-2">
          {{ $t('certificates.available') }}
        </span>
      </template>

      <template #cell-exams="{ row }">
        {{ row.exams_passed }} / {{ row.exams_total }}
      </template>

      <template #cell-actions="{ row }">
        <v-btn
          v-if="row.available"
          color="primary"
          size="small"
          :to="{ name: 'certificates.view', params: { idEdit: row.course_id } }"
          :data-test="`cert-go-${row.course_id}`"
        >
          {{ $t('certificates.open') }}
        </v-btn>
        <v-tooltip v-else :text="$t('certificates.blockedHint')" location="top">
          <template #activator="{ props }">
            <span v-bind="props" class="text-disabled">
              {{ $t('certificates.blocked') }}
            </span>
          </template>
        </v-tooltip>
      </template>
    </DataTable>
  </div>
</template>

<script>
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import DataTable from '../../components/ui/DataTable.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'

/**
 * Список сертификатов обучаемого.
 *
 * Курсы, по которым сертификат ещё не выдаётся, показаны, но
 * недоступны: скрывать их нельзя — иначе человек не понимает, что
 * мешает закрыть обучение. Причина недоступности видна прямо в
 * таблице: сколько уроков закрыто и сколько экзаменов сдано.
 */
export default {
  name: 'CertificateList',
  components: { DataTable, EmptyState, PageHeader },
  data() {
    return {
      items: [],
      loading: true,
    }
  },
  computed: {
    columns() {
      return [
        { key: 'title', title: this.$t('certificates.course') },
        { key: 'lessons', title: this.$t('certificates.lessons'), width: '200px' },
        { key: 'exams', title: this.$t('certificates.exams'), width: '120px' },
        { key: 'actions', title: '', width: '160px', align: 'right' },
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
        const response = await $api.get('/api/my/certificates')
        this.items = unwrapResponse(response) || []
      } catch (error) {
        this.items = []
      } finally {
        this.loading = false
      }
    },
  },
}
</script>
<template>
  <div>
    <PageHeader :title="$t('reports.title')" :subtitle="$t('reports.hint')">
      <template #actions>
        <v-btn
          variant="text"
          prepend-icon="mdi-download"
          :href="exportUrl"
          download
          data-test="reports-export"
        >
          {{ $t('reports.export') }}
        </v-btn>
        <v-btn
          variant="text"
          :prepend-icon="viewMode === 'table' ? 'mdi-table' : 'mdi-account-tree'"
          @click="viewMode = viewMode === 'table' ? 'tree' : 'table'"
          class="ms-2"
        >
          {{ viewMode === 'table' ? $t('reports.treeView') : $t('reports.tableView') }}
        </v-btn>
      </template>
    </PageHeader>

    <div class="u-card u-card__body reports__filters" data-test="reports-filters">
      <div class="reports__filter">
        <v-text-field
          v-model="filters.from"
          :label="$t('reports.from')"
          type="date"
          density="compact"
          hide-details
          data-test="reports-from"
          @change="load"
        />
      </div>
      <div class="reports__filter">
        <v-text-field
          v-model="filters.to"
          :label="$t('reports.to')"
          type="date"
          density="compact"
          hide-details
          data-test="reports-to"
          @change="load"
        />
      </div>
      <div class="reports__filter">
        <v-select
          v-model="filters.group_id"
          :items="groups"
          item-title="name"
          item-value="id"
          :label="$t('reports.group')"
          clearable
          density="compact"
          hide-details
          data-test="reports-group"
          @update:model-value="load"
        />
      </div>
      <div class="reports__filter">
        <v-select
          v-model="filters.course_id"
          :items="courses"
          item-title="name"
          item-value="id"
          :label="$t('reports.course')"
          clearable
          density="compact"
          hide-details
          data-test="reports-course"
          @update:model-value="load"
        />
      </div>
      <div class="reports__filter reports__filter--actions">
        <v-btn size="small" variant="text" data-test="reports-reset" @click="reset">
          {{ $t('reports.reset') }}
        </v-btn>
      </div>
    </div>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="statistic, article" />
    </div>

    <template v-else>
      <div class="reports__stats" data-test="reports-stats">
        <StatCard
          :value="report.enrollment?.total ?? 0"
          :label="$t('reports.enrollments')"
          icon="mdi-account-group-outline"
          tone="primary"
        />
        <StatCard
          :value="report.exams?.percent === null || report.exams?.percent === undefined
            ? $t('reports.noData')
            : report.exams.percent + '%'"
          :label="$t('reports.passRate')"
          icon="mdi-check-decagram-outline"
          :tone="(report.exams?.percent ?? 0) >= 70 ? 'success' : 'warning'"
        />
        <StatCard
          :value="report.progress?.percent === null || report.progress?.percent === undefined
            ? $t('reports.noData')
            : report.progress.percent + '%'"
          :label="$t('reports.lessonProgress')"
          icon="mdi-progress-clock"
          tone="info"
        />
        <StatCard
          :value="report.activity?.new_users ?? 0"
          :label="$t('reports.newUsers')"
          icon="mdi-account-plus-outline"
          tone="muted"
        />
      </div>

      <MetricCharts
        class="mt-4"
        :months="report.timeline || []"
        :groups="[]"
        data-test="reports-timeline"
      />

      <DataTable
        v-if="viewMode === 'table'"
        class="mt-4"
        :title="$t('reports.enrollmentTable')"
        :count="report.enrollment?.rows?.length || 0"
        :columns="columns"
        :rows="report.enrollment?.rows || []"
        :row-key="(row) => `${row.group}-${row.course}-${row.study_from}`"
        :caption="$t('reports.enrollmentTable')"
        :empty-title="$t('reports.emptyTitle')"
        :empty-text="$t('reports.emptyText')"
        data-test="reports-table"
      >
        <template #cell-deadline="{ row }">
          <span v-if="row.deadline">{{ row.deadline }}</span>
          <span v-else class="u-muted">—</span>
        </template>
      </DataTable>

      <RecursiveTable
        v-else
        :columns="treeColumns"
        :rows="treeRows"
        :row-key="treeRowKey"
        :parent-key="'parent_id'"
        :tree-column-key="'name'"
        :level-key="'_level'"
        :title="$t('reports.enrollmentTree')"
        :count="treeRows.length"
        :loading="loading"
        :default-expanded="true"
        data-test="reports-tree"
      >
        <template #cell-study_from="{ row, value }">
          <span v-if="value">{{ value }}</span>
          <span v-else class="u-muted">—</span>
        </template>
        <template #cell-study_to="{ row, value }">
          <span v-if="value">{{ value }}</span>
          <span v-else class="u-muted">—</span>
        </template>
        <template #cell-deadline="{ row, value }">
          <span v-if="value">{{ value }}</span>
          <span v-else class="u-muted">—</span>
        </template>
      </RecursiveTable>
    </template>
  </div>
</template>

<script>
import $api from '../api/httpClient'
import { unwrapResponse } from '../api/envelope'
import DataTable from '../components/ui/DataTable.vue'
import MetricCharts from '../components/ui/MetricCharts.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import StatCard from '../components/ui/StatCard.vue'
import RecursiveTable from '../components/ui/RecursiveTable.vue'

/**
 * Отчёты и аналитика.
 *
 * Фильтры применяются на сервере: период, группа и курс уходят в
 * запрос, а не фильтруют уже загруженные данные. Иначе отчёт за год
 * по всем группам тянул бы в браузер всё, чтобы показать одну строку.
 *
 * Пустые значения показываются прочерком, а не «0%»: группа без
 * попыток не провалила экзамены — у неё их не было.
 */
export default {
  name: 'ReportsPage',
  components: { DataTable, MetricCharts, PageHeader, StatCard, RecursiveTable },
  data() {
    return {
      report: {
        filters: {},
        enrollment: { rows: [], total: 0, groups: 0 },
        exams: { attempts: 0, passed: 0, percent: null },
        progress: { percent: null, lessons_done: 0, lessons_started: 0 },
        activity: { new_users: 0, topics: 0, announcements: 0 },
        timeline: [],
      },
      groups: [],
      courses: [],
      filters: { from: '', to: '', group_id: null, course_id: null },
      loading: true,
      viewMode: 'table',
    }
  },
  computed: {
    columns() {
      return [
        { key: 'group', title: this.$t('reports.group') },
        { key: 'course', title: this.$t('reports.course') },
        { key: 'study_from', title: this.$t('reports.from') },
        { key: 'study_to', title: this.$t('reports.to') },
        { key: 'deadline', title: this.$t('reports.deadline'), width: '140px' },
      ]
    },

    treeColumns() {
      return [
        { key: 'name', title: this.$t('reports.item'), width: '300px' },
        { key: 'study_from', title: this.$t('reports.from'), width: '140px', align: 'center' },
        { key: 'study_to', title: this.$t('reports.to'), width: '140px', align: 'center' },
        { key: 'deadline', title: this.$t('reports.deadline'), width: '140px', align: 'center' },
      ]
    },

    treeRows() {
      const rows = []
      let idCounter = 0

      const addNode = (node) => {
        node.id = node.id || `node_${idCounter++}`
        rows.push(node)
      }

      const enrollmentRows = this.report.enrollment?.rows || []

      const groupMap = {}
      enrollmentRows.forEach((row) => {
        if (!groupMap[row.group]) {
          groupMap[row.group] = { courses: {} }
        }
        if (!groupMap[row.group].courses[row.course]) {
          groupMap[row.group].courses[row.course] = []
        }
        groupMap[row.group].courses[row.course].push(row)
      })

      Object.entries(groupMap).forEach(([groupName, groupData]) => {
        const groupNode = {
          id: `group_${groupName}`,
          name: groupName,
          _level: 1,
          _hasChildren: true,
          study_from: null,
          study_to: null,
          deadline: null,
          isGroup: true,
        }
        addNode(groupNode)

        Object.entries(groupData.courses).forEach(([courseName, enrollments]) => {
          const courseNode = {
            id: `course_${groupName}_${courseName}`,
            name: courseName,
            parent_id: groupNode.id,
            _level: 2,
            _hasChildren: true,
            study_from: null,
            study_to: null,
            deadline: null,
            isCourse: true,
          }
          addNode(courseNode)

          enrollments.forEach((enrollment) => {
            const enrollmentNode = {
              id: `enroll_${groupName}_${courseName}_${enrollment.study_from}`,
              name: `${enrollment.student || this.$t('reports.enrollment')}`,
              parent_id: courseNode.id,
              _level: 3,
              _hasChildren: false,
              study_from: enrollment.study_from,
              study_to: enrollment.study_to,
              deadline: enrollment.deadline,
              isEnrollment: true,
              group: enrollment.group,
              course: enrollment.course,
            }
            addNode(enrollmentNode)
          })
        })
      })

      return rows
    },

    treeRowKey(row) {
      return row.id
    },

    exportUrl() {
      const params = new URLSearchParams({ report: 'enrollment' })

      Object.entries(this.filters).forEach(([key, value]) => {
        if (value !== null && value !== '') {
          params.set(key, value)
        }
      })

      return `${import.meta.env.VITE_APP_URL || ''}/api/reports/export?${params.toString()}`
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true

      try {
        const params = {}
        Object.entries(this.filters).forEach(([key, value]) => {
          if (value !== null && value !== '') {
            params[key] = value
          }
        })

        const response = await $api.get('/api/reports', { params })
        const payload = unwrapResponse(response) || {}

        this.report = { ...this.report, ...payload }
        this.groups = payload.groups || []
        this.courses = payload.courses || []
      } catch (error) {
        this.report = {
          ...this.report,
          enrollment: { rows: [], total: 0, groups: 0 },
          exams: { attempts: 0, passed: 0, percent: null },
          progress: { percent: null, lessons_done: 0, lessons_started: 0 },
          activity: { new_users: 0, topics: 0, announcements: 0 },
          timeline: [],
        }
      } finally {
        this.loading = false
      }
    },

    reset() {
      this.filters = { from: '', to: '', group_id: null, course_id: null }
      this.load()
    },
  },
}
</script>

<style scoped>
.reports__filters {
  display: grid;
  gap: var(--sp-3);
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  margin-bottom: var(--sp-4);
}

.reports__filter--actions {
  align-items: flex-end;
  display: flex;
}

.reports__stats {
  display: grid;
  gap: var(--sp-3);
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.u-muted {
  color: var(--c-text-muted);
}
</style>
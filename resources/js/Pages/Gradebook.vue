<template>
  <div>
    <PageHeader :title="$t('gradebook.title')" :subtitle="$t('gradebook.hint')">
      <template #actions>
        <v-btn
          v-if="students.length"
          variant="text"
          prepend-icon="mdi-download"
          data-test="gb-export"
          :href="exportUrl"
          download
        >
          {{ $t('gradebook.export') }}
        </v-btn>
        <v-btn
          variant="text"
          :prepend-icon="viewMode === 'matrix' ? 'mdi-table' : 'mdi-account-tree'"
          @click="viewMode = viewMode === 'matrix' ? 'tree' : 'matrix'"
          class="ms-2"
        >
          {{ viewMode === 'matrix' ? $t('gradebook.treeView') : $t('gradebook.matrixView') }}
        </v-btn>
      </template>
    </PageHeader>

    <div class="u-card u-card__body gb-filters" data-test="gb-filters">
      <v-select
        :items="groups"
        :model-value="groupId"
        item-title="name"
        item-value="id"
        :label="$t('gradebook.group')"
        data-test="gb-group"
        density="compact"
        hide-details
        class="mb-3"
        @update:model-value="onGroup"
      />

      <v-select
        v-if="courses.length"
        :items="courses"
        :model-value="courseId"
        item-title="name"
        item-value="id"
        :label="$t('gradebook.course')"
        data-test="gb-course"
        density="compact"
        clearable
        hide-details
        @update:model-value="onCourse"
      />
    </div>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="table" />
    </div>

    <EmptyState
      v-else-if="!groupId"
      icon="mdi-table-large"
      :title="$t('gradebook.pickGroup')"
      :text="$t('gradebook.pickGroupText')"
    />

    <EmptyState
      v-else-if="!exams.length"
      icon="mdi-clipboard-text-off-outline"
      :title="$t('gradebook.noExams')"
      :text="$t('gradebook.noExamsText')"
    />

    <template v-else>
      <!-- Matrix view (original) -->
      <div v-if="viewMode === 'matrix'" class="gb-wrap">
        <table class="u-table gb-table" data-test="gb-table">
          <caption class="u-sr-only">{{ $t('gradebook.caption') }}</caption>
          <thead>
            <tr>
              <th scope="col" class="gb-table__student">{{ $t('gradebook.student') }}</th>
              <th v-for="exam in exams" :key="exam.id" scope="col">
                {{ exam.title }}
                <span v-if="exam.course" class="gb-table__course">{{ exam.course }}</span>
              </th>
              <th scope="col" class="gb-table__avg">{{ $t('gradebook.average') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="student in students" :key="student.id">
              <th scope="row" class="gb-table__student">{{ student.fio }}</th>
              <td v-for="exam in exams" :key="exam.id" class="gb-cell">
                <button
                  v-if="cell(exam, student)"
                  type="button"
                  class="gb-grade"
                  :class="gradeClass(cell(exam, student))"
                  :title="cellTitle(exam, student)"
                  :data-test="`gb-cell-${exam.id}-${student.id}`"
                  @click="openEditor(exam, student)"
                >
                  {{ cell(exam, student).grade }}
                  <span v-if="cell(exam, student).manual" class="gb-grade__manual" aria-hidden="true">•</span>
                </button>
                <span v-else class="gb-cell__empty" aria-label="—">—</span>
              </td>
              <td class="gb-table__avg">{{ average(student) || '—' }}</td>
            </tr>
          </tbody>
        </table>

        <p class="gb-legend">
          <span class="gb-grade__manual" aria-hidden="true">•</span>
          {{ $t('gradebook.manualLegend') }}
        </p>
      </div>

      <!-- Tree view (RecursiveTable) -->
      <RecursiveTable
        v-else
        :columns="treeColumns"
        :rows="treeRows"
        :row-key="treeRowKey"
        :parent-key="'parent_id'"
        :tree-column-key="'name'"
        :level-key="'_level'"
        :title="$t('gradebook.treeTitle')"
        :count="treeRows.length"
        :selectable="true"
        :loading="loading"
        :aggregates="treeAggregates"
        :default-expanded="true"
        data-test="gb-tree"
        @toggle="onTreeToggle"
        @select="onTreeSelect"
      >
        <template #cell-grade="{ row, value }">
          <span v-if="value !== null && value !== undefined" :class="gradeClass({ grade: value, manual: row.manual })">
            {{ value }}
            <span v-if="row.manual" class="gb-grade__manual" aria-hidden="true">•</span>
          </span>
          <span v-else class="u-muted">—</span>
        </template>
        <template #cell-correct="{ row, value }">
          <span v-if="value !== null && value !== undefined">
            {{ value }}/{{ row.total }}
          </span>
          <span v-else class="u-muted">—</span>
        </template>
        <template #cell-average="{ row, value }">
          <span v-if="value !== null && value !== undefined" class="gb-table__avg">{{ value }}</span>
          <span v-else class="u-muted">—</span>
        </template>
        <template #aggregate-grade="{ row }">
          <span class="gb-table__avg">{{ row.avgGrade }}</span>
        </template>
      </RecursiveTable>
    </template>

    <!-- Редактор ручной оценки -->
    <v-dialog v-model="editor.open" max-width="420">
      <v-card>
        <v-card-title>{{ $t('gradebook.editorTitle') }}</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-4">
            {{ editor.exam?.title }} — {{ editor.student?.fio }}
          </p>

          <p v-if="editor.autoGrade" class="text-caption mb-4">
            {{ $t('gradebook.autoGrade') }}: {{ editor.autoGrade }}
          </p>

          <v-select
            :items="gradeOptions"
            :model-value="editor.grade"
            :label="$t('gradebook.grade')"
            data-test="gb-grade"
            density="compact"
          />

          <v-textarea
            v-model="editor.comment"
            :label="$t('gradebook.comment')"
            rows="3"
            density="compact"
            data-test="gb-comment"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn v-if="editor.manual" variant="text" color="error" data-test="gb-clear" @click="clearOverride">
            {{ $t('gradebook.clear') }}
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="editor.open = false">{{ $t('cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" data-test="gb-save" @click="save">
            {{ $t('save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import $api from '../api/httpClient'
import { unwrapResponse } from '../api/envelope'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import RecursiveTable from '../components/ui/RecursiveTable.vue'

/**
 * Грейдбук преподавателя: журнал «обучаемые × экзамены».
 *
 * Оценки не хранятся готовыми: сервер отдаёт сырой счёт и границы,
 * а пятерка считается здесь по тем же порогам, что и на бэкенде.
 * Иначе после смены границ журнал показывал бы старые цифры.
 *
 * Точка на оценке — ручная оценка преподавателя: она перекрывает
 * автоматическую, и это видно сразу, а не в подсказке.
 */
export default {
  name: 'GradebookPage',
  components: { EmptyState, PageHeader, RecursiveTable },
  data() {
    return {
      groups: [],
      courses: [],
      students: [],
      exams: [],
      cells: {},
      boundaries: {},
      groupId: null,
      courseId: null,
      loading: false,
      saving: false,
      viewMode: 'matrix',
      editor: {
        open: false,
        exam: null,
        student: null,
        grade: null,
        comment: '',
        manual: false,
        autoGrade: null,
      },
    }
  },
  computed: {
    gradeOptions() {
      const grades = [...new Set(Object.values(this.boundaries))].sort((a, b) => a - b)

      return grades.map((grade) => ({ title: String(grade), value: grade }))
    },

    exportUrl() {
      return `${import.meta.env.VITE_APP_URL || ''}/api/gradebook/export?group_id=${this.groupId}`
    },

    treeColumns() {
      return [
        { key: 'name', title: this.$t('gradebook.student'), width: '300px' },
        { key: 'grade', title: this.$t('gradebook.grade'), width: '100px', align: 'center' },
        { key: 'correct', title: this.$t('gradebook.correct'), width: '100px', align: 'center' },
        { key: 'average', title: this.$t('gradebook.average'), width: '100px', align: 'right' },
      ]
    },

    treeRows() {
      const rows = []
      let idCounter = 0

      const addNode = (node) => {
        node.id = node.id || `node_${idCounter++}`
        rows.push(node)
      }

      this.exams.forEach((exam) => {
        const examNode = {
          id: `exam_${exam.id}`,
          name: exam.title,
          _level: 1,
          _hasChildren: true,
          grade: null,
          correct: null,
          total: null,
          average: null,
          examId: exam.id,
          isExam: true,
        }
        addNode(examNode)

        this.students.forEach((student) => {
          const cell = this.cell(exam, student)
          if (cell) {
            const studentNode = {
              id: `student_${student.id}_exam_${exam.id}`,
              name: student.fio,
              parent_id: examNode.id,
              _level: 2,
              _hasChildren: false,
              grade: cell.grade,
              correct: cell.correct,
              total: cell.total,
              average: cell.grade,
              manual: cell.manual,
              studentId: student.id,
              examId: exam.id,
              isStudent: true,
            }
            addNode(studentNode)
          }
        })
      })

      return rows
    },

    treeRowKey(row) {
      return row.id
    },

    treeAggregates(rows) {
      const aggregates = []
      const examGroups = {}

      rows.forEach((row) => {
        if (row.isExam) {
          examGroups[row.id] = { grades: [], count: 0 }
        } else if (row.isStudent && row.grade !== null && row.grade !== undefined) {
          const parentId = row.parent_id
          if (examGroups[parentId]) {
            examGroups[parentId].grades.push(Number(row.grade))
            examGroups[parentId].count++
          }
        }
      })

      Object.entries(examGroups).forEach(([examId, data]) => {
        if (data.grades.length > 0) {
          const avg = (data.grades.reduce((a, b) => a + b, 0) / data.grades.length).toFixed(2)
          aggregates.push({
            id: `agg_${examId}`,
            name: this.$t('gradebook.average'),
            parent_id: examId,
            _level: 2,
            _hasChildren: false,
            _parent: examId,
            avgGrade: avg,
            grade: null,
            correct: null,
            total: null,
            average: avg,
          })
        }
      })

      return aggregates
    },
  },
  async mounted() {
    await this.loadGroups()
  },
  methods: {
    async loadGroups() {
      this.loading = true

      try {
        const response = await $api.get('/api/gradebook')
        const payload = unwrapResponse(response) || {}

        this.groups = payload.groups || []

        if (this.groups.length === 1) {
          await this.onGroup(this.groups[0].id)
        }
      } finally {
        this.loading = false
      }
    },

    async onGroup(id) {
      this.groupId = id ?? null
      this.courseId = null
      this.students = []
      this.exams = []
      this.cells = {}

      if (!this.groupId) return

      await this.load()
    },

    async onCourse(id) {
      this.courseId = id ?? null
      await this.load()
    },

    async load() {
      this.loading = true

      try {
        const params = { group_id: this.groupId }
        if (this.courseId) params.course_id = this.courseId

        const response = await $api.get('/api/gradebook', { params })
        const payload = unwrapResponse(response) || {}

        this.groups = payload.groups || this.groups
        this.courses = payload.courses || []
        this.students = payload.students || []
        this.exams = payload.exams || []
        this.cells = payload.cells || {}
        this.boundaries = payload.boundaries || {}
      } catch (error) {
        this.students = []
        this.exams = []
        this.cells = {}
      } finally {
        this.loading = false
      }
    },

    cell(exam, student) {
      return this.cells[`${exam.id}:${student.id}`] || null
    },

    gradeClass(cell) {
      if (cell.manual) return 'is-manual'

      const grade = cell.grade
      if (grade >= 5) return 'is-high'
      if (grade === 4 || grade === 3) return 'is-mid'

      return 'is-low'
    },

    cellTitle(exam, student) {
      const cell = this.cell(exam, student)
      if (!cell) return ''

      const parts = []

      if (cell.correct != null) {
        parts.push(`${this.$t('gradebook.correct')} ${cell.correct}/${cell.total}`)
      }
      if (cell.manual) parts.push(this.$t('gradebook.manualLegend'))
      if (cell.comment) parts.push(cell.comment)

      return parts.join(' · ')
    },

    average(student) {
      const grades = this.exams
        .map((exam) => this.cell(exam, student))
        .filter(Boolean)
        .map((cell) => Number(cell.grade) || 0)

      if (!grades.length) return null

      return (grades.reduce((sum, grade) => sum + grade, 0) / grades.length).toFixed(2)
    },

    openEditor(exam, student) {
      const cell = this.cell(exam, student)

      this.editor = {
        open: true,
        exam,
        student,
        grade: cell?.manual ? cell.manual_grade : (cell?.grade ?? null),
        comment: cell?.comment ?? '',
        manual: Boolean(cell?.manual),
        autoGrade: cell?.auto_grade ?? null,
      }
    },

    onTreeToggle({ row, expanded }) {
      // Could save expanded state to localStorage
    },

    onTreeSelect(row) {
      if (row.isStudent && row.examId && row.studentId) {
        const exam = this.exams.find((e) => e.id === row.examId)
        const student = this.students.find((s) => s.id === row.studentId)
        if (exam && student) {
          this.openEditor(exam, student)
        }
      }
    },

    async save() {
      this.saving = true

      try {
        await $api.put('/api/gradebook/cell', {
          user_id: this.editor.student.id,
          exam_id: this.editor.exam.id,
          grade: this.editor.grade ?? null,
          comment: this.editor.comment ?? null,
        })

        this.editor.open = false
        await this.load()
      } finally {
        this.saving = false
      }
    },

    async clearOverride() {
      this.editor.grade = null
      await this.save()
    },
  },
}
</script>

<style scoped>
.gb-filters {
  margin-bottom: var(--sp-4);
}

.gb-wrap {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-lg);
  overflow: auto;
}

.gb-table th,
.gb-table td {
  vertical-align: middle;
}

.gb-table thead th {
  background: var(--c-surface-2);
  position: sticky;
  top: 0;
  z-index: 1;
}

.gb-table__student {
  min-width: 220px;
  position: sticky;
  left: 0;
  background: var(--c-surface);
  text-align: left;
  z-index: 1;
}

.gb-table thead .gb-table__student {
  background: var(--c-surface-2);
  z-index: 2;
}

.gb-table__course {
  color: var(--c-text-muted);
  display: block;
  font-size: 0.75rem;
  font-weight: 400;
}

.gb-table__avg {
  font-variant-numeric: tabular-nums;
  min-width: 90px;
  text-align: right;
}

.gb-cell {
  text-align: center;
}

.gb-grade {
  background: none;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font: inherit;
  font-variant-numeric: tabular-nums;
  min-width: 2.5rem;
  padding: 0.25rem 0.5rem;
}

.gb-grade:hover {
  border-color: var(--c-border-strong);
}

.gb-grade.is-high {
  background: var(--c-success-soft);
  color: var(--c-success);
}

.gb-grade.is-mid {
  background: var(--c-warning-soft);
  color: var(--c-warning);
}

.gb-grade.is-low {
  background: var(--c-danger-soft);
  color: var(--c-danger);
}

.gb-grade.is-manual {
  outline: 1px dashed currentColor;
}

.gb-grade__manual {
  font-weight: 700;
}

.gb-cell__empty {
  color: var(--c-text-muted);
}

.gb-legend {
  color: var(--c-text-muted);
  font-size: 0.75rem;
  margin-top: var(--sp-2);
}

.u-muted {
  color: var(--c-text-muted);
}
</style>
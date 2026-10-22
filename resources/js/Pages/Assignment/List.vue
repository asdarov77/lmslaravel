<template>
  <div>
    <PageHeader :title="$t('assignments.title')" :subtitle="$t('assignments.hint')">
      <template v-if="canManage" #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" data-test="assignment-new" @click="openCreate">
          {{ $t('assignments.create') }}
        </v-btn>
      </template>
    </PageHeader>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="list-item-avatar-three-line@4" />
    </div>

    <EmptyState
      v-else-if="!items.length"
      icon="mdi-clipboard-text-outline"
      :title="$t('assignments.emptyTitle')"
      :text="$t('assignments.emptyText')"
    />

    <div v-else class="as-list" data-test="assignments-list">
      <article
        v-for="item in items"
        :key="item.id"
        class="u-card u-card__body as-item"
        :class="{ 'is-overdue': item.overdue && !item.own_submission }"
      >
        <div class="as-item__head">
          <h2 class="u-card__title">
            <router-link :to="{ name: 'assignments.show', params: { idEdit: item.id } }">
              {{ item.title }}
            </router-link>
          </h2>

          <span class="u-badge" :class="statusClass(item)">
            {{ statusLabel(item) }}
          </span>
        </div>

        <p class="as-item__meta">
          <span v-if="item.course">{{ item.course }}</span>
          <span v-if="item.lesson">· {{ item.lesson }}</span>
          <span v-if="item.due_at">· {{ $t('assignments.due') }} {{ formatDate(item.due_at) }}</span>
        </p>

        <p v-if="item.own_submission" class="as-item__status">
          <v-icon icon="mdi-check-circle-outline" size="16" aria-hidden="true" />
          {{ $t('assignments.submitted') }}
          <template v-if="item.own_submission.reviewed && item.own_submission.score !== null">
            · {{ item.own_submission.score }} / {{ item.max_score }}
          </template>
        </p>
        <p v-else-if="item.overdue" class="as-item__status as-item__status--danger">
          <v-icon icon="mdi-alert-outline" size="16" aria-hidden="true" />
          {{ $t('assignments.overdue') }}
        </p>

        <p v-if="canManage && item.submissions_count" class="as-item__meta">
          {{ $t('assignments.works', { n: item.submissions_count }) }}
        </p>
      </article>
    </div>

    <!-- Создание/редактирование задания -->
    <v-dialog v-model="editor.open" max-width="680">
      <v-card>
        <v-card-title>{{ isEditing ? $t('assignments.editTitle') : $t('assignments.createTitle') }}</v-card-title>
        <v-card-text>
          <AssignmentForm
            v-model:editor="editor"
            :initial-data="editingAssignment"
            :courses="courses"
            :groups="groups"
            :mode="isEditing ? 'edit' : 'create'"
            @submit="saveAssignment"
            @cancel="closeEditor"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import $api from '../../api/httpClient'
import { unwrapResponse, unwrapArray } from '../../api/envelope'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import AssignmentForm from '../../components/ui/AssignmentForm.vue'

function rowsOf(payload) {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items

  return []
}

/**
 * Домашние задания.
 *
 * Список один и тот же для обеих ролей, различается он тем, что
 * доступно: обучаемому — свои задания и своя работа, преподавателю —
 * задания своих групп и число сданных работ.
 */
export default {
  name: 'AssignmentList',
  components: { EmptyState, PageHeader, AssignmentForm },
  data() {
    return {
      items: [],
      courses: [],
      groups: [],
      loading: true,
      editor: { open: false },
      editingAssignment: null,
    }
  },
  computed: {
    canManage() {
      const can = this.$store?.getters?.['Auth/can']
      const isSuperAdmin = this.$store?.getters?.['Auth/isSuperAdmin']

      if (isSuperAdmin) return true

      if (typeof can === 'function') {
        return can('grading.manage', 'groups.manage', 'courses.manage')
      }

      const perms = this.$store?.state?.Auth?.permissionSlugs || []

      return perms.some((slug) => ['grading.manage', 'groups.manage', 'courses.manage'].includes(slug))
    },

    isEditing() {
      return !!this.editingAssignment
    },
  },
  async mounted() {
    await this.load()

    if (this.canManage) {
      await this.loadOptions()
    }
  },
  methods: {
    async load() {
      this.loading = true

      try {
        const response = await $api.get('/api/assignments')
        this.items = rowsOf(unwrapResponse(response)) || []
      } catch (error) {
        this.items = []
      } finally {
        this.loading = false
      }
    },

    async loadOptions() {
      const [coursesRes, groupsRes] = await Promise.allSettled([
        $api.get('/api/courses', { params: { per_page: 100 } }),
        $api.get('/api/groups'),
      ])

      if (coursesRes.status === 'fulfilled') {
        this.courses = rowsOf(unwrapResponse(coursesRes.value)).map((c) => ({ id: c.id, name: c.title }))
      }

      if (groupsRes.status === 'fulfilled') {
        this.groups = rowsOf(unwrapResponse(groupsRes.value)).map((g) => ({
          id: g.id,
          name: g.groupname || g.name,
        }))
      }
    },

    openCreate() {
      this.editingAssignment = null
      this.editor.open = true
    },

    async saveAssignment(payload) {
      if (this.isEditing) {
        await $api.put(`/api/assignments/${this.editingAssignment.id}`, payload)
      } else {
        await $api.post('/api/assignments', payload)
      }

      this.closeEditor()
      await this.load()
    },

    closeEditor() {
      this.editor.open = false
      this.editingAssignment = null
    },

    async editAssignment(item) {
      this.editingAssignment = item
      this.editor.open = true
    },

    statusLabel(item) {
      if (item.own_submission?.reviewed) return this.$t('assignments.reviewed')
      if (item.own_submission) return this.$t('assignments.submitted')
      if (item.overdue) return this.$t('assignments.overdue')
      if (item.due_at) return this.$t('assignments.statusActive')

      return this.$t('assignments.statusNoDeadline')
    },

    statusClass(item) {
      if (item.own_submission?.reviewed) return 'u-badge--success'
      if (item.overdue && !item.own_submission) return 'u-badge--danger'

      return ''
    },

    formatDate(value) {
      if (!value) return ''

      return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
  },
}
</script>

<style scoped>
.as-list {
  display: grid;
  gap: var(--sp-3);
}

.as-item.is-overdue {
  border-left: 3px solid var(--c-danger);
}

.as-item__head {
  align-items: center;
  display: flex;
  gap: var(--sp-2);
}

.as-item__head a {
  color: inherit;
  text-decoration: none;
}

.as-item__head a:hover {
  text-decoration: underline;
}

.as-item__meta {
  color: var(--c-text-muted);
  display: flex;
  flex-wrap: wrap;
  font-size: 0.8125rem;
  gap: var(--sp-2);
  margin-top: var(--sp-1);
}

.as-item__status {
  align-items: center;
  color: var(--c-success);
  display: flex;
  font-size: 0.875rem;
  gap: 6px;
  margin-top: var(--sp-2);
}

.as-item__status--danger {
  color: var(--c-danger);
}
</style>
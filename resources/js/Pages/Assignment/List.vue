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

        <!-- Своя работа видна сразу: «сдано или нет» — это и есть
             главный вопрос обучаемого, а не «есть ли где-то файл». -->
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

    <!-- Создание задания -->
    <v-dialog v-model="editor.open" max-width="620">
      <v-card>
        <v-card-title>{{ $t('assignments.createTitle') }}</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="editor.title"
            :label="$t('assignments.fieldTitle')"
            data-test="assignment-title"
            density="compact"
          />

          <v-textarea
            v-model="editor.description"
            :label="$t('assignments.fieldDescription')"
            rows="4"
            data-test="assignment-description"
            density="compact"
          />

          <v-select
            v-model="editor.course_id"
            :items="courses"
            item-title="name"
            item-value="id"
            :label="$t('assignments.fieldCourse')"
            data-test="assignment-course"
            density="compact"
          />

          <v-select
            v-model="editor.group_id"
            :items="groups"
            item-title="name"
            item-value="id"
            :label="$t('assignments.fieldGroup')"
            data-test="assignment-group"
            density="compact"
          />

          <div class="d-flex ga-4">
            <v-text-field
              v-model="editor.due_at"
              :label="$t('assignments.fieldDue')"
              type="datetime-local"
              density="compact"
            />
            <v-text-field
              v-model="editor.max_score"
              :label="$t('assignments.fieldMaxScore')"
              type="number"
              min="1"
              density="compact"
            />
          </div>

          <v-switch
            v-model="editor.publish"
            :label="$t('assignments.fieldPublish')"
            color="primary"
            density="compact"
            data-test="assignment-publish"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editor.open = false">{{ $t('cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" data-test="assignment-save" @click="save">
            {{ $t('save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'

/**
 * Домашние задания.
 *
 * Список один и тот же для обеих ролей, различается он тем, что
 * доступно: обучаемому — свои задания и своя работа, преподавателю —
 * задания своих групп и число сданных работ.
 */
export default {
  name: 'AssignmentList',
  components: { EmptyState, PageHeader },
  data() {
    return {
      items: [],
      courses: [],
      groups: [],
      loading: true,
      saving: false,
      editor: { open: false, title: '', description: '', course_id: null, group_id: null, due_at: '', max_score: 5, publish: true },
    }
  },
  computed: {
    canManage() {
      const perms = this.$store?.getters?.['Auth/permissionSlugs'] || []
      const groups = this.$store?.getters?.['Auth/groups'] || []

      return perms.some((slug) => ['grading.manage', 'groups.manage', 'courses.manage'].includes(slug)) && groups.includes('manage')
    },

    userGroupId() {
      return this.$store?.state?.Auth?.user?.group_id ?? null
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
        this.items = unwrapResponse(response) || []
      } catch (error) {
        this.items = []
      } finally {
        this.loading = false
      }
    },

    /** Справочники для формы: курсы и группы. */
    async loadOptions() {
      const [coursesRes, groupsRes] = await Promise.allSettled([
        $api.get('/api/courses', { params: { per_page: 100 } }),
        $api.get('/api/groups'),
      ])

      if (coursesRes.status === 'fulfilled') {
        const payload = unwrapResponse(coursesRes.value) || {}
        this.courses = (payload.data || []).map((c) => ({ id: c.id, name: c.title }))
      }

      if (groupsRes.status === 'fulfilled') {
        const payload = unwrapResponse(groupsRes.value) || {}
        this.groups = (payload.data || []).map((g) => ({ id: g.id, name: g.groupname || g.name }))
      }
    },

    openCreate() {
      this.editor = {
        open: true,
        title: '',
        description: '',
        course_id: this.courses[0]?.id ?? null,
        group_id: this.groups[0]?.id ?? null,
        due_at: '',
        max_score: 5,
        publish: true,
      }
    },

    async save() {
      this.saving = true

      try {
        await $api.post('/api/assignments', {
          title: this.editor.title,
          description: this.editor.description || null,
          course_id: this.editor.course_id,
          group_id: this.editor.group_id,
          due_at: this.editor.due_at || null,
          max_score: this.editor.max_score,
          published_at: this.editor.publish ? new Date().toISOString() : null,
        })

        this.editor.open = false
        await this.load()
      } finally {
        this.saving = false
      }
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
  color: var(--text-muted);
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
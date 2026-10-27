<template>
  <div>
    <PageHeader
      :title="assignment?.title || $t('assignments.title')"
      :subtitle="assignment?.course"
    />

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="article" />
    </div>

    <EmptyState
      v-else-if="!assignment"
      icon="mdi-clipboard-text-outline"
      :title="$t('assignments.notFoundTitle')"
      :text="$t('assignments.notFoundText')"
    />

    <template v-else>
      <article class="u-card u-card__body" data-test="assignment-card">
        <p v-if="assignment.description" class="assignment__description">
          {{ assignment.description }}
        </p>

        <dl class="assignment__facts">
          <div>
            <dt>{{ $t('assignments.due') }}</dt>
            <dd>
              <template v-if="assignment.due_at">{{ formatDate(assignment.due_at) }}</template>
              <span v-else class="u-muted">{{ $t('assignments.noDeadline') }}</span>
            </dd>
          </div>
          <div>
            <dt>{{ $t('assignments.maxScore') }}</dt>
            <dd>{{ assignment.max_score }}</dd>
          </div>
          <div v-if="assignment.group">
            <dt>{{ $t('assignments.group') }}</dt>
            <dd>{{ assignment.group }}</dd>
          </div>
        </dl>
      </article>

      <!-- Сдача работы: только обучаемому -->
      <section v-if="!canReview" class="u-card u-card__body mt-4">
        <h2 class="u-card__title">{{ $t('assignments.submitTitle') }}</h2>

        <v-alert
          v-if="assignment.overdue"
          type="warning"
          density="compact"
          class="mb-3"
        >
          {{ $t('assignments.overdueHint') }}
        </v-alert>

        <FormField
          v-if="!assignment.overdue"
          :label="$t('assignments.uploadLabel')"
          name="file"
          :error="form.errors.file"
        >
          <v-file-input
            v-model="file"
            prepend-icon=""
            show-size
            density="compact"
            data-test="assignment-file"
            @change="form.setFieldValue('file', $event)"
          />
        </FormField>

        <FormField
          v-if="!assignment.overdue"
          :label="$t('assignments.commentLabel')"
          name="comment"
          :error="form.errors.comment"
        >
          <v-textarea
            v-bind="form.bind('comment')"
            :rows="3"
            density="compact"
            data-test="assignment-comment"
          />
        </FormField>

        <v-btn
          v-if="!assignment.overdue"
          color="primary"
          class="mt-3"
          :loading="saving"
          :disabled="!form.values.comment?.trim() && !form.values.file"
          data-test="assignment-send"
          @click="submit"
        >
          {{ assignment.own_submission ? $t('assignments.resend') : $t('assignments.submit') }}
        </v-btn>

        <!-- Результат проверки виден сразу: главный вопрос после сдачи —
             «что не так», а он в комментарии преподавателя. -->
        <v-alert
          v-if="assignment.own_submission?.reviewed"
          :type="assignment.own_submission.score !== null ? 'success' : 'info'"
          density="compact"
          class="mt-4"
          data-test="assignment-review"
        >
          <p v-if="assignment.own_submission.score !== null">
            {{ $t('assignments.score') }}: {{ assignment.own_submission.score }} / {{ assignment.max_score }}
          </p>
          <p v-if="assignment.own_submission.feedback" class="mt-1">
            {{ assignment.own_submission.feedback }}
          </p>
        </v-alert>
      </section>

      <!-- Проверка работ: преподавателю -->
      <section v-else class="mt-4">
        <h2 class="u-card__title">{{ $t('assignments.reviewTitle', { n: submissions.length }) }}</h2>

        <EmptyState
          v-if="!submissions.length"
          icon="mdi-inbox-arrow-down-outline"
          :title="$t('assignments.noWorks')"
          :text="$t('assignments.noWorksText')"
        />

        <DataTable
          v-else
          class="mt-3"
          :columns="columns"
          :rows="submissions"
          :row-key="(row) => row.id"
          :caption="$t('assignments.reviewTitle', { n: submissions.length })"
        >
          <template #cell-user="{ row }">{{ row.user || $t('assignments.anonymous') }}</template>

          <template #cell-file="{ row }">
            <a v-if="row.file_url" :href="row.file_url" target="_blank" rel="noopener">
              {{ row.file_name }}
            </a>
            <span v-else class="u-muted">—</span>
          </template>

          <template #cell-score="{ row }">
            <span v-if="row.score === null" class="u-muted">—</span>
            <span v-else>{{ row.score }} / {{ assignment.max_score }}</span>
          </template>

          <template #cell-actions="{ row }">
            <v-btn
              size="small"
              variant="text"
              :data-test="`assignment-review-${row.id}`"
              @click="openReview(row)"
            >
              {{ row.reviewed ? $t('assignments.reviewAgain') : $t('assignments.review') }}
            </v-btn>
          </template>
        </DataTable>
      </section>
    </template>

    <!-- Диалог проверки -->
    <v-dialog v-model="review.open" max-width="480">
      <v-card>
        <v-card-title>{{ $t('assignments.review') }}</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-3">{{ review.user }}</p>
          <p v-if="review.comment" class="text-caption mb-3">{{ review.comment }}</p>

          <FormField
            :label="$t('assignments.score')"
            name="score"
            :error="reviewErrors.score"
            :required="true"
          >
            <v-text-field
              v-model="review.score"
              type="number"
              :min="0"
              :max="assignment?.max_score ?? 5"
              data-test="review-score"
              density="compact"
              variant="outlined"
              hide-details
            />
          </FormField>

          <FormField
            :label="$t('assignments.feedback')"
            name="feedback"
            :error="reviewErrors.feedback"
          >
            <v-textarea
              v-model="review.feedback"
              :rows="4"
              density="compact"
              data-test="review-feedback"
            />
          </FormField>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeReview">{{ $t('cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" data-test="review-save" @click="saveReview">
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
import DataTable from '../../components/ui/DataTable.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormField from '../../components/ui/FormField.vue'

/**
 * Домашнее задание: описание, сдача и проверка.
 *
 * Форма сдачи исчезает после дедлайна: бессмысленно принимать работу,
 * на которую уже нельзя повлиять, и человек должен увидеть причину, а
 * не просто исчезнувшую кнопку.
 */
export default {
  name: 'AssignmentShow',
  components: { DataTable, EmptyState, PageHeader, FormField },
  props: {
    idEdit: { type: [Number, String], required: true },
  },
  data() {
    return {
      assignment: null,
      submissions: [],
      canReview: false,
      comment: '',
      file: null,
      saving: false,
      loading: true,
      review: { open: false, id: null, user: null, comment: '', score: 0, feedback: '' },
      reviewErrors: {},
    }
  },
  computed: {
    columns() {
      return [
        { key: 'user', title: this.$t('assignments.student') },
        { key: 'file', title: this.$t('assignments.file') },
        { key: 'comment', title: this.$t('assignments.commentLabel') },
        { key: 'score', title: this.$t('assignments.score'), width: '110px' },
        { key: 'actions', title: '', width: '140px', align: 'right' },
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
        const response = await $api.get(`/api/assignments/${this.idEdit}`)
        const payload = unwrapResponse(response) || {}

        this.assignment = payload
        this.submissions = payload.submissions || []
        this.canReview = response?.meta?.can_review ?? false
      } catch (error) {
        this.assignment = null
      } finally {
        this.loading = false
      }
    },

    async submit() {
      this.saving = true

      try {
        const body = new FormData()
        body.append('comment', this.comment)

        if (this.file) {
          body.append('file', this.file)
        }

        await $api.post(`/api/assignments/${this.idEdit}/submissions`, body, {
          headers: { 'Content-Type': 'multipart/form-data' },
          optional: true,
        })

        this.comment = ''
        this.file = null
        await this.load()
      } finally {
        this.saving = false
      }
    },

    openReview(submission) {
      this.review = {
        open: true,
        id: submission.id,
        user: submission.user,
        comment: submission.comment,
        score: submission.score ?? 0,
        feedback: submission.feedback ?? '',
      }
      this.reviewErrors = {}
    },

    closeReview() {
      this.review.open = false
      this.reviewErrors = {}
    },

    async saveReview() {
      this.reviewErrors = {}

      if (this.review.score === null || this.review.score === '') {
        this.reviewErrors.score = this.$t('assignments.errors.scoreRequired')
        return
      }

      this.saving = true

      try {
        await $api.post(
          `/api/assignments/${this.idEdit}/submissions/${this.review.id}`,
          { score: Number(this.review.score), feedback: this.review.feedback || null },
          { optional: true }
        )

        this.closeReview()
        await this.load()
      } finally {
        this.saving = false
      }
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
.assignment__description {
  white-space: pre-line;
}

.assignment__facts {
  display: grid;
  gap: var(--sp-3) var(--sp-6);
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  margin-top: var(--sp-4);
}

.assignment__facts dt {
  color: var(--c-text-muted);
  font-size: 0.8125rem;
}

.assignment__facts dd {
  font-weight: 600;
  margin: 0;
}
</style>
<template>
  <div>
    <PageHeader :title="topic?.title || $t('forum.title')" :subtitle="topic?.lesson_title">
      <template #actions>
        <v-btn
          v-if="topic && canModerate"
          variant="text"
          :icon="topic.pinned ? 'mdi-pin-off-outline' : 'mdi-pin-outline'"
          :title="$t('forum.pin')"
          data-test="forum-pin"
          @click="togglePinned"
        />
        <v-btn
          v-if="topic && canModerate"
          variant="text"
          :icon="topic.locked ? 'mdi-lock-open-variant-outline' : 'mdi-lock-outline'"
          :title="$t('forum.lock')"
          data-test="forum-lock"
          @click="toggleLocked"
        />
      </template>
    </PageHeader>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="article" />
    </div>

    <EmptyState
      v-else-if="!topic"
      icon="mdi-forum-outline"
      :title="$t('forum.notFoundTitle')"
      :text="$t('forum.notFoundText')"
    />

    <template v-else>
      <article class="u-card u-card__body forum-question" data-test="forum-question">
        <div class="forum-question__badges">
          <span v-if="topic.pinned" class="u-badge">{{ $t('forum.pinned') }}</span>
          <span v-if="topic.solved" class="u-badge u-badge--success">{{ $t('forum.solved') }}</span>
          <span v-if="topic.locked" class="u-badge">{{ $t('forum.locked') }}</span>
          <span v-if="topic.private" class="u-badge">{{ $t('forum.private') }}</span>
        </div>

        <p class="forum-question__body">{{ topic.body }}</p>
        <p class="forum-question__meta">
          {{ topic.author }} · {{ formatDate(topic.created_at) }}
        </p>

        <v-btn
          v-if="canDeleteTopic"
          size="small"
          variant="text"
          color="error"
          class="mt-2"
          data-test="forum-delete-topic"
          @click="confirmOpen = true"
        >
          {{ $t('delete') }}
        </v-btn>
      </article>

      <h2 class="u-card__title mt-6">{{ $t('forum.answers', { n: posts.length }) }}</h2>

      <EmptyState
        v-if="!posts.length"
        icon="mdi-comment-outline"
        :title="$t('forum.noAnswers')"
        :text="$t('forum.noAnswersText')"
      />

      <article
        v-for="post in posts"
        :key="post.id"
        class="u-card u-card__body forum-answer"
        :class="{ 'is-solution': post.id === topic.solution_post_id }"
        :data-test="`forum-post-${post.id}`"
      >
        <header class="forum-answer__head">
          <span class="forum-answer__author">
            {{ post.author }}
            <span v-if="post.from_staff" class="u-badge">{{ $t('forum.staff') }}</span>
          </span>

          <v-spacer />

          <span class="forum-answer__date">{{ formatDate(post.created_at) }}</span>

          <!-- Решение отмечает только преподаватель: это указание
               «правильный ответ», а не мнение участника. -->
          <v-btn
            v-if="canModerate"
            size="x-small"
            variant="text"
            :color="post.id === topic.solution_post_id ? 'success' : undefined"
            :title="$t('forum.markSolution')"
            :data-test="`forum-solve-${post.id}`"
            @click="markSolution(post)"
          >
            <v-icon :icon="post.id === topic.solution_post_id ? 'mdi-check-decagram' : 'mdi-check-decagram-outline'" size="18" />
          </v-btn>

          <v-btn
            v-if="post.author_id === userId || canModerate"
            size="x-small"
            variant="text"
            color="error"
            :title="$t('delete')"
            :data-test="`forum-delete-post-${post.id}`"
            @click="removePost(post)"
          >
            <v-icon icon="mdi-delete-outline" size="18" />
          </v-btn>
        </header>

        <p class="forum-answer__body">{{ post.body }}</p>

        <p v-if="post.id === topic.solution_post_id" class="forum-answer__solution">
          <v-icon icon="mdi-check-decagram" size="14" aria-hidden="true"></v-icon>
          {{ $t('forum.solution') }}
        </p>
      </article>

      <!-- Ответ. В закрытой теме форма убирается: писать всё равно
           нельзя, а поле с кнопкой выглядело бы как поломка. -->
      <div v-if="!topic.locked" class="u-card u-card__body mt-4">
        <v-textarea
          v-model="reply"
          :label="$t('forum.replyLabel')"
          rows="4"
          density="compact"
          data-test="forum-reply"
        />
        <v-btn
          color="primary"
          class="mt-3"
          :loading="saving"
          :disabled="!reply.trim()"
          data-test="forum-reply-send"
          @click="sendReply"
        >
          {{ $t('forum.reply') }}
        </v-btn>
      </div>

      <v-alert v-else type="info" density="compact" class="mt-4" data-test="forum-closed">
        {{ $t('forum.closedHint') }}
      </v-alert>
    </template>

    <ConfirmDialog
      v-model="confirmOpen"
      :title="$t('delete')"
      :text="$t('forum.confirmDeleteTopic')"
      :confirm-text="$t('delete')"
      destructive
      @confirm="removeTopic"
    />
  </div>
</template>

<script>
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import PageHeader from '../../components/ui/PageHeader.vue'

/**
 * Обсуждение: вопрос и ответы.
 *
 * Ответ может отметить как решение только преподаватель
 * (forum.moderate). Обсуждение часто читают несколько человек, и
 * «правильный ответ» должен исходить от того, кто проверяет знания,
 * а не от участника форума.
 */
export default {
  name: 'ForumTopic',
  components: { ConfirmDialog, EmptyState, PageHeader },
  props: {
    idEdit: { type: [Number, String], required: true },
  },
  data() {
    return {
      topic: null,
      posts: [],
      canModerate: false,
      reply: '',
      loading: true,
      saving: false,
      confirmOpen: false,
    }
  },
  computed: {
    userId() {
      return this.$store?.state?.Auth?.user?.id ?? null
    },

    /** Тему удаляет автор (если ответов не было) или преподаватель. */
    canDeleteTopic() {
      if (!this.topic) return false
      if (this.canModerate) return true

      return this.topic.author_id === this.userId && this.posts.length === 0
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true

      try {
        const response = await $api.get(`/api/forum/topics/${this.idEdit}`)
        const payload = unwrapResponse(response) || {}

        this.topic = payload
        this.posts = payload.posts || []
        this.canModerate = response?.meta?.can_moderate ?? false
      } catch (error) {
        this.topic = null
        this.posts = []
      } finally {
        this.loading = false
      }
    },

    async sendReply() {
      this.saving = true

      try {
        await $api.post(`/api/forum/topics/${this.idEdit}/posts`, { body: this.reply })

        this.reply = ''
        await this.load()
      } finally {
        this.saving = false
      }
    },

    async markSolution(post) {
      // Повторное нажатие снимает отметку: иначе решение нельзя было бы
      // исправить, не удаляя ответ.
      const next = post.id === this.topic.solution_post_id ? null : post.id

      await $api.patch(`/api/forum/topics/${this.idEdit}`, { solution_post_id: next }, { optional: true })
      await this.load()
    },

    async togglePinned() {
      await $api.patch(`/api/forum/topics/${this.idEdit}`, { pinned: !this.topic.pinned }, { optional: true })
      await this.load()
    },

    async toggleLocked() {
      await $api.patch(`/api/forum/topics/${this.idEdit}`, { locked: !this.topic.locked }, { optional: true })
      await this.load()
    },

    async removePost(post) {
      await $api.delete(`/api/forum/posts/${post.id}`, { optional: true })
      await this.load()
    },

    async removeTopic() {
      await $api.delete(`/api/forum/topics/${this.idEdit}`, { optional: true })
      await this.load()
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
.forum-question__badges,
.forum-answer__head {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-1);
}

.forum-question__body,
.forum-answer__body {
  margin-top: var(--sp-3);
  white-space: pre-line;
}

.forum-question__meta,
.forum-answer__date {
  color: var(--text-muted);
  font-size: 0.8125rem;
  margin-top: var(--sp-3);
}

.forum-answer {
  margin-top: var(--sp-3);
}

.forum-answer.is-solution {
  border-left: 3px solid var(--c-success);
}

.forum-answer__author {
  align-items: center;
  display: flex;
  font-weight: 600;
  gap: var(--sp-2);
}

.forum-answer__date {
  margin-top: 0;
}

.forum-answer__solution {
  align-items: center;
  color: var(--c-success);
  display: flex;
  font-size: 0.8125rem;
  gap: 4px;
  margin-top: var(--sp-2);
}
</style>
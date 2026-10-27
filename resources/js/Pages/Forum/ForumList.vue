<template>
  <div>
    <PageHeader
      :title="$t('forum.title')"
      :subtitle="$course ? $course.title : $t('forum.hint')"
    >
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" data-test="forum-new" @click="openNew">
          {{ $t('forum.ask') }}
        </v-btn>
      </template>
    </PageHeader>

    <div class="u-card u-card__body forum-filters" v-if="course">
      <v-chip-group v-model="filter" mandatory density="compact" data-test="forum-filter">
        <v-chip value="all" size="small">{{ $t('forum.filterAll') }}</v-chip>
        <v-chip value="unanswered" size="small">{{ $t('forum.filterUnanswered') }}</v-chip>
        <v-chip value="mine" size="small">{{ $t('forum.filterMine') }}</v-chip>
      </v-chip-group>
    </div>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="list-item-avatar-three-line@4" />
    </div>

    <EmptyState
      v-else-if="!visibleTopics.length"
      icon="mdi-forum-outline"
      :title="$t('forum.emptyTitle')"
      :text="$t('forum.emptyText')"
    />

    <div v-else class="forum-list" data-test="forum-list">
      <article
        v-for="topic in visibleTopics"
        :key="topic.id"
        class="u-card u-card__body forum-item"
        :class="{ 'is-pinned': topic.pinned, 'is-solved': topic.solved }"
      >
        <div class="forum-item__stats">
          <span class="forum-item__stat">
            <v-icon icon="mdi-message-text-outline" size="14" aria-hidden="true"></v-icon>
            {{ topic.posts_count }}
          </span>
          <span class="forum-item__stat">
            <v-icon icon="mdi-eye-outline" size="14" aria-hidden="true"></v-icon>
            {{ topic.views }}
          </span>
        </div>

        <div class="forum-item__main">
          <div class="forum-item__badges">
            <span v-if="topic.pinned" class="u-badge">{{ $t('forum.pinned') }}</span>
            <span v-if="topic.solved" class="u-badge u-badge--success">{{ $t('forum.solved') }}</span>
            <span v-if="topic.locked" class="u-badge">{{ $t('forum.locked') }}</span>
            <span v-if="topic.private" class="u-badge">{{ $t('forum.private') }}</span>
            <span v-if="topic.lesson_title" class="u-badge">{{ topic.lesson_title }}</span>
          </div>

          <h2 class="u-card__title forum-item__title">
            <router-link :to="{ name: 'forum.topic', params: { idEdit: topic.id } }">
              {{ topic.title }}
            </router-link>
          </h2>

          <p class="forum-item__body">{{ topic.body }}</p>

          <p class="forum-item__meta">
            {{ topic.author }}
            <template v-if="topic.last_activity_at">
              · {{ $t('forum.activity') }} {{ formatDate(topic.last_activity_at) }}
            </template>
          </p>
        </div>
      </article>
    </div>

    <!-- Новый вопрос -->
    <v-dialog v-model="editor.open" max-width="620">
      <v-card>
        <v-card-title>{{ $t('forum.ask') }}</v-card-title>
        <v-card-text>
          <v-text-field
            v-model="editor.title"
            :label="$t('forum.fieldTitle')"
            data-test="forum-title"
            density="compact"
          />

          <v-textarea
            v-model="editor.body"
            :label="$t('forum.fieldBody')"
            rows="6"
            data-test="forum-body"
            density="compact"
          />

          <v-select
            v-if="lessons.length"
            v-model="editor.lesson_id"
            :items="lessons"
            item-title="title"
            item-value="id"
            :label="$t('forum.fieldLesson')"
            clearable
            density="compact"
          />

          <v-switch
            v-model="editor.private"
            :label="$t('forum.fieldPrivate')"
            color="primary"
            density="compact"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editor.open = false">{{ $t('cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" data-test="forum-save" @click="save">
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
 * Лента вопросов по курсу.
 *
 * Список намеренно не пагинируется: у курса десятки тем, а не тысячи,
 * и вторая страница делала бы «задать вопрос» менее заметным. Если
 * тем станет много, пагинация добавится здесь же — точка входа одна.
 */
export default {
  name: 'ForumList',
  components: { EmptyState, PageHeader },
  props: {
    // course_id приходит из маршрута: форум живёт внутри курса.
    idEdit: { type: [Number, String], required: true },
  },
  data() {
    return {
      topics: [],
      course: null,
      lessons: [],
      filter: 'all',
      loading: true,
      saving: false,
      editor: { open: false, title: '', body: '', lesson_id: null, private: false },
    }
  },
  computed: {
    userId() {
      return this.$store?.state?.Auth?.user?.id ?? null
    },

    /** Темы после фильтра: сервер отдаёт всё, отсеиваем на клиенте. */
    visibleTopics() {
      if (this.filter === 'unanswered') {
        return this.topics.filter((topic) => !topic.solved)
      }

      if (this.filter === 'mine') {
        return this.topics.filter((topic) => topic.author_id === this.userId)
      }

      return this.topics
    },
  },
  async mounted() {
    await this.load()
    await this.loadLessons()
  },
  methods: {
    async load() {
      this.loading = true

      try {
        const response = await $api.get('/api/forum/topics', {
          params: { course_id: this.idEdit },
        })
        const payload = unwrapResponse(response) || []

        this.topics = payload
      } catch (error) {
        this.topics = []
      } finally {
        this.loading = false
      }
    },

    async loadLessons() {
      // Уроки нужны только для выбора «вопрос по этому уроку».
      try {
        const response = await $api.get(`/api/coursemanifest/${this.idEdit}`)
        const manifest = unwrapResponse(response) || {}

        // Манифест отдаёт сам курс, а не конверт с полем course.
        this.course = { title: manifest.title ?? '' }
        this.lessons = (manifest.aukstructures || []).map((item) => ({
          id: item.id,
          title: item.title,
        }))
      } catch (error) {
        this.course = { title: '' }
        this.lessons = []
      }
    },

    openNew() {
      this.editor = { open: true, title: '', body: '', lesson_id: null, private: false }
    },

    async save() {
      this.saving = true

      try {
        await $api.post('/api/forum/topics', {
          title: this.editor.title,
          body: this.editor.body,
          course_id: Number(this.idEdit),
          aukstructure_id: this.editor.lesson_id,
          private: this.editor.private,
        })

        this.editor.open = false
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
.forum-filters {
  margin-bottom: var(--sp-4);
}

.forum-list {
  display: grid;
  gap: var(--sp-3);
}

.forum-item {
  display: flex;
  gap: var(--sp-4);
}

.forum-item.is-pinned {
  border-color: var(--c-primary);
}

.forum-item.is-solved {
  border-left: 3px solid var(--c-success);
}

.forum-item__stats {
  color: var(--text-muted);
  display: flex;
  flex-direction: column;
  font-size: 0.8125rem;
  gap: var(--sp-1);
  min-width: 64px;
}

.forum-item__stat {
  align-items: center;
  display: flex;
  gap: 4px;
}

.forum-item__main {
  flex: 1;
  min-width: 0;
}

.forum-item__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-1);
  margin-bottom: var(--sp-1);
}

.forum-item__title a {
  color: inherit;
  text-decoration: none;
}

.forum-item__title a:hover {
  text-decoration: underline;
}

.forum-item__body {
  color: var(--text-muted);
  margin-top: var(--sp-1);
}

.forum-item__meta {
  color: var(--text-muted);
  font-size: 0.8125rem;
  margin-top: var(--sp-2);
}
</style>
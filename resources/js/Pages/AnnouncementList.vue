<template>
  <div>
    <PageHeader :title="$t('announcements.title')" :subtitle="$t('announcements.hint')">
      <template v-if="canManage" #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" data-test="ann-new" @click="openEditor(null)">
          {{ $t('announcements.new') }}
        </v-btn>
      </template>
    </PageHeader>

    <div v-if="loading" class="u-card u-card__body">
      <v-skeleton-loader type="list-item-avatar-three-line" />
    </div>

    <EmptyState
      v-else-if="!items.length"
      icon="mdi-bullhorn-outline"
      :title="$t('announcements.emptyTitle')"
      :text="$t('announcements.emptyText')"
    />

    <div v-else class="ann-list" data-test="ann-list">
      <article
        v-for="item in items"
        :key="item.id"
        class="u-card u-card__body ann-item"
        :class="{ 'is-pinned': item.pinned }"
        :data-test="`ann-${item.id}`"
      >
        <header class="ann-item__head">
          <h2 class="u-card__title">{{ item.title }}</h2>
          <span v-if="item.pinned" class="u-badge" data-test="ann-pinned">
            <v-icon icon="mdi-pin" size="12" aria-hidden="true"></v-icon>
            {{ $t('announcements.pinned') }}
          </span>
        </header>

        <p class="ann-item__body">{{ item.body }}</p>

        <footer class="ann-item__foot">
          <span v-if="item.author">{{ item.author }}</span>
          <span v-if="item.published_at">{{ formatDate(item.published_at) }}</span>
          <span v-if="item.expires_at">{{ $t('announcements.until') }} {{ formatDate(item.expires_at) }}</span>
          <span v-if="!item.audience_all" class="u-badge">{{ audienceLabel(item) }}</span>

          <v-spacer />

          <template v-if="canManage">
            <v-btn size="small" variant="text" :data-test="`ann-edit-${item.id}`" @click="openEditor(item)">
              {{ $t('edit') }}
            </v-btn>
            <v-btn
              size="small"
              variant="text"
              color="error"
              :data-test="`ann-del-${item.id}`"
              @click="confirmRemove(item)"
            >
              {{ $t('delete') }}
            </v-btn>
          </template>
        </footer>
      </article>
    </div>

    <!-- Создание и правка -->
    <v-dialog v-model="editor.open" max-width="640">
      <v-card>
        <v-card-title>
          {{ editor.id ? $t('announcements.editTitle') : $t('announcements.newTitle') }}
        </v-card-title>
        <v-card-text>
          <v-text-field
            v-model="editor.title"
            :label="$t('announcements.fieldTitle')"
            data-test="ann-title"
            density="compact"
          />

          <v-textarea
            v-model="editor.body"
            :label="$t('announcements.fieldBody')"
            rows="6"
            data-test="ann-body"
            density="compact"
          />

          <v-switch
            v-model="editor.pinned"
            :label="$t('announcements.fieldPinned')"
            data-test="ann-pinned-switch"
            color="primary"
            density="compact"
          />

          <div class="d-flex ga-4">
            <v-text-field
              v-model="editor.published_at"
              :label="$t('announcements.fieldPublishedAt')"
              type="date"
              density="compact"
            />
            <v-text-field
              v-model="editor.expires_at"
              :label="$t('announcements.fieldExpiresAt')"
              type="date"
              density="compact"
            />
          </div>

          <v-switch
            v-model="editor.audience_all"
            :label="$t('announcements.fieldAll')"
            data-test="ann-all-switch"
            color="primary"
            density="compact"
          />

          <template v-if="!editor.audience_all">
            <v-select
              v-model="editor.audience_groups"
              :items="audiences.groups"
              item-title="name"
              item-value="id"
              :label="$t('announcements.fieldGroups')"
              multiple
              chips
              density="compact"
              data-test="ann-groups"
            />
            <v-select
              v-model="editor.audience_courses"
              :items="audiences.courses"
              item-title="name"
              item-value="id"
              :label="$t('announcements.fieldCourses')"
              multiple
              chips
              density="compact"
              data-test="ann-courses"
            />
            <v-select
              v-model="editor.audience_roles"
              :items="audiences.roles"
              item-title="name"
              item-value="name"
              :label="$t('announcements.fieldRoles')"
              multiple
              chips
              density="compact"
            />
          </template>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="editor.open = false">{{ $t('cancel') }}</v-btn>
          <v-btn color="primary" :loading="saving" data-test="ann-save" @click="save">
            {{ $t('save') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Удаление: необратимое действие, спрашиваем отдельно. -->
    <ConfirmDialog
      v-model="confirm.open"
      :title="$t('delete')"
      :text="$t('announcements.confirmDelete')"
      :confirm-text="$t('delete')"
      destructive
      @confirm="remove"
    />
  </div>
</template>

<script>
import $api from '../api/httpClient'
import { unwrapResponse } from '../api/envelope'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'

/**
 * Лента объявлений.
 *
 * Одну и ту же страницу видят и редактор, и обучаемый: право на
 * публикацию включает кнопки управления, но не меняет состав ленты —
 * так куда смотреть за правками не нужно.
 */
export default {
  name: 'AnnouncementList',
  components: { ConfirmDialog, EmptyState, PageHeader },
  data() {
    return {
      items: [],
      audiences: { groups: [], courses: [], roles: [] },
      loading: true,
      saving: false,
      canManage: false,
      editor: { open: false, id: null, title: '', body: '', pinned: false, audience_all: true, audience_groups: [], audience_courses: [], audience_roles: [], published_at: '', expires_at: '' },
      confirm: { open: false, item: null },
    }
  },
  computed: {
    user() {
      return this.$store?.state?.Auth?.user ?? {}
    },
  },
  async mounted() {
    await this.load()
    await this.loadAudiences()
  },
  methods: {
    async load() {
      this.loading = true

      try {
        const response = await $api.get('/api/announcements')
        this.items = unwrapResponse(response) || []
      } catch (error) {
        this.items = []
      } finally {
        this.loading = false
      }
    },

    async loadAudiences() {
      // Справочники нужны только редактору: тянуть группы и курсы
      // обучаемому незачем.
      if (!this.canManage) return

      try {
        const response = await $api.get('/api/announcements/audiences')
        this.audiences = unwrapResponse(response) || this.audiences
      } catch (error) {
        this.audiences = { groups: [], courses: [], roles: [] }
      }
    },

    async checkPermission() {
      const perms = this.$store?.getters?.['Auth/permissionSlugs'] || []
      this.canManage = perms.includes('announcements.manage')
    },

    openEditor(item) {
      this.editor = item
        ? {
            open: true,
            id: item.id,
            title: item.title,
            body: item.body,
            pinned: item.pinned,
            audience_all: item.audience_all,
            audience_groups: [...(item.audience_groups || [])],
            audience_courses: [...(item.audience_courses || [])],
            audience_roles: [...(item.audience_roles || [])],
            published_at: (item.published_at || '').slice(0, 10),
            expires_at: (item.expires_at || '').slice(0, 10),
          }
        : { open: true, id: null, title: '', body: '', pinned: false, audience_all: true, audience_groups: [], audience_courses: [], audience_roles: [], published_at: '', expires_at: '' }
    },

    async save() {
      this.saving = true

      try {
        const payload = {
          title: this.editor.title,
          body: this.editor.body,
          pinned: this.editor.pinned,
          audience_all: this.editor.audience_all,
          audience_groups: this.editor.audience_all ? null : this.editor.audience_groups,
          audience_courses: this.editor.audience_all ? null : this.editor.audience_courses,
          audience_roles: this.editor.audience_all ? null : this.editor.audience_roles,
          published_at: this.editor.published_at || null,
          expires_at: this.editor.expires_at || null,
        }

        if (this.editor.id) {
          await $api.put(`/api/announcements/${this.editor.id}`, payload)
        } else {
          await $api.post('/api/announcements', payload)
        }

        this.editor.open = false
        await this.load()
      } finally {
        this.saving = false
      }
    },

    confirmRemove(item) {
      this.confirm = { open: true, item }
    },

    async remove() {
      if (!this.confirm.item) return

      await $api.delete(`/api/announcements/${this.confirm.item.id}`, { optional: true })
      await this.load()
    },

    audienceLabel(item) {
      const parts = []

      if (item.audience_groups?.length) parts.push(this.$t('announcements.groupsLabel', { n: item.audience_groups.length }))
      if (item.audience_courses?.length) parts.push(this.$t('announcements.coursesLabel', { n: item.audience_courses.length }))
      if (item.audience_roles?.length) parts.push(this.$t('announcements.rolesLabel', { n: item.audience_roles.length }))

      return parts.join(', ')
    },

    formatDate(value) {
      if (!value) return ''

      return new Date(value).toLocaleDateString('ru-RU')
    },
  },

  created() {
    // Право проверяем сразу: от него зависит наличие кнопки публикации,
    // а лента грузится параллельно.
    this.checkPermission()
  },
}
</script>

<style scoped>
.ann-list {
  display: grid;
  gap: var(--sp-3);
}

.ann-item.is-pinned {
  border-color: var(--c-primary);
}

.ann-item__head {
  align-items: center;
  display: flex;
  gap: var(--sp-2);
}

.ann-item__body {
  margin-top: var(--sp-2);
  white-space: pre-line;
}

.ann-item__foot {
  align-items: center;
  color: var(--c-text-muted);
  display: flex;
  flex-wrap: wrap;
  font-size: 0.8125rem;
  gap: var(--sp-3);
  margin-top: var(--sp-3);
}
</style>
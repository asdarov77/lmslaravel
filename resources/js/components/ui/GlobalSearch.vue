<template>
  <div class="u-search">
    <button
      type="button"
      class="u-search__trigger"
      :aria-label="$t('search.open')"
      aria-haspopup="dialog"
      data-test="search-trigger"
      @click="open"
    >
      <v-icon icon="mdi-magnify" size="20" aria-hidden="true"></v-icon>
      <span class="u-search__hint">{{ $t("search.placeholder") }}</span>
      <kbd class="u-search__kbd" aria-hidden="true">{{ shortcut }}</kbd>
    </button>

    <v-dialog
      v-model="dialog"
      max-width="var(--modal-width-lg)"
      scrollable
      data-test="search-dialog"
      @after-enter="focusInput"
    >
      <v-card>
        <v-text-field
          ref="input"
          v-model="query"
          :placeholder="$t('search.placeholder')"
          :label="$t('search.label')"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="comfortable"
          hide-details
          autofocus
          data-test="search-input"
          @update:model-value="onInput"
          @keydown.enter.prevent="goFirst"
          @keydown.esc="close"
        />

        <v-progress-linear v-if="loading" indeterminate color="primary" />

        <v-card-text class="u-search__results">
          <!--
              Запрос короче двух символов: сервер всё равно отвечает 422,
              а пустой диалог без пояснения выглядит как поломка.
          -->
          <p v-if="tooShort" class="u-search__hint-text">
            {{ $t("search.tooShort") }}
          </p>

          <p v-else-if="error" class="u-search__hint-text">{{ error }}</p>

          <template v-else-if="groups.length">
            <section
              v-for="group in groups"
              :key="group.key"
              class="u-search__group"
              :aria-label="$t(group.titleKey)"
            >
              <h3 class="u-search__group-title">{{ $t(group.titleKey) }}</h3>
              <v-list density="compact" bg-color="transparent">
                <v-list-item
                  v-for="item in group.items"
                  :key="`${group.key}-${item.id}`"
                  :title="item.title"
                  :subtitle="item.subtitle || undefined"
                  :to="item.to || undefined"
                  :prepend-icon="icons[group.key] || 'mdi-dot'"
                  rounded="lg"
                  data-test="search-result"
                  @click="close"
                ></v-list-item>
              </v-list>
            </section>
          </template>

          <p v-else-if="searched" class="u-search__hint-text">
            {{ $t("search.empty") }}
          </p>

          <p v-else class="u-search__hint-text">{{ $t("search.hint") }}</p>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import { searchGlobal } from '../../api/search.api'

/** Задержка перед запросом: без неё каждая буква порождает вызов. */
const DEBOUNCE_MS = 250

export default {
  name: 'GlobalSearch',

  data: () => ({
    dialog: false,
    query: '',
    loading: false,
    searched: false,
    error: '',
    result: { total: 0, groups: {} },
    timer: null,
    /** Номер последнего запроса: ответ приходит асинхронно, и без
     *  этой проверки медленный ответ перетирал бы свежий. */
    requestId: 0,
  }),

  computed: {
    ...mapGetters('Auth', ['loggedIn']),

    groups() {
      return Object.entries(this.result.groups || {}).map(([key, items]) => ({
        key,
        items,
        titleKey: `search.groups.${key}`,
      }))
    },

    tooShort() {
      return this.query.trim().length > 0 && this.query.trim().length < 2
    },

    shortcut() {
      return typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || '')
        ? '⌘K'
        : 'Ctrl+K'
    },

    icons: () => ({
      courses: 'mdi-book-open-page-variant-outline',
      modules: 'mdi-file-tree-outline',
      categories: 'mdi-shape-outline',
      groups: 'mdi-account-group-outline',
      users: 'mdi-account-outline',
      questions: 'mdi-help-circle-outline',
    }),
  },

  mounted() {
    window.addEventListener('keydown', this.onHotkey)
  },

  beforeUnmount() {
    window.removeEventListener('keydown', this.onHotkey)
    if (this.timer) clearTimeout(this.timer)
  },

  methods: {
    open() {
      if (!this.loggedIn) return
      this.dialog = true
    },

    close() {
      this.dialog = false
    },

    focusInput() {
      // После появления диалога поле ещё не в фокусе: без этого
      // пришлось бы кликать по нему мышью.
      this.$nextTick(() => {
        const el = this.$refs.input?.$el?.querySelector('input')
        el?.focus()
      })
    },

    onHotkey(event) {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        this.dialog ? this.close() : this.open()
      }
    },

    onInput(value) {
      if (this.timer) clearTimeout(this.timer)

      if (value.trim().length < 2) {
        this.loading = false
        this.searched = false
        this.error = ''
        this.result = { total: 0, groups: {} }
        return
      }

      this.timer = setTimeout(() => this.run(value.trim()), DEBOUNCE_MS)
    },

    async run(term) {
      const id = ++this.requestId
      this.loading = true
      this.error = ''

      try {
        const response = await searchGlobal(term)
        if (id !== this.requestId) return

        const payload = response.data?.data ?? response.data
        this.result = {
          total: Number(payload?.total ?? 0),
          groups: payload?.groups ?? {},
        }
        this.searched = true
      } catch (error) {
        if (id !== this.requestId) return
        this.error =
          error?.response?.data?.error?.message || this.$t('search.failed')
        this.result = { total: 0, groups: {} }
      } finally {
        if (id === this.requestId) this.loading = false
      }
    },

    goFirst() {
      const first = this.groups[0]?.items?.[0]
      if (!first || !first.to) return
      this.close()
      this.$router.push(first.to).catch(() => {})
    },
  },
}
</script>

<style scoped>
.u-search {
  flex: 1 1 auto;
  min-width: 0;
}

.u-search__trigger {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  width: 100%;
  max-width: 420px;
  padding: var(--sp-1) var(--sp-3);
  border: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.12);
  color: inherit;
  cursor: pointer;
  font: inherit;
}

.u-search__trigger:hover,
.u-search__trigger:focus-visible {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.6);
}

.u-search__hint {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
  opacity: 0.85;
  font-size: 0.875rem;
}

.u-search__kbd {
  flex: none;
  padding: 0 var(--sp-2);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  opacity: 0.85;
}

.u-search__results {
  min-height: 180px;
}

.u-search__group + .u-search__group {
  margin-top: var(--sp-3);
}

.u-search__group-title {
  margin: 0 0 var(--sp-1);
  font-size: 0.75rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--c-text-secondary);
}

.u-search__hint-text {
  margin: 0;
  color: var(--c-text-secondary);
}
</style>

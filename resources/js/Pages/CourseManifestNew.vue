<!--
  CourseManifest — плеер курса, переработанный на composables (этап 5).

  Архитектура:
    useCoursePlayer   — данные (player-endpoint): дерево/entry/resume/stats (Д2, Д3, Д4);
    useContentFrame   — iframe: высота через ResizeObserver, resume-скролл (Д6, Д9);
    useLessonProgress — серверный+локальный прогресс, событийный /view (Д6, P2);
    useInDocumentSearch — поиск + серверная подсветка span.cp-hl (Д5, Д10).

  Закрытые дефекты старых версий:
    Д1 — контент отдаётся endpoint'ом с Cache-Control: no-store, подпись/базы во
         фронтенд-кэше больше нет; кэшируются только метаданные;
    Д2 — entry.content_url приходит из player-endpoint, лишних RTT к getlink нет;
    Д3/Д4 — is_material и корни дерева считает сервер (CoursePlayerService);
    Д7 — удалены мёртвые watch/getFirstAukId/beforeDestroy/curAuk/lessonPercent;
    Д8 — prev/next берутся из узлов сервера (единый цикл урока), а не дублируются;
    Д9 — ResizeObserver + догрузка картинок;
    Д10 — доступное дерево (CourseTree), Fullscreen API, debounce поиска.

  Маршрут/пропсы сохранены (/courses/itemmani?idEdit=&idCategory=) — обратная совместимость.
-->
<template>
  <div
    id="course-player-root"
    class="cp"
    :class="{ 'cp--fullscreen': isFullscreen }"
  >
    <!-- верхняя панель курса -->
    <v-app-bar flat density="compact" class="cp__bar">
      <v-btn icon="mdi-arrow-left" :aria-label="$t('courseManifest.backToCourses')" @click="$router.back()" />
      <v-toolbar-title class="cp__title text-truncate">{{ courseTitle }}</v-toolbar-title>
      <template #append>
        <span class="cp__progress-caption me-3">{{ progressCaption }}</span>
        <v-progress-circular
          :model-value="stats.percent"
          size="34"
          width="4"
          color="primary"
          :aria-label="$t('courseManifest.progressAria', { percent: stats.percent })"
        >{{ stats.percent }}</v-progress-circular>
      </template>
    </v-app-bar>

    <v-sheet v-if="state.error" class="cp__error ma-4 pa-4 rounded" color="error-light-1" role="alert">
      {{ $t(state.error) }}
      <v-btn size="small" variant="text" prepend-icon="mdi-refresh" @click="init">{{ $t('courseManifest.retry') }}</v-btn>
    </v-sheet>

    <v-row v-else dense no-gutters class="cp__body">
      <!-- левая колонка: оглавление / поиск / избранное -->
      <v-col cols="12" md="4" lg="3" class="cp__side-col">
        <v-sheet class="cp__side rounded-lg" elevation="2">
          <div class="cp__tabs" role="tablist" :aria-label="$t('courseManifest.sidebarAria')">
            <button
              v-for="t in SIDEBAR_TABS"
              :key="t.id"
              class="cp__tab"
              :class="{ 'cp__tab--active': sidebarTab === t.id }"
              role="tab"
              :aria-selected="sidebarTab === t.id"
              :aria-label="t.label"
              @click="sidebarTab = t.id"
            >
              <v-icon size="20">{{ t.icon }}</v-icon>
            </button>
            <v-spacer />
            <v-btn icon size="x-small" :aria-label="$t('courseManifest.collapsePanel')" class="d-md-none" @click="mobilePanelOpen = false">
              <v-icon size="18">mdi-close</v-icon>
            </v-btn>
          </div>

          <!-- ОГЛАВЛЕНИЕ -->
          <div v-show="sidebarTab === 'toc'" class="cp__panel" role="tabpanel" :aria-label="$t('courseManifest.tabToc')">
            <v-skeleton-loader v-if="state.loading" type="list-item@6" />
            <CourseTree
              v-else
              :nodes="state.nodes"
              :active-id="activeItemId"
              :tab-id="treeTabId"
              @select="onSelectNode"
              @set-tab="treeTabId = $event"
            />
            <div v-if="!state.loading && state.nodes.length === 0" class="cp__empty">
              {{ $t('courseManifest.emptyContents') }}
            </div>
          </div>

          <!-- ПОИСК -->
          <div v-show="sidebarTab === 'search'" class="cp__panel" role="tabpanel" :aria-label="$t('courseManifest.tabSearch')">
            <v-text-field
              v-model="search.query.value"
              density="compact"
              variant="outlined"
              rounded
              :label="$t('courseManifest.searchLabel')"
              :hint="$t('common.minCharsHint')"
              persistent-hint
              clearable
              single-line
              :loading="search.searching.value"
              append-inner-icon="mdi-magnify"
              :aria-label="$t('courseManifest.searchFieldAria')"
              @update:model-value="search.onQueryInput"
              @click:append-inner="search.runSearch"
              @keyup.enter="search.runSearch"
            />
            <ul v-if="search.results.value.length" class="cp__results">
              <li class="cp__results-meta">{{ $t('courseManifest.foundTotal') }} {{ search.results.value.length }}</li>
              <li v-for="(r, i) in search.results.value" :key="(r.itemId || 0) + '-' + r.file + '-' + i">
                <button class="cp__result-btn" @click="openFromSearch(r)">
                  <span class="cp__result-title">{{ r.title || r.file }}</span>
                  <span v-if="r.snippet" class="cp__result-snippet">{{ r.snippet }}</span>
                </button>
              </li>
            </ul>
            <p v-else-if="search.searchedOnce.value && !search.searching.value" class="cp__empty">{{ $t('courseManifest.noResults') }}</p>
          </div>

          <!-- ИЗБРАННОЕ -->
          <div v-show="sidebarTab === 'fav'" class="cp__panel" role="tabpanel" :aria-label="$t('courseManifest.tabFav')">
            <ul v-if="favorites.length" class="cp__results">
              <li v-for="item in favorites" :key="item.id">
                <button class="cp__result-btn" @click="openFavorite(item)">
                  <span class="cp__result-title">{{ item.title }}</span>
                </button>
                <v-btn icon size="x-small" :aria-label="$t('courseManifest.removeFavoriteShort')" @click="removeFavorite(item.course_id)">
                  <v-icon size="14">mdi-close</v-icon>
                </v-btn>
              </li>
            </ul>
            <p v-else class="cp__empty">{{ $t('courseManifest.favoritesEmpty') }}</p>
          </div>
        </v-sheet>
      </v-col>

      <!-- правая часть: кадр материала -->
      <v-col cols="12" md="8" lg="9" class="cp__main-col">
        <v-sheet class="cp__frame-wrap rounded-lg" elevation="3">
          <CoursePlayerToolbar
            :container="rootEl"
            :loading="frame.loading.value"
            :failed="frame.failed.value"
            :has-marks="search.hasMarks.value"
            :counter-label="search.counterLabel.value"
            @prev-mark="search.prevMark"
            @next-mark="search.nextMark"
          />

          <div class="cp__mat-head">
            <h2 class="cp__mat-title text-truncate-2">{{ activeNode ? activeNode.title : '' }}</h2>

            <!-- Ручной режим высоты кадра (best practice LMS: fit / window / fixed). -->
            <v-menu offset-y>
              <template #activator="{ props: menuProps }">
                <v-btn
                  icon
                  size="small"
                  v-bind="menuProps"
                  :aria-label="$t('courseManifest.heightModeTitle')"
                  :title="$t('courseManifest.heightModeTitle')"
                >
                  <v-icon size="20">mdi-resize</v-icon>
                </v-btn>
              </template>
              <v-list density="compact" nav>
                <v-list-item
                  v-for="m in HEIGHT_MODES"
                  :key="m.id"
                  :prepend-icon="frame.heightMode.value === m.id ? 'mdi-check' : undefined"
                  :title="m.label"
                  @click="frame.setHeightMode(m.id)"
                />
                <v-expand-transition>
                  <div v-if="frame.heightMode.value === 'fixed'" class="px-4 pb-2">
                    <v-slider
                      :model-value="frame.fixedHeight.value"
                      :label="$t('courseManifest.heightFixedHint')"
                      min="320"
                      max="2000"
                      step="40"
                      hide-details
                      dense
                      @update:model-value="frame.setFixedHeight"
                    />
                  </div>
                </v-expand-transition>
              </v-list>
            </v-menu>

            <v-btn
              icon
              size="small"
              :color="isFavoriteActive ? 'amber' : undefined"
              :aria-label="isFavoriteActive ? $t('courseManifest.removeFavorite') : $t('courseManifest.addFavorite')"
              @click="toggleFavoriteActive"
            >
              <v-icon size="20">{{ isFavoriteActive ? 'mdi-star' : 'mdi-star-outline' }}</v-icon>
            </v-btn>
          </div>

          <iframe
            ref="iframeRef"
            class="cp__iframe"
            :style="{ height: currentFrameHeight }"
            :title="activeNode ? activeNode.title : $t('courseManifest.material')"
            scrolling="auto"
            @load="onFrameLoad"
            @error="frame.failed.value = true"
          ></iframe>

          <v-progress-linear v-if="frame.loading.value" indeterminate color="primary" />

          <!-- Заметки студента к текущему материалу (перенесены из legacy-плеера). -->
          <div v-if="activeItemId != null" class="cp__notes px-3 pb-2">
            <MaterialNotes :material-id="activeItemId" :course-id="idEdit" />
          </div>

          <!-- pager: единый источник prev/next — серверные поля узла (Д8).
               Кнопки показываем всегда, неактивные — disabled: пользователь
               не теряет отметку конца курса на единственном материале. -->
          <nav class="cp__pager" :aria-label="$t('courseManifest.pagerAria')">
            <v-btn
              :disabled="!prevNode"
              variant="tonal"
              prepend-icon="mdi-chevron-left"
              :aria-label="prevNode ? $t('courseManifest.prevWith', { title: prevNode.title }) : $t('courseManifest.noPrev')"
              @click="go(prevNode)"
            >{{ $t('courseManifest.prevBtn') }}</v-btn>
            <span class="cp__pager-pos">{{ positionLabel }}</span>
            <v-btn
              :disabled="!nextNode"
              variant="tonal"
              append-icon="mdi-chevron-right"
              :aria-label="nextNode ? $t('courseManifest.nextWith', { title: nextNode.title }) : $t('courseManifest.noNext')"
              @click="go(nextNode)"
            >{{ $t('courseManifest.nextBtn') }}</v-btn>
          </nav>
        </v-sheet>
      </v-col>
    </v-row>

    <!-- мобильная кнопка открытия панели -->
    <v-btn
      v-if="!mobilePanelOpen"
      class="cp__fab d-md-none"
      icon="mdi-view-list"
      color="primary"
      fab
      fixed
      bottom
      :aria-label="$t('courseManifest.openContents')"
      @click="mobilePanelOpen = true"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useStore } from 'vuex'
import { useI18n } from 'vue-i18n'
import $api from '../api/httpClient'
import { contentFrameUrl } from '../api/player.api'
import { TokenService } from '../services/storage.service'
import { useCoursePlayer } from '../composables/useCoursePlayer'
import { useContentFrame } from '../composables/useContentFrame'
import { useLessonProgress } from '../composables/useLessonProgress'
import { useInDocumentSearch } from '../composables/useInDocumentSearch'
import CourseTree from '../components/course/CourseTree.vue'
import CoursePlayerToolbar from '../components/course/CoursePlayerToolbar.vue'
import MaterialNotes from './Course/MaterialNotes.vue'

const props = defineProps({
  idEdit: { type: Number, required: true },     // id курса (сохранён старый контракт маршрута)
  idCategory: { type: Number, default: null },
})

const { t } = useI18n()

const SIDEBAR_TABS = computed(() => [
  { id: 'toc', icon: 'mdi-view-list', label: t('courseManifest.tabToc') },
  { id: 'search', icon: 'mdi-magnify', label: t('courseManifest.tabSearch') },
  { id: 'fav', icon: 'mdi-star-outline', label: t('courseManifest.tabFav') },
])

const HEIGHT_MODES = computed(() => [
  { id: 'fit', label: t('courseManifest.heightFit') },
  { id: 'window', label: t('courseManifest.heightWindow') },
  { id: 'fixed', label: t('courseManifest.heightFixed') },
])

/* ------------------------- состояние ------------------------- */

const store = useStore()
const courseIdRef = computed(() => props.idEdit)

const player = useCoursePlayer(courseIdRef)
const { state, nodeById, materials, materialIds } = player

const iframeRef = ref(null)
const rootEl = ref(null)
const frame = useContentFrame(iframeRef)

const aircraftRef = ref(null)
const pathRef = ref('')

const search = useInDocumentSearch({
  courseIdRef,
  aircraftRef,
  pathRef,
  iframeRef,
})

const progress = useLessonProgress(courseIdRef)

const activeItemId = ref(null)
const resumeAnchor = ref(null)   // якорь для прокрутки после загрузки кадра (Д6)
const treeTabId = ref(null)
const sidebarTab = ref('toc')
const isFullscreen = ref(false)
const mobilePanelOpen = ref(true)
const highlightTerm = ref('') // термин для серверной подсветки текущего кадра

/* ------------------------- производные ------------------------- */

const courseTitle = computed(() => state.course?.title || '')
const stats = computed(() => state.stats)
const progressCaption = computed(() =>
  t('courseManifest.materialsVisited', { visited: stats.value.materials_visited, total: stats.value.materials_total })
)
/** Итоговая высота кадра: зависит от frameHeight, чтобы пересчитываться в режиме fit. */
const currentFrameHeight = computed(() => {
  void frame.frameHeight.value
  return frame.effectiveHeight() + 'px'
})
const activeNode = computed(() =>
  activeItemId.value != null ? nodeById.value.get(activeItemId.value) || null : null
)
// Д8: prev/next — серверные поля узла, никакой локальной дублирующей логики
const prevNode = computed(() =>
  activeNode.value?.prev_id != null ? nodeById.value.get(activeNode.value.prev_id) || null : null
)
const nextNode = computed(() =>
  activeNode.value?.next_id != null ? nodeById.value.get(activeNode.value.next_id) || null : null
)
const positionLabel = computed(() => {
  const ids = materialIds.value
  if (!ids.length || activeItemId.value == null) return ''
  return `${ids.indexOf(activeItemId.value) + 1} / ${ids.length}`
})

/* ------------------------- избранные курсы ------------------------- */

const favorites = ref([])
const isFavoriteActive = computed(() =>
  favorites.value.some((f) => String(f.course_id) === String(activeItemId.value))
)

async function getFavorites() {
  try {
    const { data } = await $api.get('/api/favorites/')
    favorites.value = data.favorites || []
  } catch (e) {
    favorites.value = []
  }
}

async function removeFavorite(id) {
  await $api.delete(`/api/favorites/${id}`).catch(() => {})
  getFavorites()
}

function toggleFavoriteActive() {
  if (activeItemId.value == null) return
  if (isFavoriteActive.value) {
    const f = favorites.value.find((x) => String(x.course_id) === String(activeItemId.value))
    if (f) removeFavorite(f.course_id ?? f.id)
    return
  }
  $api
    .post('/api/favorites/add', { course_id: activeItemId.value, title: activeNode.value?.title })
    .then(getFavorites)
    .catch(() => {})
}

function openFavorite(item) {
  sidebarTab.value = 'toc'
  const node = nodeById.value.get(Number(item.course_id))
  if (node) onSelectNode(node.id)
}

/* ------------------------- открытие материалов ------------------------- */

function buildFrameSrc(itemId) {
  return contentFrameUrl(props.idEdit, itemId, { highlight: highlightTerm.value })
}

/** Центральный метод открытия материала (из дерева, поиска, prev/next, resume). */
function openItem(itemId, { anchor = null, highlight = '' } = {}) {
  if (itemId == null) return
  activeItemId.value = Number(itemId)
  treeTabId.value = Number(itemId)
  highlightTerm.value = highlight
  resumeAnchor.value = anchor   // прокрутка выполнится в onFrameLoad (Д6)
  frame.navigate(buildFrameSrc(itemId))
  progress.openLesson(Number(itemId), anchor)
  if (!state.error) player.markVisited(Number(itemId))
}

function onSelectNode(itemId) {
  openItem(itemId)
  if (window.innerWidth < 960) mobilePanelOpen.value = false
}

function go(node) {
  if (node) openItem(node.id)
}

function openFromSearch(result) {
  // Д5: ведём ровно на тот узел, где найдено совпадение (itemIds сервера),
  // подсветку ставит сервер через ?highlight=term
  const target = result.itemId ?? (Array.isArray(result.itemIds) ? result.itemIds[0] : null)
  if (target == null) return
  highlightTerm.value = search.query.value.trim()
  openItem(target, { highlight: highlightTerm.value })
  sidebarTab.value = 'toc'
}

/* ------------------------- загрузка кадра ------------------------- */

function throttle(fn, ms) {
  let last = 0
  return (...args) => {
    const now = Date.now()
    if (now - last >= ms) {
      last = now
      fn(...args)
    }
  }
}

function attachScrollTracking() {
  const win = iframeRef.value?.contentWindow
  if (!win) return
  const doc = win.document
  const onScroll = throttle(() => {
    const el = doc.scrollingElement || doc.documentElement
    const max = el.scrollHeight - el.clientHeight
    if (max > 0) progress.reportScroll((el.scrollTop / max) * 100)
  }, 1000)
  win.removeEventListener('scroll', onScroll) // идемпотентно для повторных load
  win.addEventListener('scroll', onScroll, { passive: true })
}

function onFrameLoad() {
  frame.onLoad()
  // resume-point: прокрутка к якорю после отрисовки (Д6)
  if (resumeAnchor.value) {
    frame.scrollToAnchor(resumeAnchor.value)
    resumeAnchor.value = null
  }
  // серверная подсветка уже в DOM кадра — собираем span.cp-hl (Д5)
  if (highlightTerm.value) {
    nextTick(() => search.collectMarks())
  } else {
    search.resetMarks()
  }
  attachScrollTracking()
  prefetchNext()
}

/* ------------------------- prefetch следующего материала ------------------------- */

// Warm-keepalive: сразу после отрисовки текущего кадра тихо прогреваем HTTP-кэш
// браузера следующим материалом по цепочке prev/next (Д8). Следующий клик по
// «Далее» выбирает документ из кэша без задержки на выборку/обработку сервером.
// no-store у HTML-ответа не мешает: fetch с тем же URL повторит только дешёвый
// RTT, а сам файл (и его ресурсы через raw/) останутся в памяти процесса.
let prefetchedId = null
let prefetchController = null

function prefetchNext() {
  const next = nextNode.value
  if (!next || next.id === activeItemId.value || next.id === prefetchedId) return
  prefetchedId = next.id

  prefetchController?.abort()
  prefetchController = new AbortController()
  const idle = window.requestIdleCallback || ((cb) => setTimeout(cb, 1500))
  idle(() => {
    if (prefetchedId !== next.id) return // пользователь уже ушёл дальше
    fetch(buildFrameSrc(next.id), {
      credentials: 'same-origin',
      signal: prefetchController.signal,
      priority: 'low',
    }).catch(() => { /* warm-up, ошибка не критична */ })
  })
}

/* ------------------------- инициализация ------------------------- */

let scrollKeyHandler = null

// Полноэкранный режим чтения (CoursePlayerToolbar вызывает requestFullscreen
// на rootEl): синхронизируем класс и следим за выходом через Escape/браузер.
function onFsChange() {
  isFullscreen.value = document.fullscreenElement === rootEl.value
}

async function init() {
  try {
    await player.load()
  } catch (e) {
    return
  }

  // мета для поиска: aircraft/path берём из payload курса (сервер знает лучше фронта)
  pathRef.value = state.course?.path || ''
  aircraftRef.value = state.course?.aircraft_id ?? null

  // Д6: точка возврата — серверный resume > localStorage > entry.
  // Валидируем по списку материалов: resume может указывать на узел,
  // удалённый после переимпорта курса.
  const validIds = new Set(materialIds.value)
  let start = progress.resolveStartItem(state.resume, validIds)
  if (!start && state.resume?.last_item_id && !validIds.has(state.resume.last_item_id)) {
    start = { itemId: state.entry?.item_id, anchor: null } // resume устарел — идём к entry
  }
  if (start?.itemId && validIds.has(start.itemId)) {
    openItem(start.itemId, { anchor: start.anchor || null })
  } else if (state.entry) {
    openItem(state.entry.item_id) // entry.content_url уже в payload — 0 лишних RTT (Д2)
  }
}

onMounted(async () => {
  rootEl.value = document.getElementById('course-player-root')
  getFavorites()
  if (!TokenService.getToken()) {
    state.error = 'courseManifest.loginRequired'
    return
  }
  await init()
  progress.bindUnloadGuards()

  // клавиатура глобально: [ ] prev/next, / — фокус в поиск (Д10)
  scrollKeyHandler = (e) => {
    const tag = (e.target?.tagName || '').toLowerCase()
    if (tag === 'input' || tag === 'textarea') return
    if (e.key === '[') go(prevNode.value)
    else if (e.key === ']') go(nextNode.value)
    else if (e.key === '/') {
      e.preventDefault()
      sidebarTab.value = 'search'
    } else if (e.key === 'Escape' && document.fullscreenElement) {
      document.exitFullscreen().catch(() => {})
    }
  }
  window.addEventListener('keydown', scrollKeyHandler)
  document.addEventListener('fullscreenchange', onFsChange)
})

onBeforeUnmount(() => {
  // Д7: beforeUnmount вместо beforeDestroy — слушатель гарантированно снимается
  if (scrollKeyHandler) window.removeEventListener('keydown', scrollKeyHandler)
  document.removeEventListener('fullscreenchange', onFsChange)
  progress.unbindUnloadGuards()
})

watch(
  () => props.idEdit,
  () => init()
)
</script>

<style scoped>
.cp {
  min-height: calc(100vh - 64px);
  background: var(--c-bg);
}
.cp__bar {
  position: sticky;
  top: 0;
  z-index: 5;
  background: var(--c-surface);
  color: var(--c-text);
}
.cp__title {
  font-size: 16px;
}
.cp__progress-caption {
  font-size: 12px;
  color: var(--c-text-muted);
}
.cp__error {
  display: flex;
  align-items: center;
  gap: 8px;
}
.cp__side-col {
  padding: 8px;
}
.cp__side {
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 120px);
  overflow: hidden;
  background: var(--c-surface);
}
.cp__tabs {
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 6px 8px;
  border-bottom: 1px solid var(--c-border);
}
.cp__tab {
  border: none;
  background: transparent;
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
  color: var(--c-text-secondary);
}
.cp__tab--active {
  background: var(--c-primary-soft);
  color: var(--c-primary);
}
.cp__panel {
  overflow-y: auto;
  padding: 8px;
  flex: 1;
}
.cp__empty {
  color: var(--c-text-muted);
  font-size: 13px;
  padding: 12px 4px;
}
.cp__results {
  list-style: none;
  margin: 0;
  padding: 0;
}
.cp__results li {
  display: flex;
  align-items: center;
  gap: 4px;
}
.cp__results-meta {
  font-size: 13px;
  color: var(--c-text-muted);
  padding: 4px 0;
}
.cp__result-btn {
  flex: 1;
  text-align: left;
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 6px 4px;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  color: var(--c-text);
}
.cp__result-btn:hover {
  background: var(--c-surface-3);
}
.cp__result-title {
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cp__result-snippet {
  font-size: 12px;
  color: var(--c-text-muted);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.cp__main-col {
  padding: 8px;
}
.cp__frame-wrap {
  background: var(--c-surface);
  overflow: visible;
}
.cp__mat-head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
}
.cp__mat-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0;
  flex: 1;
  color: var(--c-text);
}
.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.cp__iframe {
  width: 100%;
  min-height: 320px;
  border: none;
  display: block;
  background: var(--c-surface);
  /* длинные сканы: кадр вне вьюпорта не layout'ится до появления */
  content-visibility: auto;
}
.cp__pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  gap: 8px;
}
.cp__pager-pos {
  font-size: 13px;
  color: var(--c-text-muted);
  font-variant-numeric: tabular-nums;
}
.cp__fab {
  z-index: 10;
}
/* Полноэкранный режим чтения: скрываем шапку приложения, кадр занимает экран. */
.cp--fullscreen .cp__bar {
  display: none;
}
.cp--fullscreen {
  min-height: 100vh;
  background: var(--c-bg);
}
.cp--fullscreen .cp__side {
  max-height: calc(100vh - 60px);
}
@media (max-width: 959px) {
  .cp__side-col {
    position: fixed;
    inset: 56px 0 0 0;
    z-index: 8;
    padding: 8px;
    background: rgba(0, 0, 0, 0.35);
  }
}
</style>

<!--
  CoursePlayer — страница плеера v2 (S1, blueprint §2.2). Тонкий роут-компонент:
  layout + композиция useCoursePlayer (дерево) → useLessonLoader (урок) →
  LessonWorkspace (рендер). Никакой собственной загрузочной логики.

  URL-as-truth: выбор урока живёт только в query (?item=). Порядок:
    клик по дереву → router.push({query:{item}}) → watch(route) → loader.load().
  Параллельных копий «текущий урок» нет; проигравшие гонки ответы отбраковывает
  loader (loadToken/AbortController), DOM старого рендера уничтожает :key="epoch".

  Маршрут: /courses/:id/play (name courses.play) — за feature-flag
  ui.course_player_v2 (R3); legacy /courses/itemmani не трогает этот файл.
-->
<template>
  <div class="cp2">
    <header class="cp2__bar d-flex align-center gap-2 pa-2">
      <v-btn icon="mdi-arrow-left" variant="text" :aria-label="$t('courseManifest.backToCourses')" @click="goBack" />
      <div class="cp2__course text-truncate font-weight-medium">{{ playerState.course?.title || '' }}</div>
      <v-spacer />
      <v-progress-circular v-if="playerState.loading" indeterminate size="20" width="2" />
      <span v-else-if="stats.percent != null" class="text-caption">{{ $t('courseManifest.progressAria', { percent: stats.percent }) }}</span>
    </header>

    <v-alert v-if="playerState.error" type="error" variant="tonal" class="ma-3">
      {{ $t(playerState.error) }}
      <v-btn size="small" variant="text" prepend-icon="mdi-refresh" @click="init">{{ $t('courseManifest.retry') }}</v-btn>
    </v-alert>

    <v-row no-gutters class="cp2__body">
      <v-col cols="12" md="4" lg="3" class="cp2__side pa-2">
        <CourseTree
          :nodes="playerState.nodes"
          :active-id="currentItemId"
          :label="$t('courseManifest.tree')"
          @select="onSelect"
        />
      </v-col>
      <v-col cols="12" md="8" lg="9" class="cp2__main pa-3">
        <LessonWorkspace
          :course-id="courseIdNum"
          :lesson="loader.lesson.value"
          :status="loader.status.value"
          :error-key="loader.errorKey.value"
          :epoch="loader.epoch.value"
          :material-ids="materialIds"
          :current-item-id="currentItemId"
          @retry="loader.retry()"
          @select="onSelect"
        />
      </v-col>
    </v-row>
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursePlayer } from '../composables/useCoursePlayer'
import { useLessonProgress } from '../composables/useLessonProgress'
import { useLessonLoader, createDefaultLessonApi } from '../composables/useLessonLoader'
import CourseTree from '../components/course/CourseTree.vue'
import LessonWorkspace from '../components/player/LessonWorkspace.vue'

export default {
  name: 'CoursePlayer',
  components: { CourseTree, LessonWorkspace },
  setup() {
    const route = useRoute()
    const router = useRouter()

    const courseIdNum = computed(() => Number(route.params.id))
    const player = useCoursePlayer(courseIdNum)
    const progress = useLessonProgress(courseIdNum)

    // Loader живёт на странице: его scope умирает вместе с маршрутом.
    const api = createDefaultLessonApi({ treeSource: player })
    const loader = useLessonLoader({ courseId: courseIdNum, api })

    const currentItemId = ref(null)

    function itemFromRoute() {
      const q = Number(route.query.item)
      return Number.isFinite(q) && q > 0 ? q : null
    }

    async function init() {
      await player.load()
      // resume-point сервера (Д6) + локальная копия; если в URL есть ?item= — он главнее.
      // Сигнатура resolveStartItem(serverResume, validIds) — см. useLessonProgress.js:135.
      const validIds = new Set(player.materialIds.value.map(Number))
      const resolved = progress.resolveStartItem(player.state.resume, validIds)
      const start = itemFromRoute()
        || resolved?.itemId
        || player.state.entry?.item_id
        || null
      if (start != null) {
        if (itemFromRoute()) {
          await openItem(start, route.query.anchor ? String(route.query.anchor) : null)
        } else {
          // syncUrl сам не триггерит watch (в watch стоит guard id !== currentItemId),
          // поэтому загрузку вызываем здесь явно.
          await syncUrl(Number(start), resolved?.anchor ?? null)
          await openItem(Number(start), resolved?.anchor ?? null)
        }
      } else {
        loader.status.value = 'idle'
      }
    }

    async function syncUrl(itemId, anchor = null) {
      if (Number(route.query.item) === Number(itemId)) {
        await openItem(itemId, anchor)
        return
      }
      const query = { ...route.query, item: String(itemId) }
      if (anchor) query.anchor = String(anchor)
      else delete query.anchor
      // watch(route) вызовет openItem — единственный путь загрузки урока.
      await router.push({ name: 'courses.play', params: { id: courseIdNum.value }, query })
    }

    async function openItem(itemId, anchor = null) {
      if (itemId == null) return
      currentItemId.value = Number(itemId)
      progress.openLesson(Number(itemId), anchor)
      await loader.load(Number(itemId), { highlight: anchor || '' })
      player.markVisited(Number(itemId), true)
    }

    function onSelect(nodeOrId) {
      const id = typeof nodeOrId === 'object' && nodeOrId !== null ? nodeOrId.id : nodeOrId
      if (id == null) return
      syncUrl(Number(id))
    }

    // Единственный триггер загрузки урока: смена ?item= в URL.
    watch(
      () => [route.name, route.params.id, route.query.item],
      ([name, , item]) => {
        if (name !== 'courses.play') return
        const id = Number(item)
        if (Number.isFinite(id) && id > 0 && id !== currentItemId.value) {
          openItem(id, route.query.anchor ? String(route.query.anchor) : null)
        }
      },
      { immediate: false },
    )

    watch(courseIdNum, (v, old) => { if (old !== undefined && v !== old) init() })

    return {
      route,
      router,
      courseIdNum,
      playerState: player.state,
      materialIds: player.materialIds,
      stats: computed(() => player.state.stats),
      loader,
      currentItemId,
      init,
      onSelect,
      goBack: () => router.back(),
    }
  },
  async mounted() {
    await this.init()
  },
}
</script>

<style scoped>
.cp2 { display: flex; flex-direction: column; height: calc(100vh - 64px); }
.cp2__bar { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.cp2__body { flex: 1; min-height: 0; overflow: hidden; }
.cp2__side { overflow-y: auto; border-right: 1px solid rgba(0, 0, 0, 0.06); height: 100%; }
.cp2__main { overflow-y: auto; height: 100%; }
</style>

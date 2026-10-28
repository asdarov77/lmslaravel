/**
 * useCoursePlayer — загрузка и нормализация данных плеера (player-endpoint).
 *
 * Закрывает дефекты:
 *  Д2 — единый источник entry/resume/tree: всё приходит из /api/course/{id}/player,
 *       фронт больше не читает несуществующий ключ signed_base;
 *  Д3/Д4 — дерево и признак материала (is_material) считаются на сервере
 *       (CoursePlayerService), здесь только индексация для O(1)-доступа.
 */
import { computed, reactive, unref } from 'vue'
import { fetchPlayerPayload } from '../api/player.api'

export function useCoursePlayer(courseId) {
  // Принимает number|string либо Ref — на странице используется courseIdRef,
  // в юнит-тестах — ref(7). Без unref id попадал в URL как [object Object].
  const courseIdValue = computed(() => (unref(courseId) ?? null))

  const state = reactive({
    loading: false,
    error: null,
    course: null,      // { id, title, path, aircraft_path }
    nodes: [],         // плоский список узлов (source of truth)
    tree: [],          // готовое дерево с children
    entry: null,       // { item_id, title } — первый материал
    resume: null,      // { last_item_id, last_anchor, percent } — серверная точка возврата
    stats: { materials_total: 0, materials_visited: 0, percent: 0 },
  })

  const nodeById = computed(() => new Map(state.nodes.map((n) => [n.id, n])))
  const materials = computed(() => state.nodes.filter((n) => n.is_material))
  const materialIds = computed(() => materials.value.map((n) => n.id))

  async function load() {
    state.loading = true
    state.error = null
    try {
      // fetchPlayerPayload уже разворачивает конверт ApiResponseEnvelope
      const payload = await fetchPlayerPayload(courseIdValue.value)
      state.course = payload?.course ?? null
      state.nodes = payload?.nodes || []
      state.tree = payload?.tree || []
      state.entry = payload?.entry || null
      state.resume = payload?.resume || null
      state.stats = payload?.stats || state.stats
    } catch (e) {
      // Ключи i18n вместо хардкода: страница переводит их через $t().
      state.error = e?.response?.status === 401 ? 'courseManifest.loginRequired' : 'courseManifest.loadError'
      throw e
    } finally {
      state.loading = false
    }
  }

  /** Обновить локально отметку visited после события /view (без полного reload). */
  function markVisited(itemId, visited = true) {
    const n = nodeById.value.get(itemId)
    if (n) n.visited = visited
  }

  return {
    state,
    nodeById,
    materials,
    materialIds,
    isReady: computed(() => !state.loading && state.nodes.length > 0),
    load,
    markVisited,
  }
}

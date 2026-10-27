/**
 * useCoursePlayer — загрузка и нормализация данных плеера (player-endpoint).
 *
 * Закрывает дефекты:
 *  Д2 — единый источник entry/resume/tree: всё приходит из /api/course/{id}/player,
 *       фронт больше не читает несуществующий ключ signed_base;
 *  Д3/Д4 — дерево и признак материала (is_material) считаются на сервере
 *       (CoursePlayerService), здесь только индексация для O(1)-доступа.
 */
import { computed, reactive } from 'vue'
import { fetchPlayerPayload } from '../api/player.api'

export function useCoursePlayer(courseId) {
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
      const { data } = await fetchPlayerPayload(courseId)
      state.course = data.course
      state.nodes = data.nodes || []
      state.tree = data.tree || []
      state.entry = data.entry || null
      state.resume = data.resume || null
      state.stats = data.stats || state.stats
    } catch (e) {
      state.error = e?.response?.status === 401 ? 'Требуется вход в систему' : 'Не удалось загрузить курс'
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

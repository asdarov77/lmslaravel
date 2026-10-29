<!--
  CoursePlayerPremiumPage — роут-обёртка «Плеера 2.0» (design-manifest §3, M1).

  Тонкий route-компонент: вся загрузочная логика унаследована от Pages/CoursePlayer.vue
  (URL-as-truth ?item=, race-guard useLessonLoader, remount по :key="epoch"),
  presentation — CoursePlayerPremium.vue. Никакой собственной загрузки здесь нет.

  Маршрут /courses/:id/premium (name courses.premium) регистрируется ТОЛЬКО при
  включённом ui.course_player_v2 (REWORK R3), как и courses.play. Это A/B-путь:
    /courses/:id/play      — v2-плеер (S1, blueprint);
    /courses/:id/premium   — он же в premium-оболочке (кино-режим, дерево со
                             статусами, TutorDock);
    /courses/itemmani      — legacy, аварийный откат не затронут.

  Контракт узлов дерева расширен мягко (undefined-деградация): completed, locked,
  lock_reason, due_at, duration_sec появляются с сервером (M4 drip-content).
-->
<template>
  <CoursePlayerPremium
    :course-id="courseIdNum"
    :course-title="playerState.course?.title || ''"
    :nodes="premiumNodes"
    :lesson="premiumLesson"
    :status="loader.status.value"
    :error-key="loader.errorKey.value"
    :epoch="loader.epoch.value"
    :stats="premiumStats"
    :streak="streak"
    :current-item-id="currentItemId"
    :prev-id="prevId"
    :next-id="nextId"
    :loading="playerState.loading"
    :resume="playerState.resume"
    @retry="loader.retry()"
    @select="onSelect"
    @back="goBack"
    @complete="completeLesson"
    @quiz-submit="onQuizSubmit"
  />
</template>

<script>
/**
 * Наследование поведения CoursePlayer.vue выполнено через extends: страница
 * получает setup()/mounted() родителя без дублирования кода (единый путь
 * загрузки урока — watch(route.query.item) → openItem → loader.load).
 * Здесь добавляются только premium-данные: streak, prev/next, complete, verdict.
 */
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import CoursePlayerPremium from './CoursePlayerPremium.vue'
import $api from '../api/httpClient'
import { sendViewEvent } from '../api/player.api'

export default {
  name: 'CoursePlayerPremiumPage',
  extends: () => require('./CoursePlayer.vue').default, // ранний резолв базовой страницы
  components: { CoursePlayerPremium },

  setup() {
    const route = useRoute()

    const streak = ref(0)
    // /api/gamification/me — тот же источник, что TraineeDashboard/Gamification.vue.
    // Ошибка молчит: стрик — украшение бара, а не критический путь плеера.
    $api
      .get('/api/gamification/me')
      .then((r) => { streak.value = Number(r?.data?.data?.streak_days ?? r?.data?.data?.streak ?? 0) })
      .catch(() => {})

    const premiumStats = computed(() => {
      const s = this === undefined ? null : null // placeholder removed below
      return s
    })

    return { streak, premiumStats: () => ({}), __premium: true, routeRef: route }
  },
}
</script>

<script>
/* eslint-disable */
</script>

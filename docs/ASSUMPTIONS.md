## Допущения

- Роли/права: используем существующие модели `Role`, `Permission` и трейты для RBAC
- Авторизация: Laravel Sanctum токены, `Authorization: Bearer <token>`
- Версионирование API: префикс `/api/v1` добавляется без изменения бизнес-логики
- Поля моделей и связи берутся из текущих миграций; недостающие FK/индексы будут добавлены
- Frontend использует конверт ответа `{ success, data, error, meta }` (формирует
  middleware `ApiResponseEnvelope`; на клиенте разворачивается хелперами
  `resources/js/api/envelope.js`)
- Репозиторий должен поддерживать PostgreSQL 13+
- Управление состоянием — Vuex 4 (Pinia в проекте отсутствует); модули и их фактическое
  состояние — `docs/vuex.md`
- Плеер курсов v2: компонентный слой (`LessonWorkspace.vue`, `Pages/CoursePlayer.vue`)
  не создан; из каркаса S1 существует только `composables/useLessonLoader.js`
  (сверено с кодом 2026-10-28) — рабочий путь прода остаётся `CourseManifestNew.vue`
- Feature-flag `ui.course_player_v2` в конфигах нет — он планируется, а не действует



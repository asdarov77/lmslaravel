## ТЗ LMS (черновик, machine-readable)

Версия: 0.1.0

Общее
- Backend: Laravel 9+, Sanctum, PostgreSQL
- Frontend: Vue 3 + Vuetify 3, Vite
- Архитектура API: REST, JSON, v1 префикс

Модули
- Курсы, Занятия, Пользователи, Группы/Зачисления, Отчеты, Админ

UI Макеты (подробные таблицы и формы см. в docs/ui-design.md)
- Таблицы: явные колонки, типы, сортировка/фильтры, действия, пустые/ошибки/загрузка
- Формы: поля, правила валидации, состояния, подтверждения действий

Vuex Store (см. docs/vuex.md)
- Модули по доменам, кэширование списков, нормализация, оптимистичные апдейты

Плеер курсов (v2, см. docs/course-player-blueprint.md)
- Целевой контракт: типы уроков contentType (video|article|pdf|file|quiz|html), серверный
  LessonAccessResolver (linear/drip/prereq — решение всегда на бэке), событийный прогресс
  batch-endpoint с idempotency_key, offline-очередь IndexedDB + sendBeacon.
- Статус (сверено с кодом 2026-10-28): из S1-каркаса в репозитории ТОЛЬКО
  composables/useLessonLoader.js (race-guard: loadToken + AbortController + docCache +
  signed-TTL + prefetch; тестов на него нет). Компонентов components/player/LessonWorkspace.vue
  и Pages/CoursePlayer.vue в коде НЕ существует — blueprint описывает их как целевые,
  ранее «реализовано» было завышенной отметкой. Штатный рабочий путь —
  Pages/CourseManifestNew.vue (маршрут /courses/itemmani); переключения маршрута на v2 не было.

API (см. docs/openapi.yaml)
- Единый конверт ответа: { success, data, error, meta }
- Пагинация: meta.pagination { page, perPage, total, totalPages }
- Аутентификация: Bearer токен (Sanctum), роли/права

База данных
- Внешние ключи и индексы на FK и поля фильтрации
- Сидеры: админ + демо-данные (пользователи, курсы)

Тестирование
- PHPUnit для критичных потоков (авторизация, курсы CRUD, зачисления)
- Vue Test Utils/Jest для модулей хранилища и ключевых компонентов

Развертывание
- Инструкции в README: env, миграции, сиды, сборка фронтенда, запуск

Ограничения и допущения
- Детали, не найденные в коде/ТЗ, зафиксированы в docs/ASSUMPTIONS.md



# Правила для автономных агентов (LMS: Laravel 9 + Vue 3 + Vuetify 3 + PostgreSQL)

Документ отвечает на вопрос «как безопасно менять этот репозиторий».
Карта проекта — `docs/architecture.md`; статусы планов — в соответствующих доках (см. §6).

## 1. Команды проверки (запускать перед коммитом)

| Что | Команда |
|---|---|
| Backend-тесты | `php artisan test` |
| Frontend unit (vitest) | `npm test` |
| Сборка фронта (включает `check:sfc`) | `npm run build` |
| E2E (Playwright) | `npm run test:e2e` |
| Проверка раздачи через nginx | `npm run verify:nginx` |
| i18n-разметка | `python3 scripts/i18n_check.py` |
| Дизайн-пилюли (конвенции UI) | `tools/lint-design-pillars.sh` |
| Достижимость кода от точки входа | `python3 tools/reachability.py` |

Удаление файлов возможно только после трёх проверок достижимости
(методика зафиксирована в `docs/cleanup-manifest.md`) и прохождения всего набора выше.

## 2. Инварианты, нарушать которые нельзя

1. **Приватный контент физически вне `public/`.** Материалы курсов лежат в
   `storage/app/courses/private`; никогда не выполнять `php artisan storage:link`
   и не добавлять symlink на этот путь — иначе обход подписи гарантирован.
   После любых изменений путей — `php artisan content:check-exposure` (падает в CI,
   если прямой доступ открыт).
2. **Решения о доступе — в одном месте.** Видимость курсов — `CourseVisibility`,
   открытие материала — `CourseAccess`, права — `HasRolesAndPermissions` +
   `PermissionScope`. Контроллер, решающий «виден ли курс» самостоятельно, — баг.
3. **Подписанный URL — bearer-credential.** TTL ≤ 15 мин, binding к `user_id`
   в сигнатуре (`PrivateContentSigner`), повторная проверка доступа на эндпоинте
   выдачи подписи. Подробности — `docs/content-delivery.md`, `docs/rbac.md`.
4. **Конверт API `{ success, data, error, meta }`** формирует `ApiResponseEnvelope`;
   во фронтенде разворачивать только через хелперы `resources/js/api/envelope.js`
   (`unwrapResponse`, `unwrapArray`, `extractFieldErrors`) — ручной доступ
   `response.data.data` вне этих функций не добавлять.
5. **Роли из двух источников намеренно** (`users.role` и pivot `role_user`);
   синхронизация — только через существующий механизм (`chroll`), не править локально.
6. **Порядок маршрутов в `routes/api.php` значим**: статические сегменты раньше
   параметризованных; версионирование — вложенная группа `prefix('v1')`.

## 3. Фронтенд-конвенции

- Состояние: Vuex 4 (`resources/js/Store/modules/*`). Pinia в проекте нет.
- HTTP-запросы — только через модули `resources/js/api/*.js` (axios `$api`);
  прямые axios/fetch-вызовы из компонентов запрещены (исключение — iframe-кадры
  с токеном в query, см. `player.api.js`).
- Тосты — только `useToast`; диалоги — только `ConfirmDialog`/`AppDialog`.
- Тексты интерфейса — через i18n-ключи, хардкод строк в компонентах запрещён
  (`scripts/i18n_check.py`).
- Асинхронные загрузки в composables обязаны иметь отмену (AbortController +
  монотонный loadToken) — образец: `resources/js/composables/useLessonLoader.js`.

## 4. Чего не трогать без задачи

- `merged_all.txt` — снимок первоначальной версии репозитория, эталон для
  сравнений (`docs/course-player-performance.md`). Не редактировать, не удалять.
- Legacy-плеер `Pages/CourseManifestNew.vue` — штатный рабочий путь прода
  до переключения маршрута на v2; ломать его нельзя ни при каких правках v2.
- `docs/screenshots-baseline/` — визуальный baseline Playwright; обновлять
  только осознанно (`tools/screenshot-baseline.mjs`).

## 5. Стиль коммитов и веток

- Одна логическая задача — один PR; ветки вида `<topic>-<hash>` (см. историю git).
- В описании PR указывать, какие проверки из §1 прогнаны.
- Правки документации статуса («что сделано») обязательны вместе с кодом:
  см. §6 — не оставлять в доках утверждений, противоречащих коду.

## 6. Где лежит актуальный статус планов

| План | Документ-источник истины |
|---|---|
| Плеер v2 (S1–S4) | `docs/course-player-blueprint.md` (контракт + таблица спринтов) |
| Производительность (P0/P1) | `docs/course-player-performance.md`, раздел «Статус плана» |
| UI-редизайн (фазы) | `docs/design-redesign-plan.md` |
| Удаления файлов | `docs/cleanup-manifest.md` |

При противоречии между доками приоритет у строки «сверено с кодом <дата>».

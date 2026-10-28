# Реестр переработки (REWORK)

> Дата сверки с кодом: **2026-10-28**. Документ — рабочий список расхождений
> между доками и фактическим состоянием репозитория, каждый пункт подтверждён
> grep/чтением кода. Порядок переработки — по номерам задач; закрытые задачи
> помечаются `[x]` со ссылкой на коммит/PR, текст не удаляется (аудит-след).
> Правила безопасных правок — `docs/AGENTS.md`; статусы планов — §6 AGENTS.

## A. Подтверждённые незакрытые задачи (что делать «по порядку»)

### R1. S1-остаток плеера v2: тесты race-guard загрузчика — **СДЕЛАНО (2026-10-28)**
- **Где:** `resources/js/composables/useLessonLoader.js` (208 стр., существует).
- **Факт до правки:** ни одного теста на loader не было; критерий приёмки S1
  из blueprint («Vitest: гонка — 5 кликов подряд → рендер последнего») не выполнен.
- **Сделано:** `tests/Unit/composables.lesson-loader.spec.js` (13 тестов):
  отбраковка проигравших ответов (loadToken: 5 кликов → готов только последний),
  физическая отмена предыдущего запроса (AbortController), кэш документа
  (повтор без сети, force — с сетью), locked без загрузки контента, TTL подписи
  (не перевыпускается до exp−60с, перевыпускается после), 403→locked / 401→error,
  prefetch соседа через 1.5с и его отмена при смене урока, смерть контроллера
  и таймера в `onScopeDispose`, retry с force.
- **Acceptance:** `npm test` зелёные; строка S1 в blueprint §5 обновлена.

### R2. S1-остаток плеера v2: компоненты LessonWorkspace + CoursePlayer
- **Факт:** `resources/js/components/player/LessonWorkspace.vue` и
  `resources/js/Pages/CoursePlayer.vue` в репозитории **отсутствуют**
  (проверено listing'ом `Pages/` и `components/`). Маршрут рабочего плеера —
  `/courses/itemmani` → `Pages/CourseManifestNew.vue`
  (`resources/js/Router/routes.js:206–217`). Blueprint §4 фиксирует это честно.
- **Сделать:** создать два компонента поверх готового `useLessonLoader`
  (композиция — blueprint §2.2), URL-as-truth (`?item=`), remount-key `epoch`,
  затем переключение маршрута за feature-flag (см. R3). Legacy-путь
  `CourseManifestNew.vue` не ломать (AGENTS §4).
- **Acceptance:** TTI ≤ 1.2с; e2e `player-race.spec.js` (быстрая смена уроков —
  без «залипаний»); юнит-тесты из R1 зелёные.

### R3. Feature-flag `ui.course_player_v2`
- **Факт:** флага нет ни в `config/`, ни в `app/`, ни в `resources/js`
  (grep пустой). В `docs/ASSUMPTIONS.md` и blueprint §4 это зафиксировано
  как «планируется, а не действует».
- **Сделать:** внедрять вместе с первыми v2-компонентами (R2): config-ключ
  + точка чтения в роутере; аварийный откат — как с legacy CourseManifest.

### R4. Merge двух player-endpoint'ов (blueprint §1.3)
- **Факт:** живут оба: `GET /api/course/{courseId}/player`
  (`CoursePlayerController::show`, `routes/api.php:436`, основной фронт) и
  `GET /api/course-player/{courseId}` (`CourseController::player`,
  `routes/api.php:465`, самоневалидирующийся кэш `player:tree:{id}:{max(updated_at)}`).
- **Сделать (S1-остаток):** перенести self-validating-ключ в
  `CoursePlayerService::treeCacheKey`, оставить один маршрут, второй — alias
  на 1 релиз с deprecated-заголовком. Тесты-контракты: `CoursePlayerEndpointTest`,
  `CoursePlayerApiTest`, `CourseContentAccessTest` — расширить, не удалять.

### R5. Целевые контракты API v2 (S2–S4) — не начаты
- `GET /api/lesson/{id}`, `POST /api/course/{id}/events`,
  `POST /api/lesson/{id}/complete`, `PUT /api/aukstructure/{id}/rules` —
  в `routes/api.php` отсутствуют; `createDefaultLessonApi().fetchSignedDelivery`
  возвращает 501 (ожидаемо до S2). Миграций `lessons.content_type`,
  `course_lesson_rules`, `lesson_notes` нет. Полная таблица — blueprint §5.

### R6. Открытый вопрос по legacy CourseManifest.vue
- **Факт:** комментарий в `routes.js:210` («Legacy-версия CourseManifest.vue
  сохранена как аварийный откат») и ссылка blueprint §1.3 на
  `CourseManifest.loadPlayerData()` — но файла `Pages/CourseManifest.vue`
  в репозитории **нет** (видимо, удалён; в `docs/cleanup-manifest.md` записи
  об этом удалении тоже нет).
- **Сделать:** либо восстановить запись в cleanup-manifest (почему удалён,
  чем подтверждена недостижимость), либо убрать устаревшие ссылки из
  комментариев/доков. До выяснения считать утверждение «аварийный откат
  доступен» НЕдействительным.

### R7. Дизайн-план: хвост Фаз 2–5
- Подтверждено незакрытым (ревизия 2026-10-22 в `design-redesign-plan.md`):
  auth-экраны частично legacy, конструктор курса без DnD-шага «Структура»
  (vuedraggable в зависимостях отсутствует), InlineQuiz/TutorDock SSE — нет;
  backend-blocked список — в `docs/superpowers/specs/2026-10-16-lms-ui-redesign.md`.

## B. Исправления самих документов (выполнены в этой сессии)

| # | Файл | Было | Стало |
|---|---|---|---|
| D1 | `docs/AGENTS.md`, `docs/TECH_SPEC.md`, `README.md` | «Laravel 9 / Laravel 9+» | Laravel 11 — по `composer.json` (`laravel/framework ^11.0`); `TESTING-ARCHITECTURE.md` уже говорил правду |
| D2 | `docs/course-player-blueprint.md` §1.3 | «двойной `fetchAircrafts` в CourseManifest удалён… CourseManifest ходит одним запросом» — ссылался на несуществующий файл | переформулировано: исторический факт про legacy-файл + актуальная ссылка на `CourseManifestNew.vue`; добавлена задача R6 |
| D3 | `docs/AGENTS.md` §6 | реестр незакрытых расхождений отсутствовал | добавлена ссылка на этот документ |

## C. Проверочный лист для закрытия любой задачи R*

1. `php artisan test` (backend) и `npm test` (Vitest) — зелёные;
2. `npm run build` (включает `check:sfc`);
3. затрагивали пути контента? — `php artisan content:check-exposure`;
4. e2e-сценарий плеера (`tests/E2E/course-material.spec.js`, новый
   `player-race.spec.js` после R2);
5. обновить статусную таблицу blueprint §5 и пометку `[x]` здесь.

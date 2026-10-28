# Плеер курсов 2.0: Техническое руководство и UX-блюпринт

> Статус: **план переработки (v1.0, 2026-10-28)**. Документ — целевой контракт нового плеера.
> Диагноз производительности и уже выполненные P0/P1-оптимизации — в `docs/course-player-performance.md`
> (раздел «Статус плана»). UI-конвенции — `docs/ui-design.md`, продуктовый контекст —
> `docs/design-redesign-plan.md` (Фазы 3.3–3.4).

---

## 0. Что уже есть и почему этого мало

Фактическое состояние (сверено с кодом 2026-10-28):

| Актив | Файл | Состояние |
|---|---|---|
| Единый endpoint старта | `routes/api.php:436` → `CoursePlayerController::show`, `app/Services/CoursePlayerService.php` | ✅ работает: курс + дерево + resume + entry одним запросом (`GET /api/course/{id}/player`), серверный кэш дерева с явной инвалидацией |
| Клиентский LRU-кэш документов | `resources/js/utils/docCache.js` (15 доков, ключ `course:item`) | ✅ есть, но не реактивен и не знает TTL подписей |
| Прогресс урока | `composables/useLessonProgress.js` (`lesson_progress`, resume-позиция на сервере) | ✅ debounce-отправка есть; offline-очереди нет |
| Контент-кадр | `composables/useContentFrame.js` + srcdoc-iframe в `Pages/CourseManifestNew.vue` (754 стр.) | ⚠️ только HTML-материалы; iframe-подход наследует проблемы ниже |
| Дерево курса | `components/ui/CoursePlayerLayout.vue` (748 стр.), TreeView/TreeViewNode | ⚠️ без статусов «заблокировано», без иконок типов контента, без виртуализации >300 узлов |

Ключевой дефицит: **плеер умеет показывать только один тип контента** — HTML-документ модуля
(`aukstructures.type=3`). Видео, PDF, тесты, файлы-раздатки существуют в системе как файлы,
но не как *уроки*: у них нет модели типа, нет resume-позиции, нет правил завершения. Отсюда
«техническая устарелость»: всё сводится к iframe со srcdoc, а интерактивные форматы физически
некуда встроить.

### Дефекты текущей реализации (что чиним в первую очередь)

1. **Гонки при быстрой смене уроков.** В `CourseManifestNew.vue` последовательность
   `getlink → signed-url → fetch(html)` не отменяется: быстрый клик по дереву (или клавиатура ←/→)
   может завершиться в обратном порядке — старый контент «залипает» поверх нового. AbortController
   во фронтенд-запросы кадра не пронизан.
2. **Монолит на 754 строки** смешивает навигацию, загрузку, рендер, поиск, подсветку, избранное.
   Локальный state дублирует Pinia-стейт → рассинхрон при remount.
3. **Нет источника истины в URL**: выбраннный материал живёт в компонентном state, поэтому
   «Продолжить с последнего урока», глубинные ссылки и кнопка «Назад» браузера не работают.
4. **Подписанные URL перевыпускаются при каждой перематке/переключении** (php-режим раздачи),
   что при включённом nginx X-Accel лишним не будет, но сейчас удваивает RTT до первого кадра.
5. **Утечки слушателей**: scroll-обработчики и таймеры подсветка/автосейв частично снимаются
   только в `beforeUnmount` одного компонента; при ошибках загрузки остаются висеть.

---

## 1. Часть 1 — GAP-анализ современной LMS против текущего кода

### 1.1. Продуктовые дыры (критично)

| # | Функция |现状 | Что добавить |
|---|---|---|---|
| G1 | **Типы уроков (contentType)** | только html-материал | Миграция `lessons.content_type enum(video\|article\|pdf\|file\|quiz\|ai_widget)` + `lessons.content_ref` (polymorphic link на material/file/exam). Рендер выбирается диспетчером, см. §3. |
| G2 | **Правила прохождения (sequencing)** | нет | Таблица `course_lesson_rules`: `mode(linear\|nonlinear)`, `prerequisite_lesson_id`, `drip_type(none\|date\|after_completion\|after_score)\|drip_value`. Разрешает **серверно** `LessonAccessResolver` — клиент показывает замок, но решение всегда на бэке (§2.1). |
| G3 | **Дедлайны уровня урока** | только `courses.opens_at/closes_at` | `lessons.due_at`, просрочка в дереве (красная точка), напоминания через `Notifications` (уже есть). |
| G4 | **Завершение урока** | эвристика «открыл кадр» | Единый серверный агрегат `LessonCompletion`: video — coverage≥90% + last_position; article — scroll-depth≥85% + dwell≥N сек; quiz — attempt passed; file — факт скачивания. Античит: `coverage` считается по сегментам времени, а не max(time). |
| G5 | **Offline-прогресс** | нет | IndexedDB-очередь событий + replay при online (§2.3). LocalStorage — только легковесные позиции, не события. |
| G6 | **Заметки/выделения студента** | заметки есть, не привязаны к таймкоду | `lesson_notes(lesson_id, ts_offset, text)`; для видео — клик по заметке = seek. |
| G7 | **Поиск по курсу** | tsvector ✅ | Добавить переход «результат → урок#фрагмент» (deep-link в кадр, подсветка уже есть). |
| G8 | **Геймификация streak/XP за уроки** | модуль Gamification есть | Событие `lesson.completed` → XP; не блокировать обучение, только стимулировать. |

### 1.2. Усиление существующих модулей

**Файловый менеджер.** Чанковая загрузка и resume есть; не хватает: (а) dedup по sha256
(`media_assets` таблица — один blob, много ссылок; экономит диск и ускоряет публикацию);
(b) стандартного протокола tus.io вместо самописных чанков — дешевле сопровождение;
(c) MediaLibraryService: единая точка «файл → урок/материал» (сейчас связь файлов с курсом
выводится из путей ФС — хрупко); (d) авто-транскод видео (ffmpeg queue job) в `mp4 faststart`
+ постеры, иначе custom player на HLS требует hls.js и отдельной раздачи сегментов.

**AI-Tutor (`docs/ai-tutor.md`).** Граница «не влияет на оценки» соблюдается; усилить:
(a) SSE-стриминг ответов вместо ожидания полного генерации (сейчас батч);
(b) автоматический context-pill — фрагмент открытого урока (tsvector-окружение вокруг viewport)
подмешивается в промпт, с явной маркировкой пользователю;
(c) история диалога per-lesson с сохранением в БД (для методистов — аналитика «где студенты спрашивают»).

**RBAC (`docs/rbac.md`).** Главная связка ещё не документирована жёстко: **право × подписанный URL**.
Signed-URL — это bearer-credential: кто получил ссылку, тот имеет доступ до истечения TTL.
Требования: TTL ≤ 15 мин, binding к `user_id` в сигнатуре (уже есть в `PrivateContentSigner`),
revocation-list (Redis SET `revoke:{sign}`) при отчислении из группы, и обязательная повторная
проверка `CourseAccess::authorizeOpen` на самом эндпоинте выдачи подписи (не только на дереве) —
защита от IDOR, когда клиент сам подставляет чужой `item_id`.

### 1.3. Что переносится из текущего кода в v2 (не выбрасываем)

- **Два player-endpointa — слить в один.** Сейчас живут `CoursePlayerController::show`
  (`/api/course/{id}/player`, основной фронт `CourseManifestNew.vue`) и
  `CourseController::player` (`/api/course-player/{courseId}`, дерево с самоневалидирующимся
  кэшем `player:tree:{id}:{max(updated_at)}`). В S1 — перенести self-validating-ключ
  в `CoursePlayerService::treeCacheKey`, оставить один маршрут, второй повесить alias на 1 релиз.
- **Инвалидация**: `CoursePlayerService::invalidateTreeCache()` уже вызывается из
  `CourseController` (update/структура) — при переходе на rules-движок вызывать её же
  из `PUT /aukstructure/{id}/rules` и reorder.
- `useContentFrame` (ResizeObserver, resume-anchor), `useLessonProgress` (LS-fallback,
  view-события), `docCache`, подсветка поиска, TreeView — переиспользуются как есть;
  v2 добавляет вокруг них loader race-guard и диспетчер типов, а не переписывает их.
- Режим раздачи (`settings.content_delivery`, php|nginx) читается из `config/private_content.php` —
  ответ урока отдаёт поле `delivery.mode`, чтобы `PdfLesson/FileLesson` выбирали `<iframe>` vs
  `<a download>` без догадок.

---

## 2. Часть 2 — Архитектура нового плеера

### 2.1. Backend: контракты API v1

Существующие эндпоинты (трогать нельзя, расширять): `GET /api/course/{courseId}/player`,
`POST /api/course/{courseId}/progress`, `POST /api/course/{courseId}/view`
(`CoursePlayerController`), `GET /api/course/{courseId}/content/{itemId}` и `/raw/{path}`
(`CourseContentController`, авторизация через `?t=` из-за iframe). Целевые новые контракты —
поверх них, с сохранением обратной совместимости (старый фронт живёт до снятия флага):

```
GET  /api/course/{id}/player            # bootstrap (расширяется полями ниже)
     → { course, nodes:[{..., content_type, duration_sec, status:done|active|locked|overdue,
         lock_reason}], tree, entry, resume, stats }
GET  /api/lesson/{id}                   # detail урока (ленивый; payload по content_type)
     → { id, content_type, payload:{...}, access:{allowed,reason},
         resume:{position_sec,updated_at}, delivery:{mode:php|nginx, url?, expires_in} }
POST /api/course/{id}/events            # batch прогресс-событий (см. 2.3)
     body: { events:[{lesson_id,type,ts,payload,idempotency_key}] } → принятые/отклонённые keys
POST /api/lesson/{id}/complete          # идемпотентная отметка завершения
PUT  /api/aukstructure/{id}/rules       # правила доступа (конструктор, §3.1)
```

`LessonAccessResolver` вызывается в **трёх** местах: player-tree (пометка locked), lesson-detail
(403 + reason), выдача контента (`CourseContentController::show/raw` — обязательно; подпись/токен
в query не должны обходить правила).

### 2.2. Frontend: двухпанельная композиция

```
Pages/CoursePlayer.vue                  // тонкий роут-компонент (~80 стр.): layout + provide/inject ctx
 ├─ CourseSidebar (tree)                // v-navigation-tree c нормализованным store, статусы, иконки contentType
 │    └─ useCourseTree()                // computed flatten+virtualize (>300 узлов — vue-virtual-scroller)
 ├─ LessonWorkspace                     // :key="epoch" — полный remount на смену урока
 │    └─ ContentDispatcher (component :is)
 │         VideoLesson · ArticleLesson · PdfLesson · FileLesson · QuizLesson · AiWidgetLesson
 ├─ TutorDock                           // Part 3.3
 └─ PlayerTopBar                        // прогресс-бар курса, breadcrumb, favorite, fullscreen
```

Стейт: Pinia-модуль `coursePlayer` хранит **только** дерево/статусы/прогресс (кэш над API).
Всё преходящее (payload урока, abort-контроллер, loadToken) живёт в composable `useLessonLoader`
и умирает вместе с ним. URL (`/courses/:id/lessons/:lessonId`) — единственный источник истины
о выборе: router.push → watch route → loader.load(). Никаких параллельных копий «текущий урок».

### 2.3. Автосохранение прогресса (алгоритм)

1. Источник событий: каждый renderer эмитит `progress`-события (`tick` 1 раз/10с, `seek`,
   `visibility`, `attempt_saved`) в общий `useProgressTracker(courseId)`.
2. Буфер: массив в памяти; flush по (а) debounce 2с после последнего события, (б) 20 событий,
   (в) `visibilitychange=hidden` / `pagehide` — тогда `navigator.sendBeacon` (тот же batch-endpoint,
   content-type application/json через Blob).
3. Идемпотентность: каждый событие имеет `idempotency_key = uuid`; сервер хранит последние
   keys в Redis (`SETNX`, TTL 24ч) и молча схлопывает повторы — retry безопасен.
4. Offline: при сетевом ошибке/`navigator.onLine=false` события пишутся в IndexedDB
   (`lms-outbox`, индекс по courseId); `online`-listener или первый успешный flush инициирует
   drain (порциями по 50, экспоненциальный backoff). LocalStorage — только `resume.position`
   (частая мелочь, переживает краш вкладки).
5. Конфликт устройств: сервер принимает **max(position)**, completion — monotonic (done не
   откатывается); клиент при GET resume получает серверное значение и никогда не шлёт position
   меньше локально принятого сервером (защита от «перемотал назад — потерял прогресс»).
6. Completion считается **сервером** из агрегата событий (G4), клиент лишь подсказывает UI.

### 2.4. Nginx X-Accel при частой смене уроков и стриминге

* Подпись содержит `user_id + path + exp`; TTL 15 мин. Выдавать **на урок**, не на запрос:
  клиент кэширует signed-url в памяти урока и не перевыпускает при перематке — Range-запросы
  идут напрямую в nginx (`Accept-Ranges: bytes` обязателен; проверять заголовком на выдаче).
* Redis-кэш `signed:{lesson}:{user}` с TTL = exp−60с, чтобы не гонять HMAC+проверку доступа
  на каждый open; инвалидация — по событию отчисления (revocation-list из §1.2).
* Видео: `faststart` (moov в начале) — старт без докачки хвоста; для нагрузочных курсов —
  HLS-плейлист, где каждый сегмент тоже проходит internal location, но подписывается один
  master-m3u8 (сегменты резолвятся относительно, cookie/session-bound).
* `open_file_cache` в internal-location nginx (max=10000, inactive=60s) — частые повторы
  «назад в начало урока» не трогают диск.
* Запретить: отдачу php-fpm heavy-файлов при режиме nginx — `content:check-exposure` (есть)
  должен падать в CI, если `.conf` не применён.

---

## 3. Часть 3 — Сложные UI-элементы (Vuetify 3)

### 3.1. Конструктор курса (Wizard + DnD)
Расширение существующего 4-шагового мастера (`design-redesign-plan` 3.3, шаг живёт в `?step=`):
шаг «Структура» получает drag-and-drop (`vuedraggable@next`, ghost-класс, drop-constraints:
урок нельзя утащить между курсами; keyboard-accessible fallback ▲▼ остаётся), справа —
**Inspector панели**: выбранный узел → форма правил (drip-date picker, prerequisite select,
min-score slider) пишется сразу в `PUT /lessons/{id}/rules` с optimistic update и undo-toast.
Кнопка «Preview as student» — тот же CoursePlayer с флагом `preview_user_id` (права проверяются
от имени роли, изменения замораживаются).

### 3.2. Задания (InlineQuiz)
Schema-driven: бэкенд отдаёт `question.schema.json` (single/multiple/matching/text),
фронт рендерит item-renderer'ы; валидация — собственный `useForm` (composables/useForm.js уже есть)
+ zod-схема для matching (граф пар). Ответы копятся локально, submit — один batch POST;
**перепроверка только сервером** (клиентская «галочка верно» не является источником оценки).
Таймер и навигатор вопросов переиспользуются из ExamRunner.

### 3.3. AI-Tutor Dock
`v-bottom-sheet`-правый dock (360px, сворачивается в FAB): сообщения SSE-стримом
(`fetch + ReadableStream`, отмена при unmount), context-pill сверху («Контекст: §2.3 «…» [✕]»),
три уровня подсказки (наводка → часть решения → полный ответ) как segmented control —
уровень фиксируется в telemetry для методиста. Ограничение速率 — `useRateLimitCountdown` (есть).

---

## 4. Каркас кода (рабочие файлы)

Реализовано (S1-каркас, feature-flag `ui.course_player_v2`, старый маршрут не тронут):

| Файл | Роль |
|---|---|
| `resources/js/composables/useLessonLoader.js` | Race-guard загрузки урока: loadToken + AbortController + docCache + TTL подписанного URL + prefetch соседнего урока. Утечки нет: всё живёт в scope компонента и умирает в `onScopeDispose`. |
| `resources/js/components/player/LessonWorkspace.vue` | Диспетчер контента по `content_type` (video/article/pdf/file/quiz/html-legacy через существующий `useContentFrame`), состояния loading/error/locked/empty, `:key="epoch"` remount. |
| `resources/js/Pages/CoursePlayer.vue` | Двухпанельный layout: сворачиваемое дерево со статусами/иконками типов + рабочая область; URL (`?item=`) — источник истины выбора; prev/next; прогресс-бар из `stats`. |

Старые `Pages/CourseManifestNew.vue` и `components/ui/CoursePlayerLayout.vue` остаются штатным
путём до переключения маршрута (аварийный откат — как с legacy CourseManifest).

## 5. Спринт-план и критерии приёмки

| Спринт | Содержание | Acceptance | Статус |
|---|---|---|---|
| S1 | Loader race-guard, URL-as-truth, remount-key, тесты Vitest (гонка: 5 кликов подряд → рендер последнего) | TTI ≤1.2с; 0 «залипаний» в e2e Playwright `player-race.spec.js` | **Каркас сделан** (`useLessonLoader.js`, `LessonWorkspace.vue`, `CoursePlayer.vue`); e2e-спека — следующий шаг |
| S2 | contentType-миграция + VideoLesson (custom controls, resume, speed) + PdfLesson + FileLesson | p95 открытия урока ≤300мс при 50 readers; cache-hit без сети ≤100мс | Не начат |
| S3 | Rules-движок (linear/drip/prereq) сервер+замок в дереве + InlineQuiz | 403 на bypass-URL чужого урока — тест `LessonRulesAccessTest` | UI-заготовка замка в дереве есть; бэкенд не начат |
| S4 | Offline outbox (IndexedDB) + sendBeacon + coverage-completion; TutorDock SSE | Потеря сети 5 мин → после online 100% событий доставлены, дублей нет | Не начат |

Риски: (1) iframe-srcdoc vs нативные рендереры — решаем диспетчером, srcdoc остаётся только
для legacy-html материалов; (2) ffmpeg-транскодинг на проде — деградация: исходный mp4 без
HLS допускается, badge «качество»; (3) миграция данных `aukstructures → lessons` — двойная запись
(parallel write) на 2 недели, rollback-скрипт обязателен.

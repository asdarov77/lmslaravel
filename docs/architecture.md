# Карта проекта

Файл отвечает на вопрос «за что отвечает этот класс». Он не пересказывает код, а
объясняет **разделение ответственности** и то, что из кода не следует: где
принимаются решения о доступе, почему файл делает именно это, и какие места
нельзя менять, не сломав что-то другое.

Три правила, которые проходят через весь проект:

1. **Доступ решается в одном месте, а не в каждом контроллере.** Видимость
   курсов — `CourseVisibility`, открытие материала — `CourseAccess`, права —
   `HasRolesAndPermissions` + `PermissionScope`. Контроллеры, которые решают
   «виден ли курс» самостоятельно, — источник уязвимостей.
2. **Роль приходит из двух источников намеренно.** Колонка `users.role` и
   связь `role_user` независимы (см. «Роли и права»).
3. **Приватный контент защищён подписью в пути, а не токеном.** Иначе вложенные
   CSS/JS/картинки курса запрашивались бы без авторизации.

---

## 1. Точки входа: кто кого вызывает

```
routes/web.php ─► SinglePageController (отдаёт оболочку SPA)
                    └─ весь UI живёт в Vue (resources/js), роутер — resources/js/Router/routes.js

routes/api.php ─► middleware: auth:sanctum + permission:a,b (здесь «любое из»)
              └─ Controllers
                   ├─ Support/*  — решения о доступе и выдаче контента
                   ├─ Models/*   — данные и связи
                   ├─ Policies/* — правила «кому можно что» (Gate)
                   └─ Jobs/*     — фоновая генерация вопросов тренажёра
```

`routes/api.php` смонтирован один раз под префиксом `api`; версионирование —
вложенной группой `prefix('v1')`. Поэтому маршрут пишется как `'/v1/login'`.
**Порядок объявления в файле значим**: статические сегменты должны идти раньше
параметризованных, иначе они уйдут в параметр.

---

## 2. Роли и права

| Класс | За что отвечает |
|---|---|
| `app/Traits/HasRolesAndPermissions.php` | Единый подсчёт эффективных прав: прямые (`permissions_users`) ∪ права ролей (`permissions_roles`) ∪ legacy-алиасы, с fallback на `config('permissions.role_matrix')` **только когда явных назначений нет**. Даёт `hasPermission`, `isSuperAdmin`, выдачу/снятие прав. |
| `app/Models/User.php` | Пользователи. Собирает `fio` из ФИО в хуке, нормализует роль из двух источников, отдаёт `isAdmin/isInstructor/isTrainee/isSuperAdmin`. |
| `app/Models/Role.php` | Роли. Права берутся из pivot `permissions_roles`, а не из одноимённой колонки (она удалена, чтобы связь не перебивалась). |
| `app/Models/Permission.php` | Права. В `saving` выводит slug из имени, если он не задан явно. |
| `app/Support/PermissionCatalog.php` | Каталог прав из `config/permissions.php`: маппинг legacy-алиасов и список защищённых slug. |
| `app/Support/PermissionScope.php` | Кому какие права можно выдавать: администратор — всё, инструктор — только свои (минус `users.permissions`, `system.maintenance`) и только в своей группе. |
| `app/Http/Middleware/CheckUserPermission.php` | Алиас `permission` — «достаточно **одного** из перечисленных прав». Берёт `$request->user()`, а не `Auth::user()`. |
| `app/Http/Middleware/CheckAllPermissions.php` | Алиас `permission.all` — требуются **все** права. |
| `app/Providers/AuthServiceProvider.php` | `Gate::before`: возвращает `true` только суперадмину, иначе `null`, чтобы не перебивать остальные политики. |
| `app/Console/Commands/SyncPermissions.php` | `permissions:sync` — синхронизирует каталог из конфига с таблицами `permissions`/`permissions_roles`. |

**Ловушки, из-за которых ломается раздача прав:**

- `users.role` и `role_user` — два независимых источника. `chroll` синхронизирует
  только `role_user`, поэтому пользователь с ролью из UI считался «без роли» и не
  получал базовых прав матрицы.
- Роль при регистрации назначается **по правам актора**, а не по телу запроса:
  без `users.create` роль принудительно «Обучаемый», а `group_id` остаётся `null`.
- Право «есть у админа» проверяется тремя способами (`isAdmin`, `isSuperAdmin`,
  `Gate::before`); расхождение любых двух даёт 500 на логине или 403 там, где
  ожидался проход.

---

## 3. Курсы, специальности, группы

| Класс | За что отвечает |
|---|---|
| `app/Models/Course.php` | Курс. `belongsTo` категория/самолёт, `hasMany` модули и учебные записи, `manyToMany` инструкторы и студенты. Алиасы `name`/`description` для API v1. |
| `app/Models/Category.php` | Специальность. Полностью `$guarded=[]`. |
| `app/Models/Aircraft.php` | Тип ВС. Поле `path` задаёт каталог контента `private/<путь>`. |
| `app/Models/Aukstructure.php` | Модуль курса с самоссылкой `parent/children`. |
| `app/Models/Group.php` | Учебная группа — точка привязки всех проверок области видимости. |
| `app/Models/Group2learning.php` | Запись «группа записана на курс **в рамках своей специальности**» (`group_id, course_id, category_id, parent_id`). Единственный источник прав на курсы и материалы. |
| `app/Support/CourseVisibility.php` | **Видимость** в списках: обучаемый видит курсы своей группы, управляющие курсами — всё, без группы — ничего. |
| `app/Support/CourseAccess.php` | **Открытие материала**: записан через свою группу либо управляет курсами. Завершается `abort(403)`. |
| `app/Http/Controllers/CatalogController.php` | Витрина курсов и самостоятельная запись/отписка своей группы. `CourseVisibility` здесь намеренно **не** применяется — иначе записаться было бы не на что. |
| `app/Http/Controllers/CoursesListController.php` | «Учебный план»: список курсов через `CourseVisibility`. |
| `app/Http/Controllers/CourseController.php` | CRUD курсов, манифест, открытие материала через `CourseAccess`. |
| `app/Http/Controllers/AircraftController.php` | Списки папок-классов и импорт самолёта: `Aircraft`, `Course`, `Aukstructure`, `Link`, `Category` и pivot-таблицы строятся из `imsmanifest.xml` **в одной транзакции**. |
| `app/Http/Controllers/FileLoadAndExtractController.php` | Загрузка zip курса и распаковка в `storage/app/public/private`. |
| `app/Http/Controllers/Group2learningController.php` | CRUD учебных записей, область — `Group2learningPolicy`. |

**Почему запись несёт `category_id`.** Один курс бывает привязан к нескольким
специальностям. Проверка «есть запись на курс» дала бы лётчику материалы
радиста. Поэтому единица права — пара `(course_id, category_id)`.

---

## 4. Приватный контент: подпись и выдача

Это самый тонкий узел проекта. Здесь байты файла — единственное место, где они
целиком проходят через PHP.

| Класс | За что отвечает |
|---|---|
| `app/Support/PrivateContentSigner.php` | Подписанные URL: HMAC-SHA256 от `aircraft|auk|expires` на `APP_KEY`, TTL 30 минут. Подпись встраивается **в путь**, чтобы наследовалась вложенными ресурсами. |
| `app/Http/Middleware/ValidatePrivateContentSignature.php` | Проверка подписи; 403 на подделке и по истечении срока. Маршрут намеренно без `auth:sanctum`: вложенные ресурсы идут без заголовка Authorization. |
| `app/Support/PrivateContent.php` | Безопасная работа с путями: санитизация сегментов (запрет `..`, разделителей, NUL, закодированных разделителей) и сверка `realpath` с корнем контента. |
| `app/Http/Controllers/PrivateController.php` | Выдача: полный ответ для `index.html`, поток для вложенных файлов, либо `X-Accel-Redirect`. Здесь же выдача подписанного префикса (`signedUrl`). |
| `app/Support/ContentDelivery.php` | Режим выдачи: `php` или `nginx`. Читает `settings.content_delivery` с TTL 5 секунд. **X-Accel включается только если запрос помечен заголовком из `APP_KEY`** — иначе при `php artisan serve` Symfony отдал бы пустое тело со статусом 200. |
| `app/Console/Commands/ContentNginxConfigCommand.php` | `content:nginx-config` — печатает готовый фрагмент nginx (внутренний путь, корень хранилища, метка) из тех же источников, что и приложение. |
| `app/Console/Commands/RelocateContentCommand.php` | `content:relocate` — перенос каталога материала за пределы `public/`. Считает файлы и байты до и после, отказывается переносить внутрь `public/` или `storage/app/public`, умеет `--dry-run`. |
| `app/Console/Commands/CheckPrivateContentExposure.php` | `content:check-exposure` — проверяет, что контент не отдаётся веб-сервером напрямую. |

Три независимых слоя защиты: подпись → `realpath` → `internal`-location nginx.

**Про nginx.** Защита не ослабевает: единственный вход — Laravel, `internal`
делает location недоступным снаружи (прямой запрос даёт 404). `secure_link`
добавлять **не надо**: подпись наследуется относительными ссылками, а
`secure_link` требует отдельного токена на каждый ресурс и ломает отрисовку.

**Где лежит каталог контента.** `storage/app/courses/private/`, то есть
вне `public/`. Раньше он был в `storage/app/public/private/`, и
`php artisan storage:link` (обычный шаг деплоя) открывал весь материал по
`/storage/private/...` без проверки подписи. Перенос выполнен командой
`content:relocate`; путь задаётся `COURSES_PATH` (по умолчанию — новое
место), и единственный потребитель этого пути в коде — сам конфиг, так
что менять нужно `.env` и `alias` в nginx, а не код.

---

## 5. Экзамены и банк вопросов

| Класс | За что отвечает |
|---|---|
| `app/Models/Exam.php` | Экзамен — пара `aukstructure_id + category_id` для отбора, окно `opens_at/closes_at`, `max_attempts`, `passing_score`. `questionQuery()` применяет лимит вопросов, `stateFor()` — статус. |
| `app/Models/ExamAttempt.php` | Попытка сдачи: результат считается на сервере. |
| `app/Http/Controllers/ExamController.php` | CRUD экзаменов, выдача вопросов **без** `is_correct`, приём попытки со сверкой по `answers.is_correct`. |
| `app/Models/Question.php`, `app/Models/Answer.php` | Банк. Вопрос принадлежит паре `(category_id, aukstructure_id)`; `answers.is_correct` — носитель правильного варианта, поэтому чтение банка равно публикации ответов. |
| `app/Policies/QuestionBankPolicy.php` | Разделяет чтение банка (`questions.view`) и сдачу экзамена (`exams.take`). |
| `app/Http/Controllers/GiftController.php` | Разбор GIFT-файла в HTML. **В банк ничего не пишет** — счётчик в ответе создаёт иллюзию загрузки. |

Экзамен не хранит вопросы — он задаёт правило отбора. Иначе показывались бы
одни вопросы, а засчитывались другие.

---

## 6. AI-тренажёр

Отдельный контур: свои таблицы `tutor_*`, своё право `tutor.use`,
**не влияет на экзамены** — ни `exam_attempts`, ни банк вопросов не трогает.

| Класс | За что отвечает |
|---|---|
| `app/Models/TutorMaterial.php` | Материал = пара `(course_id, category_id)`. `isReady()` требует `status=indexed` и ненулевого числа фрагментов. |
| `app/Models/TutorChunk.php` | Фрагмент материала — единица работы. `scopeRelevant` ищет по `to_tsvector`. |
| `app/Models/TutorSession.php` | Сессия с окном контекста; `generation_started_at` — признак «вопросы готовятся». |
| `app/Models/TutorItem.php` | Сгенерированный вопрос. `toPlayerArray()` **не отдаёт** эталон и цитату — иначе тренажёр проходится на автомате. |
| `app/Models/TutorResponse.php` | Ответ пользователя. `ungraded` ≠ `wrong`: недоступность движка не должна превращаться в ноль. |
| `app/Jobs/GenerateTutorQuestions.php` | Фоновая генерация. Требует запущенного `php artisan queue:work`. Снимает метку генерации всегда, даже при падении движка. |
| `app/Support/Tutor/TutorMaterialIndexer.php` | Пересборка материалов: по одному материалу на каждую пару (курс, специальность), текст курса читается один раз на все пары. |
| `app/Support/Tutor/TutorMaterialExtractor.php` | Извлечение текста из HTML курса; каталоги `GIFT/app/models/orig/eDoc` исключены — тренажёр физически не читает банк экзамена. |
| `app/Support/Tutor/TutorQuestionGrounding.php` | Промпты и строгая приёмка: цитата обязана дословно найтись во фрагменте, для `mcq` эталон обязан быть среди вариантов, дедуп по fingerprint. |
| `app/Support/Tutor/TutorAnswerGrader.php` | Проверка ответа. `mcq`/`short` — кодом, `open` — моделью строго против эталона и цитаты. |
| `app/Support/Tutor/TutorAccess.php` | Что показывать пользователю: материалы по парам из `group2learnings`. |
| `app/Support/Tutor/TutorClient.php` | HTTP-клиент локального движка (Ollama/llama.cpp): `health()`, `generate()` c `think=false`, необязательный `embed()`. |
| `app/Support/Tutor/TutorSettings.php` | Переключатель `settings.tutor_enabled`. По умолчанию **выключен**. |
| `app/Support/Tutor/PostgresVectorStore.php` | Поиск по `tutor_chunks` через FTS. Всегда доступен. |
| `app/Support/Tutor/SatelliteVectorStore.php` | Векторный поиск через Python-сателлит с ChromaDB. Любой сбой не ломает тренажёр — вызывающий переключается на Postgres. |
| `app/Support/Tutor/TutorUnavailableException.php` | Отдельный тип «движок недоступен», чтобы отличать состояние окружения от плохих вопросов. |
| `app/Console/Commands/TutorQualityCheckCommand` (`TutorQualityCheck`) | `tutor:quality` — доля пригодных вопросов. |
| `app/Console/Commands/TutorSeedDemoQuestions.php` | `tutor:seed-demo` — демонстрационные вопросы. |

**Что здесь ломает интерфейс по неочевидной причине.** Генерация идёт в фоне.
Без `php artisan queue:work` задание не берётся никем: интерфейс крутил
спиннер молча, и понять, что запуск не в порядке, было невозможно. Теперь
при превышении `tutor.worker_stall_seconds` UI прямо пишет, что не запущен
обработчик очереди.

---

## 7. Пользователи, роли, группы (HTTP)

| Класс | За что отвечает |
|---|---|
| `app/Http/Controllers/AuthController.php` | Логин (Bearer Sanctum + нормализованные права и роли в ответе), `me`, `logout`, CRUD пользователей, `chpass`/`chroll`/`chperm`, `manageableUsers`, массовая запись групп на курсы. |
| `app/Policies/UserPolicy.php` | Чужая запись — только в своей группе и не администратору. Себя удалить и повысить нельзя. |
| `app/Http/Controllers/PermissionController.php` | CRUD прав и каталог по разделам; системные slug защищены от переименования. |
| `app/Http/Controllers/RoleController.php` | CRUD ролей. Роли из `role_matrix` системные: правка и удаление запрещены. |
| `app/Http/Controllers/GroupController.php` | CRUD групп; удаление сначала чистит учебные записи (внешнего ключа нет). |
| `app/Http/Middleware/CustomAuthenticateSessionMiddleware.php` | Алиас `custom`: сверяет хеш пароля в сессии, при расхождении делает logout. |
| `app/Http/Middleware/ApiResponseEnvelope.php` | Оборачивает любой `JsonResponse` в `{success, data, error, meta}`. Потоковые и файловые ответы не трогает. `data: null` на выходе даёт `data: []` — важно при чтении фронта. |
| `app/Http/Middleware/Authenticate.php` | Отдаёт 401 JSON вместо падения «Route [login] not defined». |

---

## 8. Настройки и служебное

| Класс | За что отвечает |
|---|---|
| `app/Models/Setting.php` | Универсальные настройки key-value. Сюда пишутся `content_delivery` и `tutor_enabled`, минуя `.env`. |
| `app/Http/Controllers/SettingsController.php` | `/api/settings`: список и изменение произвольных name/value **без валидации**, плюс отдельные endpoints переключателей раздачи и тренажёра. |
| `app/Http/Controllers/ClearDBController.php` | `POST /api/clear-database`: очищает 13 таблиц контента в обратном порядке внешних ключей. Пользователей, группы, роли и права не трогает. Требует `system.maintenance`. |
| `app/Http/Controllers/GlobalSearchController.php` | Единый поиск. Курсы/темы/специальности — в рамках видимости, люди/группы/банк — только при соответствующих правах. |
| `app/Http/Controllers/CalendarController.php` | События календаря из периодов учебных записей и `deadline`. |
| `app/GiftParser/GiftParser.php` | Разбор GIFT-файлов в пары вопрос/ответ. |
| `app/Lyx/*` | Разбор и конвертация `.lyx` (LyX). Код мёртвый: контроллер, который его звал, на маршрутах не смонтирован. |

---

## 8.1. Файловый менеджер

Подробно — `docs/file-manager.md`. Здесь только «за что отвечает класс».

| Класс | За что отвечает |
|---|---|
| `app/Support/FileManager/EntryName.php` | Единственная проверка имени файла и папки. Запрещает разделители, NUL, ведущую/хвостовую точку. Отвергает, а не чистит: молчаливая очистка создала бы объект, которого пользователь не просил. |
| `app/Support/FileManager/Location.php` | Арифметика путей и диск. **Не знает о базе**: что такое папка и кому она принадлежит, решает `FolderTree`. Проверка `isInsideUserRoot` сверяет `realpath` с корнем пользователя — это ловит симлинк, прошедший проверку имени. |
| `app/Support/FileManager/FolderTree.php` | Дерево папок: кто чей, путь от корня, зацикливание, свободное имя. Родитель проверяется на принадлежность пользователю. |
| `app/Support/FileManager/FileManagerService.php` | Операции над папками и файлами. Порядок «сначала диск, потом база» и откат зафиксированы здесь, а не в контроллере. |
| `app/Support/FileManager/ChunkUploadService.php` | Протокол загрузки по частям. `init` идемпотентен по отпечатку файла — это докачка. Размер каждой части сверяется с ожидаемым, иначе объявленный размер ничем не ограничен. |
| `app/Http/Controllers/FileManagerController.php` | Каталоги, папки, файлы, скачивание. Контроллер тонкий намеренно. |
| `app/Http/Controllers/ChunkUploadController.php` | Приём частей. Отдельный от `FileManagerController`: у него другая форма запроса (тело — данные, а не JSON). |
| `app/Console/Commands/FileLimitsCommand.php` | `files:limits`: сравнивает размер части с `post_max_size` и печатает готовые строки nginx. |
| `app/Console/Commands/PruneFileUploadsCommand.php` | `files:prune-uploads`: убирает брошенные загрузки и их части. |

**Почему файлы лежат вне `public/`.** Каталог `storage/app/public`
попадает под корень веб-сервера через `php artisan storage:link`, и всё,
что туда легло, становится доступно по адресу `/storage/…` без проверки
прав. Старый `FilesController::upload` писал на диск по умолчанию, и одна
смена `FILESYSTEM_DISK=public` тихо делала личные файлы доступными всем,
у кого есть ссылка.

**Что не ломает менеджер.** Старый `POST /api/files/add` и компонент
`FileLoadSimple.vue` (его подключают формы курсов) продолжают работать;
записи, созданные до менеджера, имеют `path = null` и менеджером не
показываются.

---

## 9. Классы-остатки и мёртвый код

Их видно в IDE и они провоцируют вопросы. Список, чтобы не искать:

| Класс | Состояние |
|---|---|
| `app/Http/Controllers/CoursesController.php` | Не смонтирован на маршрутах (сканирование каталога, `.lyx`). |
| `app/Http/Controllers/PrivateManiController.php` | Маршрут закомментирован. |
| `app/Http/Controllers/UsersCoursesController.php` | Отладочный, `echo` без ответа. |
| `app/Http/Middleware/RoleMiddleware.php` | `handle` целиком закомментирован. |
| `app/Policies/PermissionPolicy.php` | Заглушка, правила живут в `PermissionScope`. |
| `app/Policies/GroupPolicy.php` | Устаревшая, на строковых ролях. |
| `app/Models/Favorite.php`, `TestResult.php`, `CategoryCourse.php`, `AukstructureCategory.php` | Остатки: связи не заданы или таблица выводится неверно. |

Отдельно про маршруты: `apiResource` генерирует `show/store/destroy`, которых в
классе может не быть — такой запрос даёт **500, а не 404**.

---

## 10. Как читать этот проект дальше

1. Начать с `resources/js/Router/routes.js` — оттуда видно, какие страницы
   вообще существуют, и у каждой видно `meta.permission`.
2. Права смотреть в `config/permissions.php`, а не в коде: middleware
   сверяется именно с этим списком.
3. Прежде чем править выдачу курса — прочитать раздел 4. Там четыре решения,
   каждое из которых выглядит избыточным, пока не столкнёшься с вложенными
   ресурсами курса.
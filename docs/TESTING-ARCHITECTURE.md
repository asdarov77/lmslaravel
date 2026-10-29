# Архитектура тестового покрытия LMS

Стек: Laravel 11 + PostgreSQL (PHPUnit, `RefreshDatabase`), Vue 3 + Vuetify 3
(Vitest — компонентные/unit-тесты фронтенда), Playwright (E2E + визуальные снапшоты).

## Слои и файлы

```
tests/
├── Feature/                          # Бэкенд + БД (PHPUnit, pgsql lmslaravel_test)
│   ├── CoursePlayerApiTest.php       # НОВЫЙ: CRUD/бизнес-логика player-endpoint'а:
│   │                                 #   /player, /progress, /view — DB state, RBAC,
│   │                                 #   валидация, поддельные id, чужие узлы курсов
│   ├── CourseContentSecurityTest.php # НОВЫЙ: безопасность CourseContentController:
│   │                                 #   анонимный доступ, фальшивые токены (?t=),
│   │                                 #   revoked/expired токены, traversal/NUL,
│   │                                 #   403 по записи/специальности, XSS-санитайзер,
│   │                                 #   подсветка <term>, no-store, X-Accel
│   └── CoursesCrudRbacTest.php       # НОВЫЙ: полный CRUD /api/courses для трёх ролей
│                                     #   (Обучаемый / Инструктор / Администратор),
│   │                                 #   Form-валидация, границы дат, exists-правила
│   ├── CoursePlayerEndpointTest.php  # (существует) контракт формата payload
│   ├── CourseContentAccessTest.php   # (существует) гейтинг CourseAccess
│   └── PrivateContentSignatureTest.php # (существует) HMAC-подпись приватного контента
│
├── Unit/                             # Vitest: composables и компоненты (jsdom)
│   ├── composables.lesson-progress.spec.js # НОВЫЙ: useLessonProgress — тики,
│   │                                 #   throttled /view, resume-priority, LS-fallback
│   ├── composables.content-frame.spec.js   # НОВЫЙ: useContentFrame — navigate,
│   │                                 #   height modes, ResizeObserver, anchors
│   └── composables.course-player.spec.js   # (существует) useCoursePlayer контракт
│
└── E2E/                              # Playwright против php artisan serve :8080
    ├── course-player-flows.spec.js   # НОВЫЙ: симуляция пользователя в плеере:
    │                                 #   дерево/табы, play→pause, модалки, v-select,
    │                                 #   пагинация, формы с валидацией, состояния
    │                                 #   кнопок (hover/focus/disabled)
    └── visual-regression.spec.js     # НОВЫЙ: toHaveScreenshot на login / плеер /
                                      #   file-manager × десктоп/планшет/мобильный,
                                      #   hover/focus/disabled/error-state снапшоты
```

## Как запускать

```bash
# Бэкенд (нужен PostgreSQL lmslaravel_test, см. phpunit.xml)
php artisan test --testsuite=Feature
vendor/bin/phpunit tests/Feature/CoursePlayerApiTest.php

# Фронтенд unit/composable
npm run test            # vitest run
npx vitest run tests/Unit/composables.lesson-progress.spec.js

# E2E + визуальные (webServer стартует сам; для первых прогонов — update snapshot)
npx playwright test tests/E2E/course-player-flows.spec.js
npx playwright test tests/E2E/visual-regression.spec.js --update-snapshots   # baseline
npx playwright test tests/E2E/visual-regression.spec.js                       # регресс
```

Снапшоты хранятся рядом со spec-файлами (`tests/E2E/visual-regression.spec.js-snapshots/`)
— стандартный механизм Playwright, отдельная папка `docs/screenshots-baseline`
не дублируется.

## Найденные дефекты и предложенные фиксы (см. комментарии в тестах)

1. **CourseContentController::show** не проверял `type === TYPE_MATERIAL`
   (в отличие от `/view`). Раздел (type 2) с проставленной ссылкой отдавался как
   материал. ✅ ИСПРАВЛЕНО: в show() добавлен `->where('type',
   CoursePlayerService::TYPE_MATERIAL)`; регрессия — пара тестов в
   CourseContentSecurityTest (раздел→404, материал→200).
2. **CoursePlayerController** не гейтился `CourseAccess` (в отличие от
   content/raw-endpoint'ов): любой вошедший получал структуру чужого курса по id,
   а POST /progress и /view писали прогресс в ЛЮБОЙ курс (перебор id → чтение
   чужих курсов, накрутка «прошли», порча агрегатов stats).
   ✅ ИСПРАВЛЕНО: `CourseAccess::authorizeOpen($user, $course)` добавлен в
   show(), progress() и view(); порядок ответов 401 → 404 → 403 закреплён
   тестами CoursePlayerApiTest (outsider→403 + пустая БД, admin→200,
   инструктор без courses.manage→403).
3. **useLessonProgress.unbindUnloadGuards** снимает не тот слушатель:
   `document.removeEventListener('visibilitychange', onPageHide)`, тогда как
   добавлялась анонимная стрелочная функция → утечка слушателя и «двойной»
   flush после unmount. Фикс: сохранить обработчик в переменную модуля.
4. **ValidatePrivateContentSignature**: подпись из query принималась даже когда
   запрос идёт по path-формату `{expires}/{signature}/...` — смешение двух
   схем допускало replay старой query-подписи на новых путях и ложные 403 на
   легитимных path-URL с посторонней query. ✅ ИСПРАВЛЕНО: если path подписан
   (первый сегмент — число expires), query игнорируется полностью; ответ на
   path-URL определяется только path-подписью. Регрессия —
   PrivateContentSignatureTest::query_подпись_не_переопределяет_path_подпись.

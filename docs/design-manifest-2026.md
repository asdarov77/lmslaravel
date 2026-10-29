# Дизайн-Манифест LMS 2026 и UX-Спецификация «Плеер 2.0»

> Аудит + концепция масштабного редизайна. Сверено с кодом ветки на 2026-10-29.
> Связанные документы: `docs/ui-design.md` (токены/конвенции), `docs/design-redesign-plan.md`
> (фазы), `docs/course-player-blueprint.md` (архитектура плеера), `docs/nginx-x-accel-setup.md`.
> Реализация шаблона из Части 4 — `resources/js/Pages/CoursePlayer.vue`,
> `resources/js/components/player/LessonWorkspace.vue`, `components/course/CourseTree*.vue`
> (feature-flag `config/ui.php → course_player_v2`, маршрут `courses.play`).

---

## 0. Результаты аудита: что есть, чего нет

**Найдено в коде (не «с нуля», а поверх):**
- Единая токеновая система: `resources/css/tokens.css` (палитра, радиусы 6/10/14/20/pill,
  тени `--shadow-1…5`, WCAG-починка `--c-text-muted` до контраста 6.35:1) + тема Vuetify,
  читающая те же токены (`Vuetify/index.js`) — цвет в `v-btn` и `.u-btn--primary` физически один.
- Каркас плеера v2 уже существует: URL-as-truth (`?item=`), race-guard `useLessonLoader`,
  remount по `:key="epoch"`, дерево с клавиатурной навигацией (roving tabindex), feature-flag.
- Геймификация (`Gamification.vue` + streak + Heatmap), расписание/дедлайны (`Schedule.vue`,
  `MyLearningPlan.vue`), дашборды ролей, chunk-upload (`services/chunkUpload.js`), X-Accel-раздача.

**Чего нет (подтверждено grep/чтением кода):**
| Дефицит | Где должно жить |
|---|---|
| Cinema/Focus Mode в плеере | `CoursePlayer.vue` — только двухпанельный каркас, затемнения нет |
| Статусы узлов в дереве (locked/overdue/in-progress) и иконки типов контента | `CourseTreeNode.vue` рисует только ✓ visited |
| TutorDock (AI-ассистент в уроке) | ни одного файла; есть только standalone `Pages/Tutor/*` |
| Drip Content / rules-движок (UI) | `drip` не встречается нигде; конструктор правил — часть Blueprint §3.1 |
| Драм-н-дроп в конструкторе курса | `CourseWizard.vue` 1034 стр., структура — плоский список без перетаскивания |
| Micro-interaction layer (transition-словарь) | анимации разбросаны по scoped-блокам, единого языка нет |
| prefers-reduced-motion в теме | чинится ниже (§1.4) |

---

## 1. Design System & Styling Guide (Vuetify 3 / MD3)

### 1.1. Как уйти от «коробочного Material» к премиальному EdTech

Болезнь типовых Vuetify-приложений: серые карточки + дефолтные elevation-тени MD2 +
синие кнопки + одинаковые скругления везде. Лечение — пять приёмов:

1. **Zero-elevation + layered shadows.** Все `v-card` c `elevation: 0` (уже стоит в defaults
   темы). Глубина — только через CSS-тени `--shadow-1…5`: мягкие, двухслойные, с большим
   blur и низкой альфа-прозрачностью (0.05–0.16) — «Apple/Figma-тень», а не MD2-ступени.
   Правило lint уже есть: `elevation-12` запрещён. Наведение карточки урока: shadow 1→2 за
   180мс + translateY(-1px). Тень — единственный «layered surface» инструмент; glassmorphism
   (`--c-glass-shadow`) — точечно: overlay-панели плеера, тултипы.
2. **Типографика как основной декор.** Inter (уже базовый) + дисплейный вес для заголовков
   уроков: 600/28px, letter-spacing -0.02em. Шкала токенов `--fs-sm…4xl` соблюдается;
   в контентных зонах line-height 1.7 (длинное чтение). Капслок-эйлиасы запрещены,
   метаданные — `text-caption` + `--c-text-secondary`.
3. **Асимметричные радиусы вместо uniform.** Карточки `--radius-xl: 20px`; поля `--radius-md: 10px`;
   CTA — pill; медиа-контейнер урока — 20px сверху, 0 снизу в cinema-mode (единый блок с панелью
   управления). Дерево курса: активный узел — «пилюла» (radius-pill) с primary-soft фоном,
   а не прямоугольная полоса выделения.
4. **Один акцент + статусные semantics.** Бренд-синий `#1a5fb4` — действия; teal `#0d9488` —
   «в процессе/живой прогресс» (streak, ring); success — пройдено; warning — дедлайн близко;
   error — просрочено. Градиенты — только в двух местах: оверлей-скрим в cinema-mode и
   progress-ring (conic-gradient teal→blue). Никаких градиентных кнопок.
5. **Motion-словарь (см. §1.4).** Единые кривые: вход — `cubic-bezier(0.2, 0, 0, 1)` (MD3
   emphasized), выход — ease-out; длительности `--dur-fast: 120ms`, `--dur-base: 180ms`,
   `--dur-slow: 280ms`. Всё, что появляется — fade+slide 8px; всё, что исчезает — только fade.

### 1.2. Палитра (light/dark — уже в tokens.css, фиксируем роли)

| Роль | Light | Dark | Применение |
|---|---|---|---|
| primary | #1a5fb4 | #7aa7e8 | действия, active-узел |
| accent | #0d9488 | #2dd4bf | прогресс «в процессе», streak |
| bg | #f4f6f8 | #14171a | холст страницы |
| surface | #ffffff | #1c2024 | карточки |
| surface-3 | #f0f2f5 | #2a2f35 | hover-ряды, dock-секции |
| text / secondary / muted | #1c2024 / #5a626c / #5a6068 | … | контраст ≥4.5:1 проверен |

Тёмная тема плеера — не инверсия, а «kinopoisk-режим»: фон рабочей области `#0b0d10`
(глубже обычного dark-bg), чтобы видео выглядело нативно; боковое дерево остаётся на
`--c-surface` dark. Логика переключения: ThemeToggle → класс `v-theme--dark` → tokens.css
переопределяет переменные → тема Vuetify читает их через probe-элемент (реализовано).

### 1.3. Defaults темы (дополнить к существующим в `Vuetify/index.js`)

```js
// resources/js/Vuetify/index.js — proposed additions to defaults:
VNavigationDrawer: { elevation: 0, width: 320 },      // rail 56 в свёрнутом виде
VProgressLinear: { roundedBar: true, height: 6 },     // трек --radius-pill
VProgressCircular: { width: 3 },
VTooltip: { openDelay: 300, closeDelay: 80 },         // lock_reason у замков
VDivider: { opacity: 1, class: 'u-divider' },         // цвет из border-color
VListItem: { rounded: 'lg', activeClass: 'u-list-active' },
```

### 1.4. Accessibility (WCAG 2.2 AA) — конкретикой

- **Contrast**: уже починен muted-текст (3.14→6.35:1). Правило: статус дерева кодируется НЕ
  только цветом — ✓/◐/🔒/❗ иконки + aria-label; точки дедлайнов — форма+иконка.
- **Keyboard**: дерево — roving tabindex + Arrow*/Home/End (реализовано в CourseTreeNode).
  Добавить глобальные шорткаты плеера: `f` — cinema mode, `[`/`]` или `←/→` — prev/next,
  `t` — tutor, `/` — поиск по курсу, `Esc` — выход из модалок/cinema. Все шорткаты игнорируют
  ввод в полях (`e.target.closest('input,textarea,[contenteditable]')`).
- **Focus-visible**: 2px solid primary offset 2px (уже конвенция); в cinema-mode кольцо —
  white 2px на тёмном фоне (contrast-safe).
- **Reduced motion**: `@media (prefers-reduced-motion: reduce)` — все transition-duration→0,
  skeleton-shimmer→static, confetti-анимация сдачи теста заменяется static-бейджем ✓.
  Вводится одним global-блоком в app.css (сейчас патчится локально).
- **Live regions**: `aria-live="polite"` на строке статуса загрузки урока («Загружаем видео…»),
  `role="status"` на toast «Урок пройден +XP»; SSE-ответ ИИ — не спамит live-region посимвольно
  (announce по завершении блока).
- **Seizure-safe**: автопредпросмотр видео при фокусе урока — muted, по умолчанию выключен.

---

## 2. UX-Паттерны

### 2.1. Плеер 2.0 — бесшовный режим обучения

**Композиция (desktop ≥1024):** левый drawer 320px (rail 56) │ рабочая область (max-width 920
для текста, медиа — вся ширина) │ правый TutorDock 360px (overlay, не толкает layout).
Верхняя панель 56px: back, breadcrumb Модуль›Урок, тонкий linear-progress курса (встроен в
бордер панели — «Netflix-строка»), streak-chip, theme, fullscreen(f).

**Cinema Mode (клавиша F / кнопка expand):**
1. Хедер и footer уходят вверх/вниз (translateY + fade, 280ms emphasized-decelerate).
2. Drawer сворачивается в rail 56px; hover/focus у левого края высвобождает overlay-дерево
   (position:absolute, scrim-градиент, не сдвигая контент — no layout shift).
3. Рабочая область растягивается на всю viewport-высоту; фон — #0b0d10; вокруг медиа —
   radial-vignette. Панель управления уроком (prev/next/complete) всплывает bottom-sheet
   при движении мыши, прячется через 3с простоя (idle-timer, сбрасывается на focus внутри).
4. Выход: Esc / клик по scrim-drawer / кнопка. Позиция скролла и playback сохраняются.

**Разделение «дерево ↔ рабочая область»: три сигнала текущего места:**
(a) активный узел — primary-soft пилюля + левая 3px accent-полоса; (b) модуль активного урока
авто-раскрыт и его заголовок sticky вверху drawer; (c) над деревом — ring-прогресс модуля
(«3/8») и общий процент курса. Типы уроков — иконки mdi:movie-outline / file-document-outline /
file-pdf-box / download / help-circle-outline / robot-outline; статусы — done ✓ (success),
in-progress ◐ (accent, медленный rotate для «смотрит сейчас»), locked 🔒 (disabled + tooltip
lock_reason: «Откроется 12 нояб. после модуля 2»), overdue ❗ (error-точка).

### 2.2. Микро-взаимодействия и state transitions

- **Переключение урока**: workspace не «пересобирается шумно» — контентная область делает
  fade-out 120ms → skeleton (2 строки + media-блок, под тип урока) → fade-in 180ms со slide-up
  8px. Race-guard уже гарантирует: рендерится только победитель гонки (`epoch`).
- **Загрузка защищённого видео (X-Accel)**: состояния `reserving → streaming → playing`.
  Фаза «reserving» (PHP проверяет подпись, отдаёт пустое тело с X-Accel-Redirect) показывается
  shimmer-полосой поверх poster-кадра + подпись «Готовим доступ…» (<300мс, обычно не видна);
  если >1с — прелоад первых байтов Range и авто-play с 0.5x на 1с для маскировки буфера.
  Signed-url кэшируется на урок (не перевыпускается при перемотке — blueprint §2.4).
- **Сдача теста внутри плеера**: submit → кнопка spinner «Проверяем» → серверный verdict →
  (а) pass: ring-прогресс урока докручивается до 100% (spring 400ms), чип ✓ пролетает из кнопки
  в узел дерева (FLIP-анимация 350ms, position shared-element), node morphs в «done»,
  toast «+20 XP · Streak 12 дней» (role=status), next-урока подсвечивается pulse-кольцом
  1 раз; (б) fail: shake 6px 250ms на карте результата, разбор ошибок по вопросам,
  кнопка «Пересдать» первичная. Всё — `prefers-reduced-motion: static`.
- **Мобильная трансформация (<768)**: drawer → temporary (bottom sheet на 60% vh, drag-handle,
  свайп справа налево открывает дерево, swipe сверху — tutor); верхняя панель 48px; панель
  prev/next → фиксированный bottom-bar «← Пред · [Продолжить ▸] » (Duolingo-style, большая
  CTA во весь ширины); double-tap по краю видео ±10с; long-press — скорость 1.5x временно.
  Tablet (768–1024): persistent drawer 280px, tutor — bottom-sheet.

### 2.3. Конструктор курса (Course Builder)

- **DnD**: `vuedraggable@next` поверх `v-list`; ручка `mdi-drag-vertical` слева (cursor grab,
  row подсвечивается только при hover на ручке — лёгкость); ghost — dashed primary-soft
  контур той же высоты (zero-jump); chosen — shadow-3 + scale 1.02. Drop-constraints: урок
  не покидает модуль-контейнер типа «course» (onMove-фильтр). Keyboard fallback ▲▼ уже
  требуется планом — остаётся обязательным (drag — мышинная надстройка, не замена).
- **Inspector-панель** справа (400px): выбрал узел → форма правил пишет debounce 500мс в
  `PUT /aukstructure/{id}/rules`, optimistic + undo-toast 5с. Никаких модалок на каждое поле.
- **Drip Content — визуальные переключатели, а не select-зоопарк**: сегментированные «карточки
  режима» (None / По дате / После урока / После теста ≥ балла) — radio-group стилизованный под
  4 tile'а с иконкой и one-line описанием; выбор раскрывает только нужное поле (date-picker /
  prerequisite-select / score-slider) через v-expand-transition. Порог балла — slider с живым
  label «≥ 70 из 100». Запрет конфликтов — inline-alert «Дедлайн раньше даты открытия».
- **Stepper**: 4 шага Basics→Structure→Rules→Publish, шаг в `?step=`, незавершённые — disabled
  с tooltip причины; выход — leave-guard (composable есть). «Preview as student» — кнопка-глаз
  в header, открывает CoursePlayer с preview-флагом.

### 2.4. Файловый менеджер (chunk upload states)

Очередь `chunkUpload.js` уже есть — UI-слой состояний поверх grid/list:
- карточка загрузки = имя + полоса чанков (сегментированный linear: переданные/активные/ошибка)
  + скорость/осталось + кнопки pause/resume/retry;
- `uploading` → `processing` («Обрабатываем на сервере…» indeterminate + virus/transcode-чип)
  → `ready` (flip-анимация карточки в сетку, skeleton→thumb превью PDF-first-page/изображения);
- `paused` — desaturated + чип; `error` — red-полоса + причина (413/сети/403) + «Докачать с
  N-го чанка» (resume-идемпотентность бэка уже заложена);
- drop-zone на всю область сетки: page-level dragenter → dashed overlay «Отпустите — файлы
  попадут в текущую папку»; контекстное меню — v-menu с actions Переименовать/Переместить/
  Ссылка на урок/Удалить (ConfirmDialog-конвенция). Превью — side-panel 480px, не модалка.

### 2.5. AI-Tutor Dock

Правый overlay-dock 360px (FAB `mdi-robot-outline` с badge непрочитанных; в mobile — bottom-sheet):
- **Context-pill** сверху: «📄 Контекст: §2.3 „Правила последовательности…“ ✕» — явная маркировка,
  что фрагмент открытого урока подмешан в промпт (blueprint §1.2b); клик по пилюле скроллит урок к цитате.
- Ответы — SSE-стрим (typed caret ▍, stop-кнопка); подсказки трёх уровней — segmented control
  «Наводка / Часть решения / Полный ответ», уровень уходит в telemetry; между уровнями —
  confirm-полоса («Полный ответ снизит обучающий эффект») для честности геймификации.
- Сообщения ИИ визуально отличаются: surface-variant фон, левая accent-полоса 2px, иконка робота;
  цитаты из урока — blockquote с монограммой раздела. Rate-limit countdown чип в поле ввода.

### 2.6. Gap-лист LMS-функциональности (что ещё заложить)

Студент-кабинет: «Продолжить» hero-карточка (poster + % + таймкод), тепловая карта активности
365d (есть Heatmap — вынести на кабинет), сертификаты-витрина. Дашборд преподавателя: очередь
на проверке (assignment grading queue), аналитика провала вопросов (bar per question). Календарь:
дедлайны уроков (G3 blueprint) поверх courses.opens/closes; повестка недели + streak-цель.
Геймификация: XP-ленты рангов, ачивки-тосты только на порогах (не спамить).

---

## 3. Дорожная карта внедрения (поверх существующих фаз)

| Шаг | Что | Опора |
|---|---|---|
| M1 | CoursePlayer v2 UI: statuses/иконки в дереве, cinema-mode, motion-словарь | флаг `ui.course_player_v2`, REWORK R2/R3 |
| M2 | Vitest гонки + Playwright player-race.spec | REWORK R1 |
| M3 | TutorDock (SSE) + context-pill | blueprint §3.3 |
| M4 | DnD+Inspector в CourseWizard, drip-тайлы | blueprint §3.1 (нужен reorder-endpoint — design-redesign-plan «остаток требует бэкенда» п.1) |
| M5 | Upload-states UI FileManager | services/chunkUpload.js |
| M6 | Global reduced-motion + focus-ring audit | app.css |

### 3.1 Статус M1 (обновление текущей итерации)

Уже выложено из M1 (готово к подключению, ничего не ломает в legacy):

| Артефакт | Что даёт |
|---|---|
| `resources/js/components/course/CourseTreeNodePremium.vue` | дерево со статусами done/current/locked/overdue (значок + sr-only, не только цвет), иконками типов контента, активным узлом-«пилюлей», roving tabindex + стрелки/Home/End |
| `resources/js/components/player/InlineQuizRunner.vue` | тест «один вопрос на экран» внутри плеера; verdict наружу (оценка только сервером), клавиши 1–4, прогресс-полоса |
| `resources/js/Pages/CoursePlayerPremium.vue` | композиция Плеера 2.0: cinema-mode, rail/tree, resume-hero, lock-card, фазы X-Accel, CTA-пилюля, mobile bottom-bar, TutorDock; computed-логика крошек/модульных статистик по контракту nodes+parent_id |
| `resources/css/tokens.css` | кино-токены `--c-cinema-*` |
| `locales/{ru,en}.json` | ключи `coursePlayer.*` (treeStatus, types, quiz, cinema…), `tutor.{open,hintNudge,hintPartial,hintFull,askAboutLesson,rateLimit}`, `gamification.streakAria`, `courseManifest.prevShort` |

Осталось для закрытия M1 (следующая итерация):
1. Тонкий роут-обёртка: смонтировать `CoursePlayerPremium` под флагом `ui.course_player_v2`
   вместо/рядом с `Pages/CoursePlayer.vue` (props уже совпадают: courseId/nodes/lesson/status/errorKey/epoch/stats/resume).
2. Серверные поля узлов `content_type/locked/lock_reason/due_at/duration_sec` (bootstrap endpoint) — до их появления premium-узел мягко деградирует до текущего контракта (visited/is_material).
3. Wire SSE-дока TutorDock к `api/tutor.api.js` (M3) и submit теста к exam-эндпоинту (колбэк-контракт `quiz-submit` уже заложен).

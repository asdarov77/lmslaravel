# Манифест удалённых файлов

Файлы мертвы по графу импортов: от точки входа `resources/js/app.js`
не достигается ни один путь. Проверено тремя способами:

1. `tools/reachability.py` — обход графа импортов от точки входа
   (комментарии при разборе отбрасываются, иначе импорт из
   закомментированного маршрута считал бы файл живым);
2. точный поиск спецификаторов импорта по всему `resources/js` и `tests/`;
3. поиск строковых ссылок: `resolveComponent`, теги в kebab-case
   (`<popup>`), глобальные регистрации `app.component`.

Ни один кандидат не смонтирован в `routes.js` (в том числе в
закомментированных блоках) и не подключён из `resources/views`. 
После удаления пройдены `npm run build`, PHPUnit, Vitest и Playwright.

Файлы восстанавливаются из истории git.

## Легаси-страницы, удалённые 2026-10-07

Решение принималось вместе с редизайном навигации, а не отдельной
чисткой: пока `/` открывал `Home.vue`, а в меню звался «Профиль» на
`/my`, страница `MyAccount.vue` была недостижимой.

| Файл | Почему удалён |
|---|---|
| `resources/js/Pages/Home.vue` | Переключатель ролей на корневом адресе: админу показывал одно, инструктору другое, обучаемому третье, дублируя меню. `/` стал редиректом на `/dashboard`, где `RoleDashboard` сам выбирает вид по роли. |
| `resources/js/Pages/User/UserPage.vue` | `/auk` — «Мои курсы» сырой таблицей без оформления. Пункт меню уже был убран как дубль личного кабинета, а `/my/learning` показывает тот же список в дизайн-системе. Вместе с маршрутом `/auk`. |
| Комментарий-блок «удалить» в `routes.js` | 116 закомментированных строк со старыми `/auk`-маршрутами на несуществующие компоненты `Test/*`. Читались как рабочие маршруты и были второй правдой о маршрутах. |

Переписан, а не удалён: `resources/js/Pages/MyAccount.vue` — шаблон был
целиком закомментирован, и пункт «Профиль» в меню вёл на пустую
страницу. Теперь это настоящий профиль: данные пользователя и его права
из стора, без дополнительного запроса.

Файлы восстанавливаются из истории git.

## Резервные копии страниц (суффиксы `copy`, `Orig`)
- `resources/js/Pages/Gift/ExamineForm copy.vue`
- `resources/js/Pages/Gift/QuestionNewOrig.vue`
- `resources/js/Pages/Group/GroupLearning Orig.vue`
- `resources/js/Pages/User/UserPage copy.vue`

## Компоненты, вытесненные дизайн-системой или дублями
- `resources/js/components/About.vue`
- `resources/js/components/FileUploader.vue`
- `resources/js/components/Navbar.vue`
- `resources/js/components/ProgressLinear.vue`
- `resources/js/components/Welcome.vue`

## Обёртки, оставшиеся от прежних правок
- `resources/js/Pages/Modal/Dialog.vue`
- `resources/js/Pages/PermissionWrapper.vue`
- `resources/js/Pages/Popup.vue`

## Незадействованные страницы и файлы
- `resources/js/Pages/Calendar.vue`
- `resources/js/Pages/CourseManifestToday.vue`
- `resources/js/Pages/CourseManifestVUE.vue`
- `resources/js/Pages/Filemanager/FileManager2.vue`
- `resources/js/Pages/Filemanager/FileManagerList.vue`
- `resources/js/Pages/Get.vue`
- `resources/js/Pages/UploadFiles.vue`
- `resources/js/Pages/User/RecursiveTable.vue`

## Файлы, которые анализатор не видел

`tools/reachability.py` сканирует только `*.vue` и `*.js`, поэтому файлы с
нестандартным расширением в список не попадали. Проверены отдельно:

- `resources/js/Pages/CourseManifestProba` — компонент на 31 КБ **без
  расширения**. Импортировать его невозможно: сборщик не подставляет `.vue`
  к спецификатору без расширения, а в `routes.js` он не смонтирован. Это
  черновик страницы просмотра курса.
- `resources/js/Pages/file.txt` — текстовый файл рядом со страницами, ссылок
  нет.

- `resources/js/Styles/app.scss` — 44 строки, из которых **все** строки
  комментарии: ни одного правила. Пакет `sass` в зависимостях не
  установлен, файл никем не импортируется. Это заготовка Vue CLI,
  оставшаяся после перехода на Vuetify.

## Barrel-файлы и устаревшая инициализация
- `resources/js/Pages/event-utils.js`
- `resources/js/Pages/index.js`
- `resources/js/Store/modules/index.js`
- `resources/js/plugins/vuetify.js`
- `resources/js/services/http-common.js`

## Файлы вне `resources/js`

- `index.html` в корне — самостоятельная страница-просмотрщик (20 КБ), к
  сборке и blade не подключена.
- `example.txt` — 43 байта клавиатурной чехарды, файл не использовался.

`merged_all.txt` оставлен по решению владельца: это дамп всех файлов
проекта, который может пригодиться как подстраховка.

## Данные, удалённые вместе с группами

`GroupController::destroy()` удаляет записи группы на курсы каскадом
(`DB::table('group2learnings')->where('group_id', …)->delete()`). Это
нужное поведение: без внешнего ключа на `groups` записи оставались
сиротами и всплывали в общем списке `/api/learning`.

Побочный эффект: тесты, удалявшие группы, уносили с собой и их
учебные планы. В боевой базе `group2learnings` оказался пуст (6 записей
до начала работы). Восстановить их нечем — дампа базы нет.

Что сделано, чтобы это не повторялось:

- `tests/E2E/calendar.spec.js` больше не работает на «ambient-данных»:
  он создаёт собственную группу и период обучения и удаляет их в
  `finally`. Раньше при пустом календаре он либо пропускал себя, либо
  падал на ожидании `.fc-daygrid-day`, которого при пустом календаре
  не существует.
- `tests/E2E/exam.spec.js` больше не полагается на `answer_id: 1`.
  Константа проверяла существование ответа №1, а не лимит попыток:
  как только таблица `answers` пересоздавалась, валидация отвечала 422
  вместо ожидаемого 403, и проверка теряла смысл.

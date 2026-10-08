# LMS (Laravel + Vue 3 + Vuetify 3 + PostgreSQL)

## Требования
- PHP 8.1+, Composer
- Node 18+, npm
- PostgreSQL 13+

## Установка
1. Установка зависимостей:
```
composer install
npm ci
```
2. Инициализация окружения и ключа:
```
cp .env.example .env
php artisan key:generate
```
3. Настройка БД в `.env` (pgsql), затем миграции и сиды:
```
php artisan migrate --seed
```
4. Запуск backend и frontend:
```
php artisan serve
npm run dev
```

## API
- Доступно по `/api` и `/api/v1`
- Конверт ответа: `{ success, data, error, meta }`
- OpenAPI: `docs/openapi.yaml`

## Документация

| Файл | О чём |
|---|---|
| `docs/architecture.md` | Карта проекта: подсистемы, классы, где принимаются решения о доступе |
| `docs/rbac.md` | Роли, права, матрица ролей, почему роль приходит из двух источников |
| `docs/content-delivery.md` | Как устроена защищённая выдача материалов и почему подпись в пути |
| `docs/nginx-x-accel-setup.md` | **Рабочая заметка по выдаче через nginx:** что настроено локально, что проверено на живых запросах, три ошибки-ловушки и порядок действий на боевой машине |
| `docs/file-manager.md` | **Файловый менеджер:** загрузка по частям, папки, перенос, докачка, настройка nginx и PHP под большие файлы |
| `docs/ai-tutor.md` | Устройство AI-тренажёра и требования к локальной модели |
| `docs/ui-design.md`, `docs/vuex.md`, `docs/TECH_SPEC.md` | Дизайн-система, состояние Vuex, техническая спецификация |

## Файлы

Пункт меню «Файлы» → `/filemanager`: загрузка (в том числе больших
файлов по частям), папки, перенос, переименование, удаление.
Подробности — `docs/file-manager.md`.

## Тесты и CI
```
php artisan test          # backend
npm test                  # frontend (vitest)
npm run build
npm run test:e2e          # браузерные тесты
```

Проверка раздачи материала через настоящий nginx (поднимает его сам,
включает режим и прогоняет браузер):
```
npm run verify:nginx
```

Материал курсов лежит в `storage/app/courses/private` — за пределами
`public/`, иначе `php artisan storage:link` открыл бы его без проверки
подписи. Смена пути: `php artisan content:relocate` + `COURSES_PATH` +
`php artisan content:nginx-config` (порядок — в
`docs/content-delivery.md`).

CI (GitHub Actions) выполняет: миграции, сиды, backend тесты, сборку фронтенда.


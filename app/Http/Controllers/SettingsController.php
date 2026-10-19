<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\ContentDelivery;
use App\Support\Tutor\TutorClient;
use App\Support\Tutor\TutorSettings;
use Illuminate\Http\Request;

/**
 * Настройки /api/settings.
 *
 * index отдаёт все записи Setting, update меняет произвольные name/value
 * БЕЗ валидации — это осознанно «гибкий» эндпоинт, и потому опасный: правом
 * settings.manage можно записать что угодно, включая content_delivery.
 *
 * Переключатели раздачи контента и тренажёра вынесены в отдельные endpoints,
 * потому что у них есть типы, значения по умолчанию и проверка состояния
 * движка (TutorClient) — в общем update это недостижимо.
 *
 * Методов show/store/destroy нет, хотя apiResource их генерирует: 500, не 404.
 */
class SettingsController extends Controller
{
    /**
     * Настройки, у которых есть собственный предметный класс.
     *
     * Их нельзя править «общим» PUT /api/settings: у каждой свои
     * правила приведения и проверки значений (см. ContentDelivery,
     * TutorSettings), и общий маршрут обошёл бы их стороной.
     */
    private const SPECIAL_SETTINGS = [
        'content_delivery' => 'режим выдачи материала',
        'tutor_enabled' => 'состояние тренажёра',
    ];

    /**
     * Текущий способ раздачи приватного контента.
     *
     * GET /api/settings/content-delivery
     *
     * Отдельный метод вместо чтения всех настроек: значение нужно
     * показывать в галке, а список settings содержит и внутренние
     * ключи, которые в интерфейсе показывать незачем.
     */
    public function contentDelivery()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'mode' => ContentDelivery::mode(),
                'available' => ContentDelivery::MODES,
                'source' => Setting::where('name', ContentDelivery::settingName())->exists()
                    ? 'settings'
                    : 'config',
                // Имя internal-location: администратор должен видеть, что
                // именно вписать в конфиг nginx. Не угадать — значит
                // включить режим и получить пустые страницы.
                'accel_internal' => (string) config('private_content.accel_internal', '/_protected-content'),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Переключает способ раздачи.
     *
     * PUT /api/settings/content-delivery  {"mode": "php"|"nginx"}
     *
     * Отдельный метод, а не /settings/{setting}, потому что тот принимает
     * произвольные name/value без валидации: опечатка в значении писала
     * в бару режим, которого не существует, и материал переставал
     * отдаваться с непонятной ошибкой.
     */
    public function updateContentDelivery(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', ContentDelivery::MODES)],
        ]);

        ContentDelivery::persist($data['mode']);

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => ContentDelivery::mode(),
                'available' => ContentDelivery::MODES,
                'source' => 'settings',
                'accel_internal' => (string) config('private_content.accel_internal', '/_protected-content'),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Состояние тренажёра для настроек.
     *
     * GET /api/settings/tutor
     */
    public function tutor()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => TutorSettings::enabled(),
                'source' => TutorSettings::source(),
                'env_default' => (bool) config('tutor.enabled', false),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Включение/выключение тренажёра.
     *
     * PUT /api/settings/tutor  {"enabled": true|false}
     */
    public function updateTutor(Request $request)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        TutorSettings::persist((bool) $data['enabled']);

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => TutorSettings::enabled(),
                'source' => TutorSettings::source(),
                'env_default' => (bool) config('tutor.enabled', false),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Диагностика нейродвижка для страницы настроек.
     *
     * GET /api/settings/tutor/probe
     *
     * Отдельный эндпоинт, а не /api/v1/tutor/health: тот требует
     * tutor.use, а настроек касается администратор, у которого этого
     * права может не быть. Диагностика движка нужна именно для того,
     * чтобы понять, почему тренажёр не работает.
     */
    public function tutorProbe()
    {
        $client = app(TutorClient::class);
        $health = $client->health();

        return response()->json([
            'success' => true,
            'data' => [
                'available' => (bool) ($health['available'] ?? false),
                'url' => (string) config('tutor.url'),
                'model' => $client->model(),
                'model_present' => (bool) ($health['model_present'] ?? false),
                'models' => $health['models'] ?? [],
                'embeddings' => $client->embedAvailable(),
                'async' => config('queue.default') !== 'sync',
                'error' => $health['error'] ?? null,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Отображает список настроек.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $settings = Setting::all();

        return response()->json($settings);
    }

    /**
     * Одна настройка по имени.
     *
     * GET /api/settings/{setting}
     *
     * Метода не было, а маршрут был: любой такой запрос падал с 500.
     */
    public function show(string $setting)
    {
        $row = Setting::where('name', $setting)->first();

        if ($row === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => '404', 'message' => 'Настройка не найдена'],
            ], 404);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    /**
     * Обновляет настройки.
     *
     * PUT /api/settings
     *
     * Раньше здесь стояло `$request->all()` в цикле без всякой
     * проверки: любой ключ тела запроса трактовался как настройка, а
     * `Setting::where('name', …)->update()` молча ничего не делал,
     * если такой строки нет. Ответ «Настройки успешно обновлены»
     * отдавался в обоих случаях — то есть успех сообщался, когда
     * ничего не изменилось.
     *
     * Теперь тело проверяется, имена существующих настроек известны,
     * а неизвестное имя и отсутствующая настройка — честная ошибка.
     *
     * Настройки, у которых есть собственный предметный класс
     * (ContentDelivery, TutorSettings), здесь намеренно не трогаются:
     * их правила приведения и проверки значений живут там.
     */
    public function update(Request $request)
    {
        /*
         * Тело принимается в двух видах — оба описаны в контракте:
         *   { "settings": [ {name, value}, … ] }  — именованная форма;
         *   [ {name, value}, … ]                   — голый массив.
         * Валидируем каждый элемент отдельно: правило вида `*.name`
         * ломается на первом варианте (Laravel трактует объект
         * settings как элемент списка и требует settings.name).
         */
        $body = $request->all();

        $items = (isset($body['settings']) && is_array($body['settings']))
            ? $body['settings']
            : array_values($body);

        if ($items === []) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => '422', 'message' => 'Не передано ни одной настройки'],
            ], 422);
        }

        $updated = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['name']) || ! is_string($item['name'])) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => ['code' => '422', 'message' => 'Элемент должен содержать строковое поле name'],
                ], 422);
            }

            $name = $item['name'];
            $row = Setting::where('name', $name)->first();

            if ($row === null) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => ['code' => '422', 'message' => "Настройка {$name} не найдена"],
                ], 422);
            }

            $owner = self::SPECIAL_SETTINGS[$name] ?? null;

            if ($owner !== null) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => '422',
                        'message' => "Настройку «{$name}» ({$owner}) меняют через её собственный раздел",
                    ],
                ], 422);
            }

            $row->value = (string) $item['value'];
            $row->save();

            $updated[] = ['name' => $row->name, 'value' => $row->value, 'type' => $row->type];
        }

        return response()->json([
            'success' => true,
            'data' => $updated,
            'error' => null,
            'meta' => ['count' => count($updated)],
        ]);
    }
}

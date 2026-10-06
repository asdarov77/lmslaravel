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
     * Обновляет настройки.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $settings = $request->all();

        foreach ($settings as $setting) {
            $name = $setting['name'];
            $value = $setting['value'];

            Setting::where('name', $name)->update(['value' => $value]);
        }

        return response()->json(['message' => 'Настройки успешно обновлены']);
    }
}

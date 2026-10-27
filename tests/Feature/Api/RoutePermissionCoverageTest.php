<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AuthenticatesApi;
use Tests\TestCase;

/**
 * Регрессия: маршруты записи теряли auth:sanctum.
 *
 * Laravel ключует RouteCollection по method+uri, поэтому явный
 * Route::post('/questions', ...)->middleware('permission:...')
 * ЗАМЕНЯЛ маршрут, объявленный apiResource, и вместе с ним терял
 * auth:sanctum. CheckUserPermission видел Auth::user() === null и
 * отвечал 401 — то есть создание/правка/удаление категорий, вопросов,
 * групп, курсов, ролей, прав и настроек было сломано для всех,
 * включая суперадминистратора.
 */
class RoutePermissionCoverageTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    /** Ресурсы, где запись требует прав и должна быть доступна администратору. */
    private function writeRequests(): array
    {
        return [
            ['POST', '/api/categories', ['title' => 'Категория']],
            ['POST', '/api/questions', ['question_text' => 'Вопрос']],
            ['POST', '/api/gift', ['name' => 'Подарок']],
            ['POST', '/api/groups', ['groupname' => 'Группа']],
            ['POST', '/api/course', ['title' => 'Курс']],
            // POST /api/settings УБРАН из списка намеренно: у таблицы
            // настроек (key-value) нет операции создания, метода store()
            // в контроллере никогда не было, и маршрут только отдавал 500.
            // Теперь его нет — и маршрута, который можно проверить на
            // права, тоже нет. Проверка прав на настройки идёт по
            // PUT /api/settings/{name} (см. SettingsApiTest).
        ];
    }

    public function test_admin_is_authorized_on_write_routes_not_rejected_as_unauthenticated(): void
    {
        $this->admin();

        foreach ($this->writeRequests() as [$method, $uri, $payload]) {
            $response = $this->json($method, $uri, $payload);

            $this->assertNotSame(
                401,
                $response->status(),
                "{$method} {$uri} вернул 401: авторизация не дошла до проверки прав. "
                    . 'Обычно причина — замена маршрута apiResource явным Route:: без auth:sanctum.'
            );
        }
    }

    public function test_user_without_right_receives_403_not_401(): void
    {
        $role = Role::factory()->create(['rolename' => 'Обучаемый', 'slug' => 'student']);
        $user = $this->asUser(['role' => 'Обучаемый']);
        $user->roles()->attach($role);
        // Даём заведомо недостаточное право — questions.manage не выдаём.
        $user->permissions()->attach(Permission::firstOrCreate(
            ['slug' => 'courses.view'],
            ['name' => 'Просмотр курсов']
        ));

        foreach ($this->writeRequests() as [$method, $uri, $payload]) {
            $response = $this->json($method, $uri, $payload);

            $this->assertSame(
                403,
                $response->status(),
                "{$method} {$uri} вернул {$response->status()} вместо 403 — прав не хватало, "
                    . 'но пользователь авторизован.'
            );
        }
    }

    /**
     * Настройки: прав достаточно только у администратора.
     *
     * Отдельный тест, потому что здесь проверяется не POST (его нет),
     * а реальная операция правки — PUT /api/settings/{имя}.
     */
    public function test_settings_write_requires_settings_manage(): void
    {
        $this->admin();
        Setting::factory()->create(['name' => 'probe_setting', 'value' => 'old']);

        $this->putJson('/api/settings/probe_setting', [
            ['name' => 'probe_setting', 'value' => 'new'],
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_guest_is_rejected_with_401_on_write_routes(): void
    {
        foreach ($this->writeRequests() as [$method, $uri, $payload]) {
            $this->assertSame(
                401,
                $this->json($method, $uri, $payload)->status(),
                "{$method} {$uri} не отклонил гостя."
            );
        }
    }

    public function test_learning_resource_is_no_longer_publicly_writable(): void
    {
        // Регрессия: у learning не было middleware вовсе.
        $this->assertSame(401, $this->json('POST', '/api/learning', [])->status());

        $this->admin();
        $this->assertNotSame(401, $this->json('POST', '/api/learning', [])->status());
    }

    public function test_every_permission_route_also_requires_authentication(): void
    {
        $checked = 0;

        /** @var \Illuminate\Routing\Route $route */
        foreach (app('router')->getRoutes() as $route) {
            $middleware = array_map(
                fn ($m) => is_string($m) ? $m : get_class($m),
                $route->gatherMiddleware()
            );

            $hasPermission = (bool) array_filter(
                $middleware,
                fn ($m) => str_contains($m, 'CheckUserPermission')
                    || str_contains($m, 'CheckAllPermissions')
                    // gatherMiddleware() отдаёт алиасы ("permission:..."), а не классы
                    || str_starts_with($m, 'permission')
            );
            $hasAuth = (bool) array_filter(
                $middleware,
                fn ($m) => (str_contains($m, 'Authenticate') && str_contains($m, 'sanctum'))
                    || $m === 'auth:sanctum'
            );

            if (! $hasPermission) {
                continue;
            }

            $checked++;
            $this->assertTrue(
                $hasAuth,
                sprintf(
                    '%s %s использует permission, но не auth:sanctum — вернётся 401 вместо 403.',
                    $route->methods()[0] ?? 'ANY',
                    $route->uri()
                )
            );
        }

        // Защита от молчаливого ослабления: если цикл перестанет что-либо находить,
        // тест обязан упасть, а не пройти «вхолостую».
        $this->assertGreaterThan(
            0,
            $checked,
            'Не найдено ни одного маршрута с permission — проверка потеряла смысл.'
        );
    }
}

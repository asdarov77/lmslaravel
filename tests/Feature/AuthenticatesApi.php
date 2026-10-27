<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Общие хелперы авторизации для feature-тестов API.
 *
 * Раньше asUser() был приватной копией в каждом тест-классе, из-за чего
 * новый тест не мог его переиспользовать.
 */
trait AuthenticatesApi
{
    /**
     * Войти под новым пользователем.
     *
     * Сбрасываем уже разрешённых guard'ов: контейнер приложения в тестах
     * один на все запросы теста, и guard, разрешивший пользователя в
     * ПРЕДЫДУЩЕМ запросе, остаётся закэшированным. Из-за этого запрос с
     * корректным токеном нового пользователя проверялся под старым —
     * например, инструктор с правом exams.manage получал 403, потому что
     * проверялся обучаемый из предыдущего вызова asUser().
     *
     * В боевом приложении этого нет: контейнер создаётся заново на каждый
     * запрос (php artisan serve / php-fpm, Octane не используется).
     */
    protected function asUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $token = $user->createToken('t')->plainTextToken;

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    /**
     * Войти под СУЩЕСТВУЮЩИМ пользователем.
     *
     * Нужно, когда в одном тесте под одним и тем же пользователем идут
     * несколько запросов: asUser() создаёт нового, и передать ему чужой id
     * нельзя — фабрика упрётся в нарушение уникальности.
     */
    protected function asExistingUser(User $user): User
    {
        $token = $user->createToken('t')->plainTextToken;

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $user;
    }

    protected function admin(): User
    {
        return $this->asUser(['role' => 'Администратор']);
    }

    protected function assertEnvelope(array $json): void
    {
        foreach (['success', 'data', 'error', 'meta'] as $key) {
            $this->assertArrayHasKey($key, $json, "В конверте нет ключа {$key}");
        }
    }
}

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
    protected function asUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $token = $user->createToken('t')->plainTextToken;
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

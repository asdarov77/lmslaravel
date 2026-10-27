<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Доступ к маршруту POST /api/clear-database.
 *
 * Регрессия, которую закрывает тест: маршрут был доступен любому
 * авторизованному пользователю, включая студента — один клик по кнопке
 * стирал весь импортированный контент. Теперь нужен явный
 * permission:system.maintenance.
 */
class ClearDatabasePermissionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function гость_не_может_очистить_базу(): void
    {
        $this->postJson('/api/clear-database')->assertUnauthorized();
    }

    /** @test */
    public function пользователь_без_права_обслуживания_получает_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));

        $this->postJson('/api/clear-database')->assertForbidden();
    }

    /** @test */
    public function администратор_может_очистить_базу(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/clear-database')->assertOk();
    }
}
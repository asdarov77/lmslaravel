<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Несуществующие пути /api/*.
 *
 * Регрессия: fallback для SPA объявлен в группе web, и запрос к
 * /api/чего-то-нет доходил до StartSession и AuthenticateSession. Guard
 * по умолчанию — sanctum, у него нет viaRemember, поэтому неизвестный
 * путь отвечал 500. Клиент не мог отличить «метод не тот» от «сервер
 * сломан», а логи засорялись исключением на каждом опечатке в URL.
 */
class ApiFallbackTest extends TestCase
{
    public function test_unknown_api_path_returns_json_404(): void
    {
        $response = $this->getJson('/api/definitely-missing-path');

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error', 'Маршрут не найден');
    }

    public function test_unsupported_method_returns_405_with_allowed_list(): void
    {
        // GET /api/city объявлен только POST: ответ должен быть 405 со
        // списком методов, а не 404, иначе клиент не понимает, что не так.
        $response = $this->getJson('/api/city');

        $response->assertStatus(405);
        $this->assertContains('POST', $response->json('meta.allowed'));
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_fio(): void
    {
        $user = User::factory()->create([
            'surname' => 'Иванов',
            'name' => 'Иван',
            'patronymic' => 'Иванович',
            'password' => bcrypt('123')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'fio' => 'Иванов Иван Иванович',
            'password' => '123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'token',
                         'user' => [
                             'id',
                             'fio',
                             'email',
                             'role'
                         ]
                     ]
                 ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('123')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'fio' => $user->fio,
            'password' => 'wrong_password'
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'id' => $user->id,
                         'fio' => $user->fio
                     ]
                 ]);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }
}

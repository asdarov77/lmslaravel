<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_user()
    {
        $user = User::factory()->create([
            'fio' => 'Tester',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'fio' => 'Tester',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['success','data'=>['token','user'=>['id','fio']],'error','meta']);
    }

    public function test_login_fails_with_wrong_password()
    {
        $user = User::factory()->create([
            'fio' => 'Tester2',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'fio' => 'Tester2',
            'password' => 'bad',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success'=>false]);
    }
}



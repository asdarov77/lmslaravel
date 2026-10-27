<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_example()
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
                         ->get('/api/courses/');
        $response->assertStatus(200);
    }

    /**
     * Список курсов закрыт авторизацией.
     *
     * @return void
     */
    public function test_courses_list_requires_auth()
    {
        $this->get('/api/courses/')->assertStatus(401);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MySessionsTest extends TestCase
{
    use RefreshDatabase;

    private function authUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_no_extra_sessions_when_alone(): void
    {
        $user = $this->authUser();
        $this->assertFalse($user->tokens()->exists());

        $res = $this->getJson('/api/my/sessions')->assertOk();
        $this->assertSame(0, $res->json('data.extra_sessions'));
        $this->assertSame([], $res->json('data.sessions'));
    }

    public function test_extra_sessions_counted_without_current(): void
    {
        $user = $this->authUser();
        $user->createToken('mobile');
        $user->createToken('tablet');

        $res = $this->getJson('/api/my/sessions')->assertOk();
        $this->assertSame(2, $res->json('data.extra_sessions'));

        $ids = collect($res->json('data.sessions'))->pluck('name')->all();
        $this->assertSame(['mobile', 'tablet'], $ids);
    }

    public function test_logout_works_and_returns_extra_flag(): void
    {
        $user = $this->authUser();
        $user->createToken('mobile');

        $res = $this->getJson('/api/logout')->assertOk();
        $this->assertSame(1, $res->json('data.extra_sessions'));
    }
}

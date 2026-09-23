<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_index_returns_paginated_list()
    {
        $user = User::factory()->create([
            'fio' => 'Admin',
            'password' => Hash::make('123456'),
        ]);
        Course::factory()->count(3)->create();

        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
                         ->getJson('/api/course');

        $response->assertStatus(200)
                 ->assertJsonStructure(['success','data','meta'=>['pagination'=>['page','perPage','total','totalPages']],'error']);
    }
}



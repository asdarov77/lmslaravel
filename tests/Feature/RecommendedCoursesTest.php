<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Feature\AuthenticatesApi;

/**
 * Рекомендованные курсы (/api/my/recommended).
 *
 * Рекомендация = курс плана группы, который прогресс не закрыл:
 * не пройдено 100% уроков и экзамены не сданы — файл не просто
 * берётся из каталога, а берётся по прогрессу.
 */
class RecommendedCoursesTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/my/recommended')->assertUnauthorized();
    }

    public function test_returns_unfinished_courses_of_own_plan(): void
    {
        $group = \App\Models\Group::factory()->create();
        $trainee = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);

        $course = Course::factory()->create(['title' => 'Недоделанный']);
        Aukstructure::factory()->count(4)->create(['course_id' => $course->id]);

        Group2learning::factory()->create(['course_id' => $course->id, 'group_id' => $group->id]);

        // Пройден 1 из 4 уроков — 25%.
        LessonProgress::create([
            'user_id' => $trainee->id,
            'course_id' => $course->id,
            'lesson_id' => Aukstructure::firstOrFail()->id,
            'percent' => 25,
            'last_file' => 'f1',
        ]);

        $this->asExistingUser($trainee);

        $data = $this->getJson('/api/my/recommended')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Недоделанный', $data[0]['title']);
        $this->assertSame(25, $data[0]['percent']);
        $this->assertTrue($data[0]['recommended']);
    }

    public function test_completed_course_is_not_recommended(): void
    {
        $group = \App\Models\Group::factory()->create();
        $trainee = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);

        $course = Course::factory()->create(['title' => 'Закрытый']);
        Group2learning::factory()->create(['course_id' => $course->id, 'group_id' => $group->id]);

        LessonProgress::create([
            'user_id' => $trainee->id,
            'course_id' => $course->id,
            'lesson_id' => Aukstructure::factory()->create(['course_id' => $course->id])->id,
            'percent' => 100,
            'last_file' => 'f1',
        ]);

        $this->asExistingUser($trainee);
        $this->assertSame([], $this->getJson('/api/my/recommended')->assertOk()->json('data'));
    }

    public function test_trainee_without_group_gets_empty_list(): void
    {
        $trainee = User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]);
        $this->asExistingUser($trainee);

        $this->assertSame([], $this->getJson('/api/my/recommended')->assertOk()->json('data'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Прогресс по урокам курса (/api/my/progress).
 *
 * Проверяется не только «запись работает», но и главное свойство этой
 * таблицы: прогресс принадлежит пользователю. Чужой курс нельзя ни
 * посмотреть, ни накрутить себе процент, а урок из другого курса
 * нельзя приписать своему.
 */
class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attrs = []): Course
    {
        return Course::create(array_merge([
            'title' => 'Курс для прогресса',
            'short_description' => 'Описание',
            'long_description' => 'Описание',
            'path' => 'course-progress',
            'visible' => true,
        ], $attrs));
    }

    private function lesson(Course $course, ?int $parent = null): Aukstructure
    {
        return Aukstructure::create([
            'course_id' => $course->id,
            'parent_id' => $parent,
            'title' => 'Урок',
            'type' => 1,
            'identifier' => 'L-' . $course->id . '-' . (string) Aukstructure::count(),
        ]);
    }

    /**
     * Зачисляет пользователя на курс.
     *
     * Доступ проверяется не по самому курсу, а через Group2learning:
     * группа пользователя должна быть записана на этот курс. Иначе
     * тест проходил бы по одному лишь group_id.
     */
    private function enroll(User $user, Course $course): User
    {
        $group = \App\Models\Group::factory()->create();
        $user->group_id = $group->id;
        $user->save();

        $record = new \App\Models\Group2learning([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);
        // study_from/study_to не в fillable: у модели их заполняет
        // только сама, поэтому здесь ставим напрямую.
        $record->study_from = now()->subDay();
        $record->study_to = now()->addMonth();
        $record->save();

        return $user;
    }

    public function test_guest_is_rejected(): void
    {
        $course = $this->course();

        $this->getJson("/api/my/progress/{$course->id}")->assertUnauthorized();
        $this->postJson('/api/my/progress', [
            'course_id' => $course->id,
            'lesson_id' => $this->lesson($course)->id,
        ])->assertUnauthorized();
    }

    public function test_enrolled_user_sees_all_lessons_with_zero_progress(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $this->lesson($course);
        $this->lesson($course);

        $this->enroll($user, $course);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/progress/{$course->id}")
            ->assertOk();

        $response->assertJsonPath('data.percent', 0);
        $response->assertJsonPath('data.total', 2);
        $response->assertJsonPath('data.completed', 0);
        $this->assertSame([0, 0], array_column($response->json('data.lessons'), 'percent'));
    }

    public function test_progress_is_saved_and_percentage_is_averaged(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $this->enroll($user, $course);

        $first = $this->lesson($course);
        $second = $this->lesson($course);

        $this->actingAs($user, 'sanctum')->postJson('/api/my/progress', [
            'course_id' => $course->id,
            'lesson_id' => $first->id,
            'percent' => 100,
            'last_file' => '1.1 Глава.html',
        ])->assertOk()->assertJsonPath('data.completed', true);

        $this->actingAs($user, 'sanctum')->postJson('/api/my/progress', [
            'course_id' => $course->id,
            'lesson_id' => $second->id,
            'percent' => 40,
        ])->assertOk();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/progress/{$course->id}")
            ->assertOk();

        // (100 + 40) / 2 = 70, а не 100 из-за одного открытого урока.
        $response->assertJsonPath('data.percent', 70);
        $response->assertJsonPath('data.completed', 1);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $first->id,
            'last_file' => '1.1 Глава.html',
        ]);
    }

    public function test_percent_never_goes_backwards(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $this->enroll($user, $course);
        $lesson = $this->lesson($course);

        $this->actingAs($user, 'sanctum')->postJson('/api/my/progress', [
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => 80,
        ])->assertOk();

        // Перечитывание не должно отменять уже набранный процент.
        $this->actingAs($user, 'sanctum')->postJson('/api/my/progress', [
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => 10,
        ])->assertOk()->assertJsonPath('data.percent', 80);

        $this->assertSame(1, LessonProgress::where('user_id', $user->id)->count());
    }

    public function test_stranger_cannot_read_or_write_progress(): void
    {
        $owner = User::factory()->create();
        $course = $this->course();
        $this->enroll($owner, $course);
        $lesson = $this->lesson($course);

        // Роль из фабрики по умолчанию — «Администратор», а значит
        // courses.manage и доступ к любому курсу. Проверять 403 нужно
        // на обычном обучаемом из другой группы.
        $stranger = User::factory()->create(['role' => 'Обучаемый']);
        $stranger->group_id = \App\Models\Group::factory()->create()->id;
        $stranger->save();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/my/progress/{$course->id}")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/my/progress', [
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'percent' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_lesson_from_another_course_is_rejected(): void
    {
        $user = User::factory()->create();

        $mine = $this->course();
        $other = $this->course(['title' => 'Чужой курс']);
        $this->enroll($user, $mine);
        $foreignLesson = $this->lesson($other);

        // Иначе можно было бы «завершить» чужой раздел и получить
        // 100% своего курса чужими кнопками.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my/progress', [
                'course_id' => $mine->id,
                'lesson_id' => $foreignLesson->id,
                'percent' => 100,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_progress_can_be_reset(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $this->enroll($user, $course);
        $lesson = $this->lesson($course);

        $this->actingAs($user, 'sanctum')->postJson('/api/my/progress', [
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => 100,
        ])->assertOk();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/my/progress/{$lesson->id}")
            ->assertOk();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    /**
     * «Продолжить обучение» должно вернуть к последнему открытому
     * уроку, а не просто к первому курсу плана.
     */
    public function test_dashboard_continue_points_to_last_open_lesson(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        $this->enroll($user, $course);

        $firstLesson = $this->lesson($course);
        $secondLesson = $this->lesson($course);

        \App\Models\LessonProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $firstLesson->id,
            'percent' => 100,
            'completed_at' => Carbon::now(),
            'last_viewed_at' => Carbon::now()->subDay(),
            'updated_at' => Carbon::now()->subDay(),
        ]);

        // Последний просмотренный урок — второй, и он не завершён.
        \App\Models\LessonProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $secondLesson->id,
            'percent' => 30,
            'last_file' => '2.2 Разбор.html',
            'last_viewed_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $continue = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/dashboard')
            ->assertOk()
            ->json('data.continue');

        $this->assertSame($course->id, $continue['course_id']);
        $this->assertSame($secondLesson->id, $continue['resume_lesson_id']);
        $this->assertSame('2.2 Разбор.html', $continue['resume_file']);
        $this->assertSame(65, $continue['percent']);
    }

    /**
     * Завершённый урок не должен предлагаться как точка продолжения.
     */
    public function test_dashboard_does_not_resume_finished_course(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        $this->enroll($user, $course);
        $lesson = $this->lesson($course);

        \App\Models\LessonProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => 100,
            'completed_at' => Carbon::now(),
            'last_viewed_at' => Carbon::now(),
        ]);

        // Период курса завершён — продолжать нечего.
        \App\Models\Group2learning::where('group_id', $user->group_id)->update([
            'study_from' => Carbon::now()->subMonths(2)->toDateString(),
            'study_to' => Carbon::now()->subDay()->toDateString(),
        ]);

        $continue = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/dashboard')
            ->assertOk()
            ->json('data.continue');

        $this->assertNull($continue, 'завершённый курс не предлагается для продолжения');
    }

    public function test_percent_above_hundred_is_rejected(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $this->enroll($user, $course);
        $lesson = $this->lesson($course);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my/progress', [
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'percent' => 150,
            ])
            ->assertStatus(422);
    }
}
<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сертификаты об окончании курса.
 *
 * Главное, что тут проверяется, — не «страница рисуется», а условия
 * выдачи. Прежний вариант идеи (открытый список всех пройденных курсов)
 * позволял напечатать сертификат по курсу, который просто закончился по
 * датам. Здесь нужно закрыть все уроки И сдать все экзамены.
 */
class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $title = 'Курс'): Course
    {
        return Course::create([
            'title' => $title,
            'short_description' => 'Описание',
            'long_description' => 'Описание',
            'path' => 'cert-' . \Illuminate\Support\Str::slug($title),
            'visible' => true,
        ]);
    }

    private function enrolled(User $user, Course $course, bool $finished = false): void
    {
        $group = \App\Models\Group::factory()->create();
        $user->group_id = $group->id;
        $user->save();

        $record = new Group2learning([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);
        $record->study_from = now()->subMonth();
        $record->study_to = $finished ? now()->subDay() : now()->addMonth();
        $record->save();
    }

    private function lesson(Course $course, int $index = 1)
    {
        return \App\Models\Aukstructure::create([
            'course_id' => $course->id,
            'parent_id' => null,
            'title' => 'Урок ' . $index,
            'type' => 1,
            'identifier' => "CERT-{$course->id}-{$index}",
        ]);
    }

    private function completeLessons(User $user, Course $course, int $count, bool $all = true): void
    {
        foreach (range(1, $count) as $index) {
            $lesson = $this->lesson($course, $index);
            $percent = $all ? 100 : 30;

            LessonProgress::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'percent' => $percent,
                'completed_at' => $percent >= 100 ? now() : null,
            ]);
        }
    }

    public function test_guest_cannot_list_certificates(): void
    {
        $this->getJson('/api/my/certificates')->assertUnauthorized();
    }

    public function test_certificate_is_available_when_all_lessons_are_closed(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        $this->enrolled($user, $course);
        $this->completeLessons($user, $course, 2);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/certificates')
            ->assertOk()
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.lessons_done', 2)
            ->assertJsonPath('meta.available', 1);
    }

    public function test_finished_period_alone_does_not_issue_certificate(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        // Курс закончился по датам, но уроки не закрыты.
        $this->enrolled($user, $course, finished: true);
        $this->completeLessons($user, $course, 2, all: false);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/certificates')
            ->assertOk()
            ->assertJsonPath('data.0.available', false);

        // И сам сертификат отдавать нельзя.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->assertForbidden();
    }

    public function test_unpassed_exam_blocks_certificate(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        $this->enrolled($user, $course);
        $this->completeLessons($user, $course, 1);

        $exam = Exam::create([
            'title' => 'Экзамен',
            'course_id' => $course->id,
            'user_id' => $user->id,
            'pass_percent' => 70,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/certificates')
            ->assertOk()
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.0.exams_total', 1)
            ->assertJsonPath('data.0.exams_passed', 0);

        // Неудачная попытка не закрывает экзамен.
        ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'total_count' => 10,
            'correct_count' => 4,
            'score' => 0.4,
            'passed' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/certificates')
            ->assertJsonPath('data.0.available', false);

        // Успешная — закрывает.
        ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'total_count' => 10,
            'correct_count' => 9,
            'score' => 0.9,
            'passed' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/my/certificates')
            ->assertOk()
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.0.exams_passed', 1);
    }

    public function test_certificate_data_contains_fio_and_code(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Петров Пётр']);
        $course = $this->course('Авиационная безопасность');
        $this->enrolled($user, $course);
        $this->completeLessons($user, $course, 1);

        $data = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame('Петров Пётр', $data['fio']);
        $this->assertSame('Авиационная безопасность', $data['course']);
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{18}$/', $data['code']);
    }

    public function test_code_verifies_without_authentication(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Сидоров Иван']);
        $course = $this->course('Проверяемый курс');
        $this->enrolled($user, $course);
        $this->completeLessons($user, $course, 1);

        $code = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->json('data.code');

        // Код проверяет получатель, у которого нет аккаунта.
        $this->getJson("/api/certificates/verify/{$code}")
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.fio', 'Сидоров Иван')
            ->assertJsonPath('data.course', 'Проверяемый курс');
    }

    /**
     * Код всегда одной длины.
     *
     * Регрессия: подпись считалась как hexdec от 8 hex-символов, это
     * число в base36 занимало 7 символов, и код выходил длиной 19-20.
     * Маршрут проверки объявлен с регуляркой {18}, поэтому «правильный»
     * код не находил маршрут и проваливался в SPA-fallback.
     */
    public function test_code_always_has_fixed_length(): void
    {
        $teacher = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        $this->enrolled($teacher, $course);
        $this->completeLessons($teacher, $course, 1);

        $code = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->json('data.code');

        $this->assertSame(18, strlen($code), "код должен быть ровно 18 символов, получен «{$code}»");
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{18}$/', $code);

        // И он обязан проходить по маршруту проверки, а не проваливаться
        // в SPA-fallback.
        $this->getJson("/api/certificates/verify/{$code}")->assertOk();
    }

    public function test_forged_code_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Сидоров Иван']);
        $course = $this->course();
        $this->enrolled($user, $course);
        $this->completeLessons($user, $course, 1);

        $code = $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->json('data.code');

        // Подмена одной цифры в подписи делает код недействительным.
        $forged = substr($code, 0, 17) . ($code[17] === 'Z' ? 'Y' : 'Z');

        $this->getJson("/api/certificates/verify/{$forged}")->assertNotFound();
        $this->getJson('/api/certificates/verify/AAAAAAAAAAAAAAAAAA')->assertNotFound();
    }

    public function test_stranger_cannot_get_certificate(): void
    {
        $owner = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course();
        $this->enrolled($owner, $course);
        $this->completeLessons($owner, $course, 1);

        $stranger = User::factory()->create(['role' => 'Обучаемый']);
        $stranger->group_id = \App\Models\Group::factory()->create()->id;
        $stranger->save();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->assertNotFound();
    }

    public function test_course_without_lessons_issues_certificate_after_exams(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $course = $this->course('Только экзамен');
        $this->enrolled($user, $course);
        // Уроков нет — блокировать выдачу нечем.

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.lessons_total', 0);
    }
}
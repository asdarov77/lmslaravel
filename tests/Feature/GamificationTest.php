<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Models\PointEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Gamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Геймификация: XP, бейджи, streak, лидерборд.
 */
class GamificationTest extends TestCase
{
    use RefreshDatabase;

    private function trainee(string $fio = 'Петров Пётр', ?Group $group = null): User
    {
        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => $fio]);

        if ($group) {
            $user->group_id = $group->id;
            $user->save();
        }

        return $user;
    }

    private function course(string $path = 'gam'): Course
    {
        return Course::create([
            'title' => 'Курс',
            'short_description' => 'Описание',
            'long_description' => 'Описание',
            'path' => $path,
            'visible' => true,
        ]);
    }

    private function enrolled(User $user, Course $course, bool $finished = false): void
    {
        if (! $user->group_id) {
            $group = Group::factory()->create();
            $user->group_id = $group->id;
            $user->save();
        }

        $record = new Group2learning(['group_id' => $user->group_id, 'course_id' => $course->id]);
        $record->study_from = now()->subMonth();
        $record->study_to = $finished ? now()->subDay() : now()->addMonth();
        $record->save();
    }

    private function lesson(Course $course, int $index = 1): Aukstructure
    {
        return Aukstructure::create([
            'course_id' => $course->id,
            'parent_id' => null,
            'title' => 'Урок ' . $index,
            'type' => 1,
            'identifier' => "GAM-{$course->id}-{$index}",
        ]);
    }

    private function completeLesson(User $user, Course $course, Aukstructure $lesson, int $percent = 100): void
    {
        LessonProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => $percent,
            'completed_at' => $percent >= 100 ? now() : null,
        ]);
    }

    private function passedExam(User $user, Course $course): ExamAttempt
    {
        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Экзамен',
            'passing_score' => 0.5,
        ]);

        return ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'total_count' => 10,
            'correct_count' => 8,
            'score' => 0.8,
            'passed' => true,
            'submitted_at' => now(),
        ]);
    }

    // --- начисление -------------------------------------------------------

    public function test_award_adds_points_once(): void
    {
        $user = $this->trainee();

        $this->assertTrue(Gamification::award($user, 'lesson_completed', 'lesson:1'));
        $this->assertFalse(Gamification::award($user, 'lesson_completed', 'lesson:1'));

        $this->assertSame(10, Gamification::totalXp($user));
        $this->assertSame(1, PointEvent::where('user_id', $user->id)->count());
    }

    public function test_unknown_kind_gives_no_points_and_no_journal_row(): void
    {
        $user = $this->trainee();

        $this->assertFalse(Gamification::award($user, 'made_up_kind', 'x:1'));
        $this->assertSame(0, Gamification::totalXp($user));
        // Нули в журнале копились бы и делали сумму нечитаемой.
        $this->assertSame(0, PointEvent::count());
    }

    public function test_progress_endpoint_awards_lesson_xp_once(): void
    {
        $user = $this->trainee();
        $course = $this->course();
        $this->enrolled($user, $course);
        $lesson = $this->lesson($course);

        // Два запроса: первый закрывает урок, второй — перечитывание.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my/progress', ['course_id' => $course->id, 'lesson_id' => $lesson->id, 'percent' => 100])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my/progress', ['course_id' => $course->id, 'lesson_id' => $lesson->id, 'percent' => 100])
            ->assertOk();

        $this->assertSame(10, Gamification::totalXp($user));
    }

    // --- уровень ----------------------------------------------------------

    public function test_level_grows_quadratically(): void
    {
        $this->assertSame(1, Gamification::level(0));
        $this->assertSame(1, Gamification::level(99));
        $this->assertSame(2, Gamification::level(100));
        $this->assertSame(3, Gamification::level(400));

        // «Сколько до следующего уровня» должно сходиться с формулой,
        // иначе кольцо прогресса показывало бы ерунду.
        $progress = Gamification::levelProgress(150);
        $this->assertSame(2, $progress['level']);
        $this->assertSame(250, $progress['need']);
        $this->assertSame(400, $progress['next']);
    }

    // --- streak -----------------------------------------------------------

    public function test_streak_counts_consecutive_days(): void
    {
        $user = $this->trainee();

        Gamification::award($user, 'lesson_completed', 's:1', null, now()->subDays(3));
        Gamification::award($user, 'lesson_completed', 's:2', null, now()->subDays(2));
        Gamification::award($user, 'lesson_completed', 's:3', null, now()->subDay());
        Gamification::award($user, 'lesson_completed', 's:4', null, now());

        $this->assertSame(4, Gamification::streak($user));
    }

    public function test_streak_survives_a_day_without_activity(): void
    {
        $user = $this->trainee();

        Gamification::award($user, 'lesson_completed', 's:1', null, now()->subDay());
        Gamification::award($user, 'lesson_completed', 's:2', null, now());

        $this->assertSame(2, Gamification::streak($user));
    }

    public function test_streak_is_zero_after_two_idle_days(): void
    {
        $user = $this->trainee();

        Gamification::award($user, 'lesson_completed', 's:1', null, now()->subDays(4));
        Gamification::award($user, 'lesson_completed', 's:2', null, now()->subDays(3));

        $this->assertSame(0, Gamification::streak($user));
    }

    public function test_two_events_in_one_day_count_as_one_day(): void
    {
        $user = $this->trainee();

        Gamification::award($user, 'lesson_completed', 's:1', null, now());
        Gamification::award($user, 'exam_passed', 's:2', null, now());

        $this->assertSame(1, Gamification::streak($user));
    }

    // --- бейджи -----------------------------------------------------------

    public function test_badge_is_granted_when_condition_is_met(): void
    {
        $user = $this->trainee();
        $course = $this->course();
        $this->enrolled($user, $course);

        $this->completeLesson($user, $course, $this->lesson($course));

        $gallery = Gamification::badgeGallery($user);
        $byKey = collect($gallery)->keyBy('key');

        $this->assertTrue($byKey['first_lesson']['granted']);
        $this->assertNotNull($byKey['first_lesson']['awarded_at']);
        // Условие не выполнено — бейдж не выдан, но виден с прогрессом:
        // один закрытый урок из двадцати пяти.
        $this->assertFalse($byKey['lessons_25']['granted']);
        $this->assertSame(1, $byKey['lessons_25']['progress']['current']);
        $this->assertSame(25, $byKey['lessons_25']['progress']['total']);
        $this->assertSame(4, $byKey['lessons_25']['progress']['percent']);
    }

    public function test_badge_requires_real_data_not_journal_entry(): void
    {
        $user = $this->trainee();

        // Запись в журнале без реально закрытого урока не должна
        // выдавать бейдж: иначе его можно было бы «нафармить».
        Gamification::award($user, 'lesson_completed', 'fake:1');

        $gallery = collect(Gamification::badgeGallery($user))->keyBy('key');
        $this->assertFalse($gallery['first_lesson']['granted']);
    }

    public function test_streak_badge_appears_after_a_week(): void
    {
        $user = $this->trainee();

        for ($i = 0; $i < 7; $i++) {
            Gamification::award($user, 'lesson_completed', "d:{$i}", null, now()->subDays($i));
        }

        $gallery = collect(Gamification::badgeGallery($user))->keyBy('key');
        $this->assertSame(7, Gamification::streak($user));
        $this->assertTrue($gallery['streak_7']['granted']);
        $this->assertFalse($gallery['streak_30']['granted']);
    }

    public function test_badge_is_withdrawn_when_progress_is_recalculated_back(): void
    {
        $user = $this->trainee();
        $course = $this->course();
        $this->enrolled($user, $course);
        $lesson = $this->lesson($course);

        $this->completeLesson($user, $course, $lesson);
        $this->assertTrue(collect(Gamification::badgeGallery($user))->keyBy('key')['first_lesson']['granted']);

        // Пересчёт прогресса убрал закрытие — бейдж должен исчезнуть,
        // иначе галерея показывала бы достижение, которого нет.
        LessonProgress::where('user_id', $user->id)->update(['percent' => 40, 'completed_at' => null]);

        $this->assertFalse(collect(Gamification::badgeGallery($user))->keyBy('key')['first_lesson']['granted']);
        $this->assertSame(0, UserBadge::where('user_id', $user->id)->count());
    }

    public function test_badge_progress_percent_is_capped(): void
    {
        $user = $this->trainee();

        // 525 XP: пять уроков, десять экзаменов, пять работ и два
        // закрытия курса — порог 500 пройден, а процент обязан
        // упереться в 100, а не показать 105%.
        $total = 0;

        foreach (range(1, 5) as $i) {
            $total += Gamification::award($user, 'lesson_completed', "l:{$i}") ? 10 : 0;
        }

        foreach (range(1, 10) as $i) {
            $total += Gamification::award($user, 'exam_passed', "e:{$i}") ? 30 : 0;
        }

        foreach (range(1, 5) as $i) {
            $total += Gamification::award($user, 'assignment_submitted', "a:{$i}") ? 15 : 0;
        }

        foreach (range(1, 2) as $i) {
            $total += Gamification::award($user, 'course_completed', "c:{$i}") ? 50 : 0;
        }

        $this->assertSame(525, $total);
        $this->assertSame(525, Gamification::totalXp($user));

        $gallery = collect(Gamification::badgeGallery($user))->keyBy('key');
        $this->assertTrue($gallery['xp_500']['granted']);
        $this->assertSame(100, $gallery['xp_500']['progress']['percent']);
    }

    public function test_gallery_lists_every_configured_badge(): void
    {
        $user = $this->trainee();

        $gallery = Gamification::badgeGallery($user);
        $expected = array_keys((array) config('gamification.badges'));

        $this->assertSame($expected, collect($gallery)->pluck('key')->all());
    }

    // --- лидерборд --------------------------------------------------------

    public function test_leaderboard_ranks_group_members_by_xp(): void
    {
        $group = Group::factory()->create();

        $top = $this->trainee('Первый', $group);
        $second = $this->trainee('Второй', $group);
        $zero = $this->trainee('Третий', $group);

        Gamification::award($top, 'exam_passed', 'l:1');
        Gamification::award($second, 'lesson_completed', 'l:2');

        $board = Gamification::leaderboard($top);

        $this->assertSame('Первый', $board[0]['fio']);
        $this->assertSame(1, $board[0]['rank']);
        $this->assertTrue($board[0]['is_me']);
        $this->assertSame('Второй', $board[1]['fio']);
        $this->assertSame('Третий', $board[2]['fio']);
        $this->assertSame(0, $board[2]['xp']);
    }

    public function test_leaderboard_excludes_other_groups(): void
    {
        $myGroup = Group::factory()->create();
        $otherGroup = Group::factory()->create();

        $me = $this->trainee('Свой', $myGroup);
        $stranger = $this->trainee('Чужой', $otherGroup);
        Gamification::award($stranger, 'exam_passed', 'other:1');

        $board = Gamification::leaderboard($me);

        $this->assertNotContains('Чужой', collect($board)->pluck('fio')->all());
    }

    public function test_leaderboard_is_empty_without_group(): void
    {
        $user = $this->trainee('Без группы');

        $this->assertSame([], Gamification::leaderboard($user));
    }

    public function test_leaderboard_order_is_stable_on_equal_xp(): void
    {
        $group = Group::factory()->create();
        $me = $this->trainee('Ааа', $group);
        $other = $this->trainee('Яяя', $group);

        Gamification::award($me, 'lesson_completed', 's:1');
        Gamification::award($other, 'lesson_completed', 's:2');

        // При равном XP порядок решает ФИО, а не порядок в базе:
        // иначе строки прыгали бы между обновлениями страницы.
        $this->assertSame('Ааа', Gamification::leaderboard($me)[0]['fio']);
    }

    // --- эндпоинт ---------------------------------------------------------

    public function test_me_returns_full_summary(): void
    {
        $user = $this->trainee('Петров Пётр');
        Gamification::award($user, 'lesson_completed', 'api:1');

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gamification/me')
            ->assertOk()
            ->json('data');

        $this->assertSame(10, $data['xp']);
        $this->assertSame(1, $data['level']['level']);
        $this->assertSame(1, $data['streak']);
        $this->assertCount(35, $data['calendar']);
        $this->assertCount(count((array) config('gamification.badges')), $data['badges']);
    }

    public function test_calendar_marks_only_active_days(): void
    {
        $user = $this->trainee();
        Gamification::award($user, 'lesson_completed', 'c:1', null, now()->subDays(2));

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gamification/me')
            ->json('data');

        $today = collect($data['calendar'])->firstWhere('date', now()->toDateString());
        $twoDaysAgo = collect($data['calendar'])->firstWhere('date', now()->subDays(2)->toDateString());
        $yesterday = collect($data['calendar'])->firstWhere('date', now()->subDay()->toDateString());

        $this->assertSame(0, $today['points']);
        $this->assertSame(10, $twoDaysAgo['points']);
        $this->assertSame(0, $yesterday['points']);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/gamification/me')->assertUnauthorized();
    }

    public function test_completed_courses_counts_only_fully_closed(): void
    {
        $course = $this->course('closed');
        $user = $this->trainee();
        $this->enrolled($user, $course, finished: true);

        $lessonOne = $this->lesson($course, 1);
        $lessonTwo = $this->lesson($course, 2);

        $this->completeLesson($user, $course, $lessonOne);
        $this->assertSame(0, Gamification::completedCourses($user));

        $this->completeLesson($user, $course, $lessonTwo);
        $this->assertSame(1, Gamification::completedCourses($user));
    }

    public function test_completed_course_without_lessons_counts_after_exam(): void
    {
        $course = $this->course('examonly');
        $user = $this->trainee();
        $this->enrolled($user, $course, finished: true);

        $this->passedExam($user, $course);

        // Закрывать такой курс нечего: единственное условие — сданный
        // экзамен, иначе он не закрылся бы никогда.
        $this->assertSame(1, Gamification::completedCourses($user));
    }
}
<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Объявления.
 *
 * Главное свойство — видимость. Объявление для группы А не должно
 * всплыть в ленте группы Б: это и главная причина, по которой объявления
 * вообще делают адресными. Остальные проверки — сроки и права публикации.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $editor = User::factory()->create(['fio' => 'Редактор Редакторович', 'role' => 'Инструктор']);
        $role = Role::where('slug', 'instructor')->first();

        if ($role !== null) {
            RoleUser::create(['user_id' => $editor->id, 'role_id' => $role->id]);
        }

        return $editor;
    }

    private function trainee(string $fio = 'Обучаемый', ?Group $group = null): User
    {
        return User::factory()->create([
            'fio' => $fio,
            'role' => 'Обучаемый',
            'group_id' => ($group ?? Group::factory()->create())->id,
        ]);
    }

    public function test_guest_cannot_read_announcements(): void
    {
        $this->getJson('/api/announcements')->assertUnauthorized();
    }

    public function test_everyone_sees_announcement_for_all(): void
    {
        Announcement::create([
            'title' => 'Плановая проверка',
            'body' => 'В четверг занятий не будет',
            'audience_all' => true,
        ]);

        $this->actingAs($this->trainee(), 'sanctum')
            ->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Плановая проверка');
    }

    public function test_group_announcement_is_hidden_from_other_group(): void
    {
        $groupA = Group::factory()->create(['groupname' => 'Группа А']);
        $groupB = Group::factory()->create(['groupname' => 'Группа Б']);

        Announcement::create([
            'title' => 'Для группы А',
            'body' => 'Внутреннее объявление',
            'audience_all' => false,
            'audience_groups' => [$groupA->id],
        ]);

        $this->actingAs($this->trainee('Из А', $groupA), 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->trainee('Из Б', $groupB), 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(0, 'data');
    }

    public function test_course_announcement_reaches_only_enrolled_group(): void
    {
        $course = Course::factory()->create(['title' => 'Безопасность полётов']);
        $enrolled = Group::factory()->create();
        $other = Group::factory()->create();

        $record = new Group2learning(['group_id' => $enrolled->id, 'course_id' => $course->id]);
        $record->study_from = Carbon::now()->subDay();
        $record->study_to = Carbon::now()->addMonth();
        $record->save();

        Announcement::create([
            'title' => 'По курсу',
            'body' => 'Смена расписания',
            'audience_all' => false,
            'audience_courses' => [$course->id],
        ]);

        $this->actingAs($this->trainee('Записан', $enrolled), 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->trainee('Не записан', $other), 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(0, 'data');
    }

    public function test_role_audience_uses_role_link_not_role_string(): void
    {
        // Объявление адресовано инструкторам по slug'у роли из role_user.
        // Роль приходит из связи, а не из users.role: строке мы не
        // доверяем, и тест это проверяет.
        $editor = $this->editor();
        $trainee = $this->trainee();

        Announcement::create([
            'title' => 'Для инструкторов',
            'body' => 'Совещание',
            'audience_all' => false,
            'audience_roles' => ['instructor'],
        ]);

        $this->actingAs($editor, 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(1, 'data');

        $this->actingAs($trainee, 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(0, 'data');
    }

    public function test_expired_and_future_announcements_are_not_shown(): void
    {
        Announcement::create([
            'title' => 'Протухшее',
            'body' => 'Было',
            'audience_all' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        Announcement::create([
            'title' => 'Будущее',
            'body' => 'Ещё не вышло',
            'audience_all' => true,
            'published_at' => Carbon::now()->addDay(),
        ]);

        Announcement::create([
            'title' => 'Актуальное',
            'body' => 'Сейчас',
            'audience_all' => true,
            'published_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDay(),
        ]);

        $this->actingAs($this->trainee(), 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Актуальное');
    }

    public function test_pinned_come_first(): void
    {
        Announcement::create([
            'title' => 'Обычное',
            'body' => '…',
            'audience_all' => true,
            'published_at' => Carbon::now(),
        ]);

        Announcement::create([
            'title' => 'Закреплённое',
            'body' => '…',
            'audience_all' => true,
            'pinned' => true,
            'published_at' => Carbon::now()->subWeek(),
        ]);

        $data = $this->actingAs($this->trainee(), 'sanctum')
            ->getJson('/api/announcements')
            ->json('data');

        $this->assertSame('Закреплённое', $data[0]['title']);
    }

    public function test_trainee_cannot_publish(): void
    {
        $trainee = $this->trainee();

        $this->actingAs($trainee, 'sanctum')
            ->postJson('/api/announcements', ['title' => 'Хочу', 'body' => 'Опубликовать'])
            ->assertForbidden();

        $this->assertSame(0, Announcement::count());
    }

    public function test_editor_can_publish_and_delete(): void
    {
        $editor = $this->editor();

        $id = $this->actingAs($editor, 'sanctum')
            ->postJson('/api/announcements', [
                'title' => 'Новое объявление',
                'body' => 'Текст',
                'audience_groups' => [Group::factory()->create()->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.audience_all', false)
            ->json('data.id');

        $this->assertDatabaseHas('announcements', ['id' => $id, 'author_id' => $editor->id]);

        $this->actingAs($editor, 'sanctum')
            ->deleteJson("/api/announcements/{$id}")
            ->assertOk();

        $this->assertSame(0, Announcement::count());
    }

    public function test_empty_audience_defaults_to_everyone(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'sanctum')
            ->postJson('/api/announcements', ['title' => 'Без уточнений', 'body' => 'Текст'])
            ->assertCreated()
            ->assertJsonPath('data.audience_all', true);

        // Иначе автор решил бы, что публикация не работает: объявление
        // без адресата просто никто бы не увидел.
        $this->assertSame(1, Announcement::where('audience_all', true)->count());
    }

    public function test_expiry_before_publish_is_rejected(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'sanctum')
            ->postJson('/api/announcements', [
                'title' => 'Несогласованные сроки',
                'body' => 'Текст',
                'published_at' => Carbon::now()->addWeek()->toDateTimeString(),
                'expires_at' => Carbon::now()->toDateTimeString(),
            ])
            ->assertStatus(422);
    }

    public function test_editor_sees_announcements_addressed_to_other_groups(): void
    {
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();

        Announcement::create([
            'title' => 'Только для А',
            'body' => '…',
            'audience_all' => false,
            'audience_groups' => [$groupA->id],
        ]);

        // Редактору нужна вся лента: иначе он не увидит собственную
        // публикацию, адресованную другой группе, и решит, что она удалена.
        $this->actingAs($this->editor(), 'sanctum')
            ->getJson('/api/announcements')
            ->assertJsonCount(1, 'data');
    }
}
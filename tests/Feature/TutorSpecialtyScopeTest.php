<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\TutorChunk;
use App\Models\TutorMaterial;
use App\Models\TutorSession;
use App\Models\User;
use App\Support\Tutor\TutorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Тренажёр не показывает материалы чужой специальности.
 *
 * Это дыра, о которой сообщил пользователь: летчик не должен видеть
 * материал радиста. В системе один курс привязан к нескольким
 * специальностям сразу, а запись группы в group2learnings несёт свою
 * category_id — то есть группа записана на курс В РАМКАХ своей
 * специальности.
 *
 * Проверяется ровно то, что описывает требование: одна группа, один
 * курс, две специальности — группа видит только свою.
 */
class TutorSpecialtyScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tutor.enabled' => true]);

        $this->app->instance(TutorClient::class, new class extends TutorClient
        {
            public function health(): array
            {
                return ['available' => true, 'models' => ['t'], 'model' => 't', 'model_present' => true];
            }

            public function generate(array $messages, int $maxTokens = 900): array
            {
                return ['json' => ['questions' => []], 'raw' => ''];
            }

            public function embed(array $input): ?array
            {
                return null;
            }
        });
    }

    /** Курс, привязанный к двум специальностям, и группа с записью на одну из них. */
    private function scenario(): array
    {
        $pilot = Category::factory()->create(['title' => 'Командир экипажа']);
        $radio = Category::factory()->create(['title' => 'Бортовой радист']);

        $course = Course::factory()->create(['title' => 'Конструкция самолета']);
        $course->categories()->sync([$pilot->id, $radio->id]);

        $group = Group::factory()->create();

        // Запись группы: только командир экипажа.
        $row = Group2learning::firstOrNew([
            'group_id' => $group->id,
            'course_id' => $course->id,
            'category_id' => $pilot->id,
        ]);
        $row->group_id = $group->id;
        $row->course_id = $course->id;
        $row->category_id = $pilot->id;
        $row->typeOfLesson = 'Лекция';
        $row->study_from = now()->subDay()->toDateString();
        $row->study_to = now()->addDays(14)->toDateString();
        $row->save();

        $pilotMaterial = TutorMaterial::factory()->create([
            'course_id' => $course->id,
            'category_id' => $pilot->id,
        ]);
        $radioMaterial = TutorMaterial::factory()->create([
            'course_id' => $course->id,
            'category_id' => $radio->id,
        ]);

        foreach ([$pilotMaterial, $radioMaterial] as $m) {
            TutorChunk::factory()->create(['material_id' => $m->id]);
        }

        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->forceFill(['group_id' => $group->id])->save();
        $user->givePermissionsTo('tutor.use');
        $user->forgetPermissionCache();

        return [$user->fresh(), $pilotMaterial, $radioMaterial, $pilot, $radio];
    }

    public function test_group_sees_only_its_own_specialty(): void
    {
        [$user, $pilotMaterial, $radioMaterial] = $this->scenario();

        $ids = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$pilotMaterial->id], $ids, 'летчик не должен видеть материал радиста');
        $this->assertNotContains($radioMaterial->id, $ids);
    }

    public function test_material_of_other_specialty_is_forbidden(): void
    {
        [$user, , $radioMaterial] = $this->scenario();

        // Права tutor.use достаточно: ограничение в специальности записи,
        // а не в самом факте права.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/sessions', ['material_id' => $radioMaterial->id])
            ->assertForbidden();
    }

    public function test_specialty_is_returned_to_frontend(): void
    {
        [$user, $pilotMaterial, , $pilot] = $this->scenario();

        $row = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->json('data.0');

        $this->assertSame($pilotMaterial->id, $row['id']);
        $this->assertSame($pilot->id, $row['category_id']);
        $this->assertSame('Командир экипажа', $row['category_title']);
    }

    public function test_second_specialty_of_same_group_opens_its_own_material(): void
    {
        // Группа записана на две специальности — двумя записями. Тогда
        // видны оба материала: это не «все подряд», а ровно свои.
        [$user, $pilotMaterial, $radioMaterial] = $this->scenario();

        // Поля задаются поимённо: у Group2learning в $fillable их нет,
        // и массовое присвоение молча отбрасывает даты — запись падает
        // на NOT NULL.
        $second = Group2learning::firstOrNew([
            'group_id' => $user->group_id,
            'course_id' => $pilotMaterial->course_id,
            'category_id' => $radioMaterial->category_id,
        ]);
        $second->typeOfLesson = 'Лекция';
        $second->study_from = now()->subDay()->toDateString();
        $second->study_to = now()->addDays(14)->toDateString();
        $second->save();

        $ids = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->json('data.*.id');

        $this->assertCount(2, $ids);
        $this->assertContains($pilotMaterial->id, $ids);
        $this->assertContains($radioMaterial->id, $ids);
        $this->assertNotNull($second);
    }

    public function test_other_group_specialty_stays_closed(): void
    {
        // Чужая группа на ту же специальность: не видит ничего.
        [$user] = $this->scenario();

        $otherGroup = Group::factory()->create();
        $other = User::factory()->create(['role' => 'Обучаемый']);
        $other->forceFill(['group_id' => $otherGroup->id])->save();
        $other->givePermissionsTo('tutor.use');
        $other->forgetPermissionCache();

        $ids = $this->actingAs($other->fresh(), 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $ids);
        $this->assertNotNull($user);
    }

    public function test_enrollment_without_specialty_grants_nothing(): void
    {
        // Запись без category_id не должна открывать материалы: иначе
        // старая запись, созданная до появления специальностей, снова
        // станет ключом ко всему подряд.
        [$user] = $this->scenario();

        DB::table('group2learnings')->where('group_id', $user->group_id)->delete();

        $ids = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $ids);
    }
}
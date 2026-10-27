<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Aircraft;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Feature\AuthenticatesApi;

/**
 * Переупорядочивание узлов курса (раздел 8, остаток бэка).
 *
 * POST /api/aukstructure/reorder { course_id, ids } — ids целиком,
 * дубликаты/чужие id/неполный список отклоняются. Порядок чтения —
 * coalesce(position, id), поэтому player отдаёт новый порядок сразу.
 */
class AukstructureReorderTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private function fixtures(): array
    {
        $aircraft = Aircraft::factory()->create();
        $course = Course::factory()->create(['aircraft_id' => $aircraft->id]);
        $auks = [];
        // Три узла, созданы в порядке a, b, c.
        foreach (['a', 'b', 'c'] as $title) {
            $auks[$title] = Aukstructure::factory()->create([
                'course_id' => $course->id,
                'type' => 3,
                'title' => $title,
            ]);
        }

        return [$course, $auks];
    }

    public function test_reorders_nodes_and_player_reads_new_order(): void
    {
        [$course, $auks] = $this->fixtures();
        $this->admin();

        $this->postJson('/api/aukstructure/reorder', [
            'course_id' => $course->id,
            'ids' => [$auks['c']->id, $auks['a']->id, $auks['b']->id],
        ])->assertOk();

        $order = Aukstructure::where('course_id', $course->id)
            ->orderByRaw('coalesce(position, id)')
            ->pluck('title')->all();

        $this->assertSame(['c', 'a', 'b'], $order);
    }

    public function test_rejects_partial_and_duplicate_lists(): void
    {
        [$course, $auks] = $this->fixtures();
        $this->admin();

        // Неполный список: 2 из 3.
        $this->postJson('/api/aukstructure/reorder', [
            'course_id' => $course->id,
            'ids' => [$auks['c']->id, $auks['a']->id],
        ])->assertStatus(422);

        // Дубликат.
        $this->postJson('/api/aukstructure/reorder', [
            'course_id' => $course->id,
            'ids' => [$auks['c']->id, $auks['c']->id, $auks['b']->id],
        ])->assertStatus(422);
    }

    public function test_rejects_nodes_of_other_course(): void
    {
        [$course, $auks] = $this->fixtures();
        $aircraft = Aircraft::factory()->create();
        $other = Course::factory()->create(['aircraft_id' => $aircraft->id]);
        $foreign = Aukstructure::factory()->create(['course_id' => $other->id, 'type' => 3]);
        $this->admin();

        $this->postJson('/api/aukstructure/reorder', [
            'course_id' => $course->id,
            'ids' => [$auks['a']->id, $auks['b']->id, $auks['c']->id, $foreign->id],
        ])->assertStatus(422);
    }

    public function test_requires_content_manage(): void
    {
        [$course, $auks] = $this->fixtures();
        User::factory()->create(['password' => bcrypt('x'), 'role' => 'Обучаемый']);
        $this->asExistingUser(User::first());

        $this->postJson('/api/aukstructure/reorder', [
            'course_id' => $course->id,
        ])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Feature\AuthenticatesApi;

/**
 * M4 (design-manifest §2.3): правила доступа («drip content») узла курса.
 *
 * PUT /api/aukstructure/{id}/rules — пишет только unlock_* поля,
 * нерелевантные сбрасывает; prerequisite обязан быть из того же курса.
 */
class AukstructureRulesTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private function fixtures(): array
    {
        $aircraft = Aircraft::factory()->create();
        $course = Course::factory()->create(['aircraft_id' => $aircraft->id]);
        $a = Aukstructure::factory()->create(['course_id' => $course->id, 'type' => 3, 'title' => 'a']);
        $b = Aukstructure::factory()->create(['course_id' => $course->id, 'type' => 3, 'title' => 'b']);
        // Узел другого курса — для проверки cross-course запрета.
        $otherCourse = Course::factory()->create(['aircraft_id' => $aircraft->id]);
        $foreign = Aukstructure::factory()->create(['course_id' => $otherCourse->id, 'type' => 3, 'title' => 'x']);

        return [$course, $a, $b, $foreign];
    }

    public function test_date_mode_stores_unlock_at(): void
    {
        [, $a] = $this->fixtures();
        $this->admin();

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'date',
            'unlock_at' => '2026-11-05T10:00:00',
        ])->assertOk()->assertJsonPath('data.unlock_mode', 'date');

        $fresh = $a->fresh();
        $this->assertSame('date', $fresh->unlock_mode);
        $this->assertNotNull($fresh->unlock_at);
        $this->assertNull($fresh->prerequisite_id);
        $this->assertNull($fresh->min_score);
    }

    public function test_prereq_mode_requires_same_course(): void
    {
        [, $a, $b, $foreign] = $this->fixtures();
        $this->admin();

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'prereq',
            'prerequisite_id' => $foreign->id,
        ])->assertStatus(422);

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'prereq',
            'prerequisite_id' => $b->id,
        ])->assertOk();

        $this->assertSame($b->id, $a->fresh()->prerequisite_id);
    }

    public function test_test_mode_validates_score_range(): void
    {
        [, $a] = $this->fixtures();
        $this->admin();

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'test',
            'min_score' => 150,
        ])->assertStatus(422);

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'test',
            'min_score' => 70,
        ])->assertOk();

        $this->assertSame(70, $a->fresh()->min_score);
    }

    public function test_clearing_mode_resets_all_rule_fields(): void
    {
        [, $a, $b] = $this->fixtures();
        $this->admin();

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'prereq',
            'prerequisite_id' => $b->id,
        ])->assertOk();

        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => null,
        ])->assertOk();

        $fresh = $a->fresh();
        $this->assertNull($fresh->unlock_mode);
        $this->assertNull($fresh->unlock_at);
        $this->assertNull($fresh->prerequisite_id);
        $this->assertNull($fresh->min_score);
    }

    public function test_missing_conditional_field_is_rejected(): void
    {
        [, $a] = $this->fixtures();
        $this->admin();

        // mode=date без unlock_at — required через exclude_unless.
        $this->putJson("/api/aukstructure/{$a->id}/rules", [
            'unlock_mode' => 'date',
        ])->assertStatus(422);
    }

    public function test_unknown_node_returns_404(): void
    {
        $this->fixtures();
        $this->admin();

        $this->putJson('/api/aukstructure/999999/rules', [
            'unlock_mode' => null,
        ])->assertNotFound();
    }
}

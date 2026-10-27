<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\CourseMaterialView;
use App\Models\CourseProgress;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Контрактный тест player-endpoint (/api/course/{id}/player).
 *
 * Именно отсутствие этого теста позволило появиться дефекту Д2: фронт читал
 * data.entry.signed_base, сервер клал signed_path — молча, без единого упавшего
 * теста. Здесь зафиксированы ИМЕНА полей ответа, чтобы любая правка формата
 * ломала CI, а не плеер на проде.
 */
class CoursePlayerEndpointTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticatesApi;

    private function course(): Course
    {
        return Course::create([
            'title' => 'Курс для контракта',
            'short_description' => 'Описание',
            'long_description' => 'Описание',
            'path' => 'course-contract',
            'visible' => true,
        ]);
    }

    private function node(Course $course, array $attrs = []): Aukstructure
    {
        return Aukstructure::create(array_merge([
            'course_id' => $course->id,
            'parent_id' => 0,
            'title' => 'Узел',
            'type' => 1,
            'identifier' => 'N-' . $course->id . '-' . uniqid(),
        ], $attrs));
    }

    private function material(Course $course, string $title, ?int $parent = null): Aukstructure
    {
        $node = $this->node($course, ['title' => $title, 'type' => 3, 'parent_id' => $parent ?? 0]);
        Link::create(['aukstructure_id' => $node->id, 'link' => "{$title}.html"]);

        return $node;
    }

    public function test_guest_gets_401(): void
    {
        $course = $this->course();

        $this->getJson("/api/course/{$course->id}/player")->assertStatus(401);
    }

    public function test_payload_contract_keys(): void
    {
        $user = $this->asUser();
        $course = $this->course();
        $section = $this->node($course, ['title' => 'Раздел', 'type' => 2]);
        $m1 = $this->material($course, 'Материал 1', $section->id);
        $m2 = $this->material($course, 'Материал 2', $section->id);

        $response = $this->getJson("/api/course/{$course->id}/player")->assertOk();

        $json = $response->json();
        // верхний уровень контракта
        foreach (['course', 'nodes', 'tree', 'entry', 'resume', 'stats'] as $key) {
            $this->assertArrayHasKey($key, $json, "В ответе player-endpoint нет ключа {$key}");
        }

        // Д2: entry несёт content_url — фронту не нужен RTT к getlink и угадывание ключей
        $this->assertNotNull($json['entry']);
        $this->assertSame($m1->id, $json['entry']['item_id']);
        $this->assertSame("Материал 1", $json['entry']['title']);
        $this->assertSame("/api/course/{$course->id}/content/{$m1->id}", $json['entry']['content_url']);
        $this->assertArrayNotHasKey('signed_base', $json['entry']);
        $this->assertArrayNotHasKey('signed_path', $json['entry']);

        // узел контракта
        $node = collect($json['nodes'])->firstWhere('id', $m1->id);
        foreach (['id', 'parent_id', 'title', 'type', 'is_material', 'has_link', 'content_url', 'visited'] as $key) {
            $this->assertArrayHasKey($key, $node, "В узле нет поля {$key}");
        }

        // stats
        $this->assertSame(2, $json['stats']['materials_total']);
        $this->assertSame(0, $json['stats']['materials_visited']);
        $this->assertSame(0, $json['stats']['percent']);
        $this->assertNull($json['resume']);
    }

    /**
     * Д3: признак материала — из type === 3, а не из схлопнутого level.
     * Узел type=3 без файла НЕ кликабелен; раздел type=2 на «третьем уровне»
     * остаётся заголовком.
     */
    public function test_is_material_comes_from_type_not_level(): void
    {
        $this->asUser();
        $course = $this->course();
        $root = $this->node($course, ['type' => 0]);           // корень АУК
        $topic = $this->node($course, ['type' => 2, 'parent_id' => $root->id]);
        $mat = $this->material($course, 'Файл', $topic->id);   // type=3 → материал
        $this->node($course, ['type' => 3, 'parent_id' => $topic->id]); // type=3 без link → не материал
        $this->node($course, ['type' => 1, 'parent_id' => $topic->id]); // обычный узел

        $nodes = $this->getJson("/api/course/{$course->id}/player")->assertOk()->json('nodes');

        $byId = collect($nodes)->keyBy('id');
        $this->assertTrue($byId[$mat->id]['is_material']);
        $this->assertFalse($byId->firstWhere('has_link', false)['is_material'],
            'узел type=3 без файла не должен быть кликабельным материалом');
        $this->assertFalse($byId[$topic->id]['is_material']);
        $this->assertFalse($byId[$root->id]['is_material']);
    }

    /**
     * Д4: плоский курс (материалы с parent_id=0) обязан дать непустое дерево.
     */
    public function test_flat_course_tree_not_empty(): void
    {
        $this->asUser();
        $course = $this->course();
        $m1 = $this->material($course, 'Лист 1');
        $m2 = $this->material($course, 'Лист 2');

        $tree = $this->getJson("/api/course/{$course->id}/player")->assertOk()->json('tree');

        $this->assertCount(2, $tree);
        $this->assertEqualsCanonicalizing([$m1->id, $m2->id], array_column($tree, 'id'));
    }

    /**
     * Д8: prev/next — один цикл по материалам, считается на сервере.
     */
    public function test_prev_next_chain_over_materials(): void
    {
        $this->asUser();
        $course = $this->course();
        $section = $this->node($course, ['type' => 2]);
        $m1 = $this->material($course, 'М1', $section->id);
        $this->node($course, ['type' => 1, 'parent_id' => $section->id]); // не материал — в цепи нет
        $m2 = $this->material($course, 'М2', $section->id);

        $nodes = collect($this->getJson("/api/course/{$course->id}/player")->assertOk()->json('nodes'))->keyBy('id');

        $this->assertNull($nodes[$m1->id]['prev_id']);
        $this->assertSame($m2->id, $nodes[$m1->id]['next_id']);
        $this->assertSame($m1->id, $nodes[$m2->id]['prev_id']);
        $this->assertNull($nodes[$m2->id]['next_id']);
    }

    public function test_resume_and_visited_reflect_server_progress(): void
    {
        $user = $this->asUser();
        $course = $this->course();
        $m1 = $this->material($course, 'М1');
        $m2 = $this->material($course, 'М2');

        CourseMaterialView::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'aukstructure_id' => $m1->id,
            'seconds_viewed' => 30,
            'max_scroll_percent' => 95,
            'completed' => true,
        ]);
        CourseProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'last_item_id' => $m2->id,
            'last_anchor' => 'p-12',
            'percent' => 50,
        ]);

        $json = $this->getJson("/api/course/{$course->id}/player")->assertOk()->json();

        // Д6: resume читается с сервера
        $this->assertSame($m2->id, $json['resume']['last_item_id']);
        $this->assertSame('p-12', $json['resume']['last_anchor']);

        $nodes = collect($json['nodes'])->keyBy('id');
        $this->assertTrue($nodes[$m1->id]['visited']);
        $this->assertFalse($nodes[$m2->id]['visited']);
        $this->assertSame(1, $json['stats']['materials_visited']);
        $this->assertSame(50, $json['stats']['percent']);
    }

    /* ---------------- POST /progress (resume-point) ---------------- */

    public function test_progress_endpoint_saves_resume_point(): void
    {
        $user = $this->asUser();
        $course = $this->course();
        $m = $this->material($course, 'М1');

        $this->postJson("/api/course/{$course->id}/progress", [
            'last_item_id' => $m->id,
            'last_anchor' => 'sec-2',
            'percent' => 40,
        ])->assertOk()->assertJson(['ok' => true]);

        $resume = $this->getJson("/api/course/{$course->id}/player")->assertOk()->json('resume');
        $this->assertSame($m->id, $resume['last_item_id']);
        $this->assertSame('sec-2', $resume['last_anchor']);
    }

    public function test_progress_rejects_foreign_node(): void
    {
        $this->asUser();
        $course = $this->course();
        $other = $this->course();
        $foreign = $this->material($other, 'Чужой');

        $this->postJson("/api/course/{$course->id}/progress", [
            'last_item_id' => $foreign->id,
        ])->assertStatus(422);
    }

    /* ---------------- POST /view (событийный прогресс, P2) ---------------- */

    public function test_view_marks_completed_by_time_or_scroll(): void
    {
        $user = $this->asUser();
        $course = $this->course();
        $m = $this->material($course, 'М1');

        // коротко и без скролла — не completed
        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $m->id,
            'seconds_viewed' => 5,
            'scroll_percent' => 30,
        ])->assertOk()->assertJson(['completed' => false]);

        // добираем время >= 15с — completed (накопительный учёт секунд)
        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $m->id,
            'seconds_viewed' => 10,
            'scroll_percent' => 40,
        ])->assertOk()->assertJson(['completed' => true]);

        $stats = $this->getJson("/api/course/{$course->id}/player")->assertOk()->json('stats');
        $this->assertSame(1, $stats['materials_visited']);
    }

    public function test_view_rejects_non_material_node(): void
    {
        $this->asUser();
        $course = $this->course();
        $section = $this->node($course, ['type' => 2]);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $section->id,
            'seconds_viewed' => 20,
            'scroll_percent' => 100,
        ])->assertStatus(422);
    }
}

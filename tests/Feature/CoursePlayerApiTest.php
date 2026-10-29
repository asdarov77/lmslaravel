<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\CourseMaterialView;
use App\Models\CourseProgress;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Link;
use App\Models\User;
use App\Services\CoursePlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CRUD + бизнес-логика API плеера курса (CoursePlayerController).
 *
 * Дополняет контрактный CoursePlayerEndpointTest: там зафиксированы ИМЕНА
 * ключей ответа, здесь — поведение: что реально меняется в PostgreSQL после
 * каждого запроса, как ведут себя RBAC-роли, валидация и граничные случаи
 * (пустые поля, неверные форматы, поддельные id, узлы чужого курса).
 *
 * @see \App\Http\Controllers\CoursePlayerController
 */
class CoursePlayerApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticatesApi;

    /* ─────────────────────────── фикстуры ─────────────────────────── */

    private function course(string $title = 'Курс плеера'): Course
    {
        return Course::factory()->create(['title' => $title]);
    }

    /** Узел-материал (type 3) со ссылкой на файл. */
    private function material(Course $course, string $title = 'Материал', int $parent = 0): Aukstructure
    {
        $node = Aukstructure::factory()->create([
            'course_id' => $course->id,
            'parent_id' => $parent,
            'title' => $title,
            'type' => CoursePlayerService::TYPE_MATERIAL,
        ]);
        Link::create(['aukstructure_id' => $node->id, 'link' => mb_strtolower($title) . '.html']);

        return $node;
    }

    /** Раздел (type 2) — не материал. */
    private function section(Course $course, string $title = 'Раздел'): Aukstructure
    {
        return Aukstructure::factory()->create([
            'course_id' => $course->id,
            'parent_id' => 0,
            'title' => $title,
            'type' => 2,
        ]);
    }

    /** Обучаемый, записанный на курс целиком (parent_id null → весь курс). */
    private function enrolledTrainee(Course $course): User
    {
        $group = Group::factory()->create();
        $user = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);
        Group2learning::factory()->create([
            'group_id' => $group->id,
            'course_id' => $course->id,
            'category_id' => null,
            'parent_id' => null,
        ]);

        return $user;
    }

    /* ══════════════════ READ: GET /api/course/{id}/player ══════════════════ */

    public function test_student_reads_player_payload_and_nothing_is_written(): void
    {
        $course = $this->course();
        $section = $this->section($course);
        $m1 = $this->material($course, 'Лекция 1', $section->id);
        $m2 = $this->material($course, 'Лекция 2', $section->id);

        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $response = $this->getJson("/api/course/{$course->id}/player")->assertOk();

        $data = $response->json('data');
        $this->assertSame($course->id, $data['course']['id']);
        $this->assertSame($course->title, $data['course']['title']);
        // Д3: признак материала — серверный, по type===3+link, а не по фронтовому level
        $byId = collect($data['nodes'])->keyBy('id');
        $this->assertTrue($byId[$m1->id]['is_material']);
        $this->assertFalse($byId[$section->id]['is_material']);
        // Д8: prev/next — единый цикл по материалам
        $this->assertNull($byId[$m1->id]['prev_id']);
        $this->assertSame($m2->id, $byId[$m1->id]['next_id']);
        $this->assertSame($m1->id, $byId[$m2->id]['prev_id']);
        // entry — первый материал с content_url (Д2)
        $this->assertSame($m1->id, $data['entry']['item_id']);
        $this->assertSame("/api/course/{$course->id}/content/{$m1->id}", $data['entry']['content_url']);
        // stats пустого курса
        $this->assertSame(2, $data['stats']['materials_total']);
        $this->assertSame(0, $data['stats']['materials_visited']);

        // READ не должен ничего писать (идемпотентность GET)
        $this->assertSame(0, CourseProgress::count());
        $this->assertSame(0, CourseMaterialView::count());
    }

    public function test_player_of_nonexistent_course_returns_404_not_500(): void
    {
        // Граничный случай: «поддельный id» курса. findOrFail → 404 конвертом,
        // а не TypeError/500 — регрессия падений плеера на мёртвых ссылках.
        $this->admin();
        $this->getJson('/api/course/999999/player')->assertStatus(404);
    }

    public function test_player_rejects_non_numeric_course_id_at_routing_layer(): void
    {
        $this->admin();
        // whereNumber('courseId'): строка в URL не доходит до контроллера
        $this->getJson('/api/course/abc/player')->assertStatus(404);
    }

    /* ──────────────── доступ по ролям к структуре курса ──────────────── */

    public function test_anonymous_cannot_read_player_structure(): void
    {
        $course = $this->course();
        $this->getJson("/api/course/{$course->id}/player")->assertStatus(401);
    }

    /**
     * @defect №2 (см. docs/TESTING-ARCHITECTURE.md): player-endpoint не гейтится
     * CourseAccess, в отличие от content/raw. Любой вошедший читает структуру
     * чужого курса по id. Предлагаемый фикс — в show():
     *   CourseAccess::authorizeOpen($request->user() ?? Auth::user(), $course);
     * Тест закрепляет ЦЕЛЕВОЕ поведение и будет красным до фикса — так дефект
     * виден в отчётах CI, а не живёт в вики.
     */
    public function test_unenrolled_student_must_not_see_foreign_course_structure(): void
    {
        $course = $this->course();
        $this->material($course, 'Секретный модуль');

        $outsider = $this->asUser(['role' => 'Обучаемый', 'group_id' => null]);

        $this->getJson("/api/course/{$course->id}/player")->assertStatus(403);
    }

    public function test_instructor_with_content_manage_opens_any_course_structure(): void
    {
        // Инструктор с courses.view читает витрину; курсы ему открывает
        // courses.manage/content.manage через CourseAccess::manages().
        $course = $this->course();
        $this->material($course);

        $instructor = User::factory()->create(['role' => 'Инструктор']);
        $permissionId = DB::table('permissions')->where('slug', 'courses.manage')->value('id');
        if ($permissionId === null) {
            $this->markTestSkipped('PermissionSeeder не выполнялся: нет courses.manage');
        }
        DB::table('permissions_users')->insert([
            'permission_id' => $permissionId,
            'user_id' => $instructor->id,
        ]);

        $this->asExistingUser($instructor);
        $this->getJson("/api/course/{$course->id}/player")->assertOk();
    }

    /* ═══════════ CREATE/UPDATE: POST /api/course/{id}/progress ═══════════ */

    public function test_progress_creates_resume_point_in_postgres(): void
    {
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/progress", [
            'last_item_id' => $item->id,
            'last_anchor' => '#p-42',
            'percent' => 37,
        ])->assertOk()->assertJson(['data' => ['ok' => true]]);

        // Проверка изменений именно в БД, а не только ответа
        $row = DB::table('course_progress')
            ->where('user_id', $user->id)->where('course_id', $course->id)->first();
        $this->assertNotNull($row, 'Resume-point не записан в PostgreSQL');
        $this->assertSame($item->id, (int) $row->last_item_id);
        $this->assertSame('#p-42', $row->last_anchor);
        $this->assertSame(37, (int) $row->percent);
    }

    public function test_progress_update_is_upsert_not_duplicate(): void
    {
        // Повторный POST той же пары (user, course) обязан обновить строку,
        // а не плодить дубликаты (updateOrCreate по составному ключу).
        $course = $this->course();
        $a = $this->material($course, 'Модуль А');
        $b = $this->material($course, 'Модуль Б');
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $a->id])->assertOk();
        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $b->id, 'percent' => 80])->assertOk();

        $rows = DB::table('course_progress')->where('user_id', $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($b->id, (int) $rows[0]->last_item_id);
        $this->assertSame(80, (int) $rows[0]->percent);
    }

    public function test_progress_survives_reload_via_player_payload(): void
    {
        // Связка C→R: сохранённый resume обязан вернуться из GET /player —
        // иначе «продолжить с того же места» на втором устройстве не работает.
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/progress", [
            'last_item_id' => $item->id, 'last_anchor' => '1200', 'percent' => 55,
        ])->assertOk();

        $resume = $this->getJson("/api/course/{$course->id}/player")
            ->assertOk()->json('data.resume');

        $this->assertSame($item->id, $resume['last_item_id']);
        $this->assertSame('1200', $resume['last_anchor']);
        $this->assertSame(55, $resume['percent']);
    }

    /* ──────────────────────── валидация progress ──────────────────────── */

    public function test_progress_empty_fields_fail_validation(): void
    {
        $course = $this->course();
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/progress", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_item_id']);

        $this->assertSame(0, DB::table('course_progress')->count());
    }

    public function test_progress_rejects_wrong_formats(): void
    {
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        // строка вместо integer, float вместо integer, percent вне 0..100
        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['last_item_id']);
        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => 1.5])
            ->assertStatus(422);
        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $item->id, 'percent' => 101])
            ->assertStatus(422)->assertJsonValidationErrors(['percent']);
        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $item->id, 'percent' => -1])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('course_progress')->count(), 'Невалидные запросы не должны писать БД');
    }

    public function test_progress_rejects_forged_item_id_from_foreign_course(): void
    {
        // Поддельный id существует в aukstructures, но принадлежит ДРУГОМУ курсу.
        // exists: проходит, поэтому в контроллере есть отдельная проверка
        // принадлежности — без неё resume-point ломает дерево чужого курса.
        $mine = $this->course('Мой курс');
        $foreign = $this->course('Чужой курс');
        $foreignItem = $this->material($foreign, 'Чужой материал');

        $user = $this->enrolledTrainee($mine);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$mine->id}/progress", ['last_item_id' => $foreignItem->id])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('course_progress')->count());
    }

    public function test_anonymous_cannot_write_progress(): void
    {
        $course = $this->course();
        $item = $this->material($course);

        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $item->id])
            ->assertStatus(401);
        $this->assertSame(0, DB::table('course_progress')->count());
    }

    /**
     * @defect №2 (продолжение): до фикса POST /progress писал resume-point в
     * ЛЮБОЙ курс по id — перебор id позволял отравлять статистику чужих
     * курсов. Фикс: CourseAccess::authorizeOpen() перед записью.
     * Тест закрепляет ЦЕЛЕВОЕ поведение (403 и пустая БД).
     */
    public function test_unenrolled_student_must_not_write_progress_to_foreign_course(): void
    {
        $course = $this->course('Чужой курс');
        $item = $this->material($course);

        $outsider = $this->asUser(['role' => 'Обучаемый', 'group_id' => null]);

        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $item->id])
            ->assertStatus(403);

        $this->assertSame(0, DB::table('course_progress')->count(), '403 обязан приходить ДО записи в БД');
    }

    public function test_instructor_without_courses_manage_must_not_write_progress(): void
    {
        // Инструктор БЕЗ courses.manage/content.manage — не «manages»,
        // и на курс не записан → 403 (то же правило, что у GET /player).
        $course = $this->course();
        $item = $this->material($course);

        $instructor = User::factory()->create(['role' => 'Инструктор']);
        $this->asExistingUser($instructor);

        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $item->id])
            ->assertStatus(403);
        $this->assertSame(0, DB::table('course_progress')->count());
    }

    public function test_admin_can_write_progress_to_any_course(): void
    {
        // Позитивная половина RBAC-правила: manages() проходит authorizeOpen,
        // иначе фикс сломал бы админские сценарии («тест на 403» без пары).
        $course = $this->course();
        $item = $this->material($course);

        $this->admin();

        $this->postJson("/api/course/{$course->id}/progress", ['last_item_id' => $item->id])
            ->assertOk();
        $this->assertSame(1, DB::table('course_progress')->count());
    }

    /* ═══════════ EVENTUAL PROGRESS: POST /api/course/{id}/view ═══════════ */

    public function test_view_accumulates_seconds_across_events_and_completes_by_time(): void
    {
        // Бизнес-правило: completed = ≥15с суммарно ИЛИ скролл ≥90%.
        // Два события по 8 секунд: каждое <15, сумма ≥15 → completed=true.
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 8, 'scroll_percent' => 10,
        ])->assertOk()->assertJsonPath('data.completed', false);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 8, 'scroll_percent' => 20,
        ])->assertOk()->assertJsonPath('data.completed', true);

        $row = DB::table('course_material_views')
            ->where('user_id', $user->id)->where('aukstructure_id', $item->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(16, (int) $row->seconds_viewed, 'Секунды обязаны накапливаться, а не перезаписываться');
        $this->assertSame(20, (int) $row->max_scroll_percent, 'Хранится максимум прокрутки');
        $this->assertTrue((bool) $row->completed);

        // агрегат попахивает в stats следующего /view сразу (без полного reload)
        $this->assertSame(1, $this->getJson("/api/course/{$course->id}/player")->json('data.stats.materials_visited'));
    }

    public function test_view_completes_by_scroll_even_without_time(): void
    {
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 0, 'scroll_percent' => 90,
        ])->assertOk()->assertJsonPath('data.completed', true);
    }

    public function test_view_max_scroll_never_regresses(): void
    {
        // Регрессия: событие со scroll 30 после scroll 90 не должно занизить
        // max_scroll_percent (иначе счётчик прогресса «отматывается» назад).
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 1, 'scroll_percent' => 90,
        ])->assertOk();
        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 1, 'scroll_percent' => 30,
        ])->assertOk();

        $row = DB::table('course_material_views')
            ->where('user_id', $user->id)->where('aukstructure_id', $item->id)->first();
        $this->assertSame(90, (int) $row->max_scroll_percent);
    }

    public function test_view_rejects_section_node_not_a_material(): void
    {
        // Граничный случай: id раздела (type 2) вместо материала.
        $course = $this->course();
        $section = $this->section($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $section->id, 'seconds_viewed' => 20, 'scroll_percent' => 100,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('course_material_views')->count());
    }

    public function test_view_rejects_forged_and_invalid_input(): void
    {
        $mine = $this->course();
        $foreign = $this->course();
        $foreignItem = $this->material($foreign);
        $user = $this->enrolledTrainee($mine);
        $this->asExistingUser($user);

        // чужой материал
        $this->postJson("/api/course/{$mine->id}/view", [
            'aukstructure_id' => $foreignItem->id, 'seconds_viewed' => 20, 'scroll_percent' => 50,
        ])->assertStatus(422);

        // пустые поля / неверные форматы / отрицательное время / переполнение суток
        $this->postJson("/api/course/{$mine->id}/view", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aukstructure_id', 'seconds_viewed', 'scroll_percent']);
        $this->postJson("/api/course/{$mine->id}/view", [
            'aukstructure_id' => 'x', 'seconds_viewed' => '-5', 'scroll_percent' => 150,
        ])->assertStatus(422);
        $this->postJson("/api/course/{$mine->id}/view", [
            'aukstructure_id' => 1, 'seconds_viewed' => 86401, 'scroll_percent' => 0,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('course_material_views')->count());
    }

    public function test_completed_flag_is_monotonic(): void
    {
        // После completed=true последующие «короткие» события не снимают отметку.
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 60, 'scroll_percent' => 100,
        ])->assertJsonPath('data.completed', true);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 1, 'scroll_percent' => 0,
        ])->assertJsonPath('data.completed', true);
    }

    public function test_view_writes_course_id_and_last_viewed_at(): void
    {
        $course = $this->course();
        $item = $this->material($course);
        $user = $this->enrolledTrainee($course);
        $this->asExistingUser($user);

        $before = now()->subSecond();
        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 20, 'scroll_percent' => 10,
        ])->assertOk();

        $row = DB::table('course_material_views')->where('user_id', $user->id)->first();
        $this->assertSame($course->id, (int) $row->course_id);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), strtotime($row->last_viewed_at));
    }

    public function test_two_users_have_independent_progress(): void
    {
        $course = $this->course();
        $item = $this->material($course);

        $u1 = $this->enrolledTrainee($course);
        $u2 = $this->enrolledTrainee($course);

        $this->asExistingUser($u1);
        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 30, 'scroll_percent' => 100,
        ])->assertOk();

        $this->asExistingUser($u2);
        $payload = $this->getJson("/api/course/{$course->id}/player")->assertOk()->json('data');
        $this->assertSame(0, $payload['stats']['materials_visited'], 'Прогресс u1 не должен протекать в u2');
        $this->assertNull($payload['resume']);
    }

    /**
     * @defect №2 (продолжение): до фикса POST /view писал course_material_views
     * в ЛЮБОЙ курс по id — накрутка «прошли» и порча агрегатов stats чужих
     * курсов. Фикс: CourseAccess::authorizeOpen() перед записью; 403 обязан
     * приходить ДО вставки строки.
     */
    public function test_unenrolled_student_must_not_write_view_events_to_foreign_course(): void
    {
        $course = $this->course('Чужой курс');
        $item = $this->material($course);

        $outsider = $this->asUser(['role' => 'Обучаемый', 'group_id' => null]);

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 60, 'scroll_percent' => 100,
        ])->assertStatus(403);

        $this->assertSame(0, DB::table('course_material_views')->count(), '403 обязан приходить ДО записи в БД');
    }

    public function test_admin_can_write_view_events_to_any_course(): void
    {
        // Позитивная половина RBAC-правила для /view (админ/модератор курса).
        $course = $this->course();
        $item = $this->material($course);

        $this->admin();

        $this->postJson("/api/course/{$course->id}/view", [
            'aukstructure_id' => $item->id, 'seconds_viewed' => 20, 'scroll_percent' => 50,
        ])->assertOk();

        $row = DB::table('course_material_views')->where('aukstructure_id', $item->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($course->id, (int) $row->course_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Link;
use App\Models\Aukstructure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Индексированный поиск по материалам курса (P1.1).
 *
 * Права — как у открытия курса: записанный обучаемый ищет и находит,
 * не записанный получает 403. Индекс наполняется командой
 * courses:index-content либо самоураной при пустом индексе.
 */
class LessonSearchIndexTest extends TestCase
{
    use RefreshDatabase;

    private string $fileHtml;

    private Course $course;

    private Aukstructure $auk;

    /** Временный корень диска 'private' — тот же приём, что в ContentDeliveryTest. */
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpRoot = storage_path('framework/testing/search-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->tmpRoot.'/private/TESTAIR/TESTCOURSE');

        // Контент АУК живёт на диске 'private' (root = родитель courses_path),
        // а не на 'public': конфиг подменяем точечно, реальный каталог не трогаем.
        config([
            'app.courses_path' => $this->tmpRoot.'/private/',
            'filesystems.disks.private.root' => $this->tmpRoot,
        ]);

        $this->fileHtml = '<html><body><p>Прибор показывает скорость полёта</p></body></html>';
        $aircraft = Aircraft::factory()->create(['path' => 'TESTAIR']);
        $this->course = Course::factory()->create([
            'path' => 'TESTCOURSE',
            'aircraft_id' => $aircraft->id,
        ]);
        $this->auk = Aukstructure::factory()->create([
            'course_id' => $this->course->id,
            'type' => 3,
            'title' => 'Скорость',
        ]);
        Link::factory()->create([
            'aukstructure_id' => $this->auk->id,
            'link' => 'speed.html',
        ]);

        Storage::disk('private')->put('private/TESTAIR/TESTCOURSE/speed.html', $this->fileHtml);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpRoot);

        parent::tearDown();
    }

    private function trainee(): User
    {
        $group = Group::factory()->create();
        Group2learning::factory()->create([
            'group_id' => $group->id,
            'course_id' => $this->course->id,
        ]);

        return User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'Обучаемый',
            'group_id' => $group->id,
        ]);
    }

    private function search(User $user, string $query = 'скорость')
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/search-files/', [
                'query' => $query,
                'path' => 'TESTCOURSE',
                'aircraft' => $this->course->aircraft_id,
            ]);
    }

    public function test_command_indexes_course_content(): void
    {
        $this->artisan('courses:index-content')->assertSuccessful();

        $this->assertDatabaseHas('lesson_search_index', [
            'course_id' => $this->course->id,
            'file' => 'speed.html',
        ]);

        $this->assertNotNull(
            DB::table('lesson_search_index')
                ->where('course_id', $this->course->id)
                ->where('file', 'speed.html')
                ->value('searchable'),
            'tsvector-столбец должен заполниться генерацией БД'
        );
    }

    public function test_search_via_index_returns_material(): void
    {
        $this->artisan('courses:index-content');
        $user = $this->trainee();

        $response = $this->search($user)->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'найденное слово должно вернуться');
        $this->assertSame('Скорость', $data[0]['title']);
        $this->assertSame($this->auk->id, $data[0]['itemId']);
    }

    public function test_search_indexes_course_on_demand_when_index_is_empty(): void
    {
        // Индекс не прогонялся командой: обучаемый всё равно находит —
        // self-heal индексирует курс при первом запросе.
        $user = $this->trainee();

        $response = $this->search($user)->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertDatabaseHas('lesson_search_index', ['file' => 'speed.html']);
    }

    public function test_not_enrolled_trainee_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('x'), 'role' => 'Обучаемый']);

        $this->search($user)->assertForbidden();
    }
}

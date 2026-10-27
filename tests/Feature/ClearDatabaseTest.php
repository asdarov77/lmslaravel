<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Кнопка «Очистить базу данных».
 *
 * Регрессии, которые закрывает этот тест:
 *
 *  1. Порядок удаления таблиц был произвольным: courses/categories
 *     удалялись раньше, чем questions/answers/test_results, которые на
 *     них ссылаются. При непустых таблицах очистка падала с
 *     «violates foreign key constraint», и кнопка выглядела нерабочей.
 *
 *  2. Очистка выполнялась по одной таблице без транзакции, поэтому
 *     частичное удаление оставляло базу в несогласованном состоянии.
 *
 *  3. Пользователи, группы и роли тоже удалялись — после очистки нельзя
 *     было ни зайти в систему, ни повторно импортировать контент.
 *
 *  4. Ответ не содержал сведений о том, что именно удалено, поэтому UI
 *     не мог показать результат операции.
 */
class ClearDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        // Маршрут закрыт auth:sanctum + permission:system.maintenance;
        // super-admin проходит проверку по фолбэку роли users.role.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    /** @test */
    public function очистка_удаляет_весь_контент_даже_при_заполненных_связях(): void
    {
        $this->actingAsAdmin();
        $this->seedContent();

        // До очистки все таблицы действительно непустые
        $this->assertGreaterThan(0, Course::count());
        $this->assertGreaterThan(0, DB::table('course_students')->count());
        $this->assertGreaterThan(0, DB::table('course_instructors')->count());
        $this->assertGreaterThan(0, DB::table('test_results')->count());

        $response = $this->postJson('/api/clear-database');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('error', null);

        foreach ([
            'courses', 'categories', 'aircrafts', 'questions', 'answers',
            'test_results', 'links', 'aukstructures', 'category_course',
            'course_students', 'course_instructors',
        ] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Таблица {$table} не очищена");
            $this->assertArrayHasKey($table, $response->json('data.deleted'));
        }
    }

    /** @test */
    public function очистка_не_трогает_пользователей_группы_и_права(): void
    {
        $this->actingAsAdmin();
        $this->seedContent();

        $users = User::count();
        $this->assertGreaterThan(0, $users, 'Нужен хотя бы один пользователь');

        $this->postJson('/api/clear-database')->assertOk();

        // Без пользователей нельзя войти, а без прав — повторно импортировать
        $this->assertSame($users, User::count());
        $this->assertSame($users, DB::table('users')->count());
        $this->assertSame(0, DB::table('role_user')->count(), 'Связи ролей чистить нельзя');
    }

    /** @test */
    public function очистка_идемпотентна_на_пустой_базе(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/clear-database');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Второй вызов на пустой базе тоже должен быть успешным
        $second = $this->postJson('/api/clear-database');
        $second->assertOk();
        foreach ($second->json('data.deleted') as $table => $count) {
            $this->assertSame(0, $count, "Ожидалось 0 удалённых строк в {$table}");
        }
    }

    /** @test */
    public function очистка_сообщает_о_числе_удалённых_строк(): void
    {
        $this->actingAsAdmin();
        $this->seedContent();

        $deleted = $this->postJson('/api/clear-database')->assertOk()->json('data.deleted');

        $this->assertSame(1, $deleted['courses']);
        $this->assertSame(1, $deleted['categories']);
        $this->assertSame(1, $deleted['course_students']);
        $this->assertSame(1, $deleted['course_instructors']);
        $this->assertSame(1, $deleted['test_results']);
    }

    /** @test */
    public function очистка_оставляет_связи_пользователей_с_курсами_на_нуле_без_ошибок_fk(): void
    {
        $this->actingAsAdmin();
        $this->seedContent();

        $this->postJson('/api/clear-database')->assertOk();

        // Удалённые курсы не должны остаться в связях (иначе FK-ошибка при insert)
        $this->assertSame(0, DB::table('course_students')->count());
        $this->assertSame(0, DB::table('course_instructors')->count());
        $this->assertSame(0, DB::table('category_course')->count());
        $this->assertSame(0, DB::table('aukstructure_category')->count());
    }

    /**
     * Наполняет БД связанным контентом по всем FK-цепочкам, которые
     * затрагивает очистка.
     */
    private function seedContent(): void
    {
        $user = User::factory()->create();

        $aircraft = Aircraft::create([
            'title' => 'КЛЕН',
            'path' => 'КЛЕН',
        ]);

        $category = Category::create([
            'title' => 'Категория',
            'code' => 'KE',
            'description' => null,
            'aircraft_id' => $aircraft->id,
        ]);

        $course = Course::create([
            'title' => 'Курс 01',
            'path' => '01',
            'short_description' => null,
            'long_description' => null,
            'visible' => true,
            // status/duration не передаём: status — NOT NULL с default 'active'
            // на уровне БД, явный null его нарушает.
            'aircraft_id' => $aircraft->id,
            'category_id' => $category->id,
        ]);

        $structure = Aukstructure::create([
            'title' => 'Материал',
            'type' => 3,
            'description' => null,
            'categories' => null,
            'identifier' => 'res_01',
            'course_id' => $course->id,
            'parent_id' => null,
        ]);

        $question = DB::table('questions')->insertGetId([
            'question_text' => 'Вопрос?',
            'category_id' => $category->id,
            'aukstructure_id' => $structure->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $answer = DB::table('answers')->insertGetId([
            'question_id' => $question,
            'answer' => 'Ответ',
            'is_correct' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('test_results')->insert([
            'user_id' => $user->id,
            'answer_id' => $answer,
            'result' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('links')->insert([
            'aukstructure_id' => $structure->id,
            'link' => 'index.html',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('category_course')->insert([
            'course_id' => $course->id,
            'category_id' => $category->id,
        ]);

        DB::table('aukstructure_category')->insert([
            'aukstructure_id' => $structure->id,
            'category_id' => $category->id,
        ]);

        DB::table('course_students')->insert([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        DB::table('course_instructors')->insert([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);
    }
}
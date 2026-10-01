<?php

namespace Tests\Feature\Api;

use App\Models\Aircraft;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AuthenticatesApi;
use Tests\TestCase;

/**
 * Фильтры списка курсов (GET /api/courses).
 *
 * Регресс: контроллер получал aircraft_id/category_id, но полностью их
 * игнорировал. Фронтенд (Pages/Courses.vue -> Course/fetchCoursesFilter)
 * отправлял значения, однако набор курсов не менялся — выбор категории
 * или самолёта в UI выглядел работающим, а список оставался прежним.
 * Дополнительно фронт оборачивал параметры в лишний { params: {...} },
 * из-за чего уходило ?params[category_id]=2.
 */
class CoursesFilterTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private function aircraft(string $path): Aircraft
    {
        return Aircraft::create(['title' => $path, 'path' => $path]);
    }

    private function course(string $title, ?Aircraft $aircraft, ?Category $category = null): Course
    {
        $course = Course::create([
            'title' => $title,
            'path' => $title,
            'short_description' => null,
            'long_description' => null,
            'visible' => true,
            'aircraft_id' => $aircraft?->id,
            'category_id' => $category?->id,
        ]);

        // Импорт манифеста связывает курс с категорией через pivot
        // category_course, а поле courses.category_id не заполняет.
        // Контроллер фильтрует по pivot, поэтому тест должен его создавать.
        if ($category) {
            DB::table('category_course')->insert([
                'category_id' => $category->id,
                'course_id' => $course->id,
            ]);
        }

        return $course;
    }

    /** @test */
    public function без_фильтров_возвращает_все_курсы_с_самолётом(): void
    {
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $this->course('01', $bpla);
        $this->course('02', $bpla);

        $json = $this->getJson('/api/courses')->json();

        $this->assertEnvelope($json);
        $this->assertCount(2, $json['data']);
    }

    /** @test */
    public function фильтр_по_самолёту_ограничивает_выборку(): void
    {
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $klen = $this->aircraft('КЛЕН');
        $this->course('01', $bpla);
        $this->course('02', $klen);
        $this->course('03', $klen);

        $json = $this->getJson('/api/courses?aircraft_id='.$klen->id)->json();

        $this->assertEnvelope($json);
        $this->assertCount(2, $json['data']);
        $this->assertSame(
            ['02', '03'],
            array_column($json['data'], 'path')
        );
    }

    /** @test */
    public function фильтр_по_категории_ограничивает_выборку(): void
    {
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $left = Category::create(['title' => 'Летчик', 'code' => 'KE', 'aircraft_id' => $bpla->id]);
        $right = Category::create(['title' => 'Штурман', 'code' => 'SH', 'aircraft_id' => $bpla->id]);

        $this->course('01', $bpla, $left);
        $this->course('02', $bpla, $right);
        $this->course('03', $bpla, $right);

        $json = $this->getJson('/api/courses?category_id='.$right->id)->json();

        $this->assertEnvelope($json);
        $this->assertCount(2, $json['data']);
    }

    /** @test */
    public function категория_импортированного_курса_берётся_из_pivot(): void
    {
        // Регресс: импорт манифеста заполняет pivot category_course, а
        // courses.category_id остаётся null. Фильтр по courses.category_id
        // поэтому всегда возвращал пустой список — при импорте любой
        // категории courses (например, «Штурман») показывалось 0 курсов.
        $this->admin();
        $klen = $this->aircraft('КЛЕН');
        $shturman = Category::create(['title' => 'Штурман', 'code' => 'SH', 'aircraft_id' => $klen->id]);

        $imported = $this->course('Конструкция самолета', $klen);
        $imported->category_id = null;
        $imported->save();
        DB::table('category_course')->insert([
            'category_id' => $shturman->id,
            'course_id' => $imported->id,
        ]);

        $json = $this->getJson('/api/courses?category_id='.$shturman->id)->json();

        $this->assertEnvelope($json);
        $this->assertCount(1, $json['data']);
        $this->assertSame('Конструкция самолета', $json['data'][0]['title']);
    }

    /** @test */
    public function фильтры_складываются(): void
    {
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $klen = $this->aircraft('КЛЕН');
        $left = Category::create(['title' => 'Летчик', 'code' => 'KE', 'aircraft_id' => $klen->id]);
        $right = Category::create(['title' => 'Штурман', 'code' => 'SH', 'aircraft_id' => $klen->id]);

        $this->course('01', $klen, $left);
        $this->course('02', $klen, $right);
        $this->course('03', $bpla, $right);

        $json = $this->getJson(
            '/api/courses?aircraft_id='.$klen->id.'&category_id='.$right->id
        )->json();

        $this->assertEnvelope($json);
        $this->assertCount(1, $json['data']);
        $this->assertSame('02', $json['data'][0]['path']);
    }

    /** @test */
    public function сброс_фильтра_нулем_не_ограничивает_выборку(): void
    {
        // Фронтенд шлёт aircraft_id = 0 / '0', когда фильтр снят.
        // Это «нет фильтра», а не id = 0.
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $klen = $this->aircraft('КЛЕН');
        $this->course('01', $bpla);
        $this->course('02', $klen);

        foreach (['0', ''] as $value) {
            $json = $this->getJson('/api/courses?aircraft_id='.$value)->json();
            $this->assertEnvelope($json);
            $this->assertCount(2, $json['data'], "aircraft_id={$value} не должен фильтровать");
        }
    }

    /** @test */
    public function нечисловой_фильтр_даёт_422(): void
    {
        $this->admin();
        $this->aircraft('БПЛА');

        $this->getJson('/api/courses?aircraft_id=abc')->assertStatus(422);
    }

    /** @test */
    public function курсы_без_самолёта_не_попадают_в_выборку_даже_с_фильтром(): void
    {
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $this->course('01', $bpla);
        // Placeholder от CourseSeeder
        $this->course('course-1', null);

        $json = $this->getJson('/api/courses?aircraft_id='.$bpla->id)->json();

        $this->assertEnvelope($json);
        $this->assertCount(1, $json['data']);
        $this->assertSame('01', $json['data'][0]['path']);
    }

    /** @test */
    public function фильтр_требует_авторизации(): void
    {
        $this->getJson('/api/courses?aircraft_id=1')->assertStatus(401);
    }

    /** @test */
    public function выборка_включает_данные_самолёта(): void
    {
        // Без eager loading был N+1 и course.aircraft === null,
        // из-за чего URL контента собирался как api/private//index.html.
        $this->admin();
        $bpla = $this->aircraft('БПЛА');
        $this->course('01', $bpla);

        $json = $this->getJson('/api/courses')->json();

        $this->assertNotNull($json['data'][0]['aircraft'] ?? null);
        $this->assertSame('БПЛА', $json['data'][0]['aircraft']['path']);
    }
}
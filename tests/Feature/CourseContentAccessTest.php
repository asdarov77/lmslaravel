<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Aircraft;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Доступ к содержимому курса (CourseAccess).
 *
 * Покрывает все четыре content-эндпоинта, а не только /api/course:
 * манифест, ссылка на файл и первая тема курса — это тоже содержимое,
 * и каждый из них раньше читался по идентификатору без проверки.
 */
class CourseContentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $group = Group::factory()->create();
        $aircraft = Aircraft::factory()->create();
        $course = Course::factory()->create(['aircraft_id' => $aircraft->id]);
        $auk = Aukstructure::factory()->create([
            'course_id' => $course->id,
            'type' => 3,
        ]);

        return [$group, $course, $auk];
    }

    private function trainee(Group $group): User
    {
        return User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'Обучаемый',
            'group_id' => $group->id,
        ]);
    }

    /** Назначенный курс виден обучаемому в учебном плане. */
    public function test_trainee_sees_enrolled_course_in_list(): void
    {
        [$group, $course] = $this->fixtures();

        Group2learning::factory()->create([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);

        $user = $this->trainee($group);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/courses');
        $response->assertOk();

        $this->assertCount(1, $response->json('data'), 'trainee должен видеть свой курс');
    }

    /**
     * Все content-эндпоинты закрыты незаписанному и открыты записанному.
     *
     * Проверяется каждый маршрут по отдельности: authorizeOpen стоит в
     * четырёх местах, и забытый вызов — это дыра, а не мелочь.
     */
    public function test_content_endpoints_gate_by_enrolment(): void
    {
        [$group, $course, $auk] = $this->fixtures();
        $user = $this->trainee($group);

        $endpoints = [
            "/api/course/{$course->id}",
            "/api/coursemanifest/{$course->id}",
            "/api/getlink/{$auk->id}",
            "/api/getfirstauk/{$auk->id}",
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($user, 'sanctum')
                ->getJson($endpoint)
                ->assertForbidden("закрыт до записи: {$endpoint}");
        }

        Group2learning::factory()->create([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);

        foreach ($endpoints as $endpoint) {
            $this->actingAs($user, 'sanctum')
                ->getJson($endpoint)
                ->assertOk("открыт после записи: {$endpoint}");
        }
    }

    /**
     * Регрессия: /api/getlink и /api/getfirstauk были зарегистрированы
     * дважды, и незащищённая копия перебивала защищённую. В Laravel
     * побеждает последнее совпадение, поэтому эти маршруты оставались
     * без auth:sanctum, а CourseAccess получал null вместо
     * пользователя и отдавал 403 даже администратору — то есть
     * страница материала не открывалась никому.
     */
    public function test_content_endpoints_reject_anonymous(): void
    {
        [$group, $course, $auk] = $this->fixtures();

        foreach ([
            "/api/course/{$course->id}",
            "/api/coursemanifest/{$course->id}",
            "/api/getlink/{$auk->id}",
            "/api/getfirstauk/{$auk->id}",
        ] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized("аноним не проходит: {$endpoint}");
        }
    }

    /** Администратор открывает содержимое любого курса без записи. */
    public function test_admin_opens_content_without_enrolment(): void
    {
        [$group, $course, $auk] = $this->fixtures();

        $admin = User::factory()->create([
            'password' => bcrypt('secret123'),
            'role' => 'Администратор',
        ]);

        foreach ([
            "/api/course/{$course->id}",
            "/api/coursemanifest/{$course->id}",
            "/api/getlink/{$auk->id}",
            "/api/getfirstauk/{$auk->id}",
        ] as $endpoint) {
            $this->actingAs($admin, 'sanctum')
                ->getJson($endpoint)
                ->assertOk("администратор открывает: {$endpoint}");
        }
    }
}

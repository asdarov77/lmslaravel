<?php

namespace Tests\Feature;

use App\Models\CertificateSetting;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Настройки бланка сертификата.
 *
 * Отдельно от CertificateTest: там проверяется, КОГДА сертификат
 * выдаётся, здесь — что на нём напечатано и кто может это менять.
 */
class CertificateSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Пользователь с правом certificates.manage.
     *
     * Роль, а не выдача прав вручную: права в проекте приходят из
     * матрицы config/permissions.php, и тест, выдающий право в обход
     * неё, проверял бы не тот путь, которым пользуется живой человек.
     */
    private function withPermission(): User
    {
        return User::factory()->create(['role' => 'Инструктор']);
    }

    public function test_settings_are_empty_by_default(): void
    {
        $user = $this->withPermission();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/certificates/settings')
            ->assertOk()
            // Пустые значения, а не null: фронт читает эти ключи
            // без проверок и не должен различать «нет настройки»
            // и «настройки нет».
            ->assertJsonPath('data.organization', null)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.signatory_name', null);
    }

    public function test_settings_are_saved_and_read_back(): void
    {
        $user = $this->withPermission();

        $payload = [
            'organization' => 'Учебный центр «Авиатор»',
            'signatory_name' => 'Иванов И. И.',
            'signatory_title' => 'Руководитель',
            'footer_note' => 'Выдан без отметки о печати.',
        ];

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/certificates/settings', $payload)
            ->assertOk()
            ->assertJsonPath('data.organization', 'Учебный центр «Авиатор»')
            ->assertJsonPath('data.signatory_title', 'Руководитель');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/certificates/settings')
            ->assertOk()
            ->assertJsonPath('data.organization', 'Учебный центр «Авиатор»')
            ->assertJsonPath('data.footer_note', 'Выдан без отметки о печати.');

        $this->assertDatabaseHas('certificate_settings', [
            'key' => 'default',
            'signatory_name' => 'Иванов И. И.',
        ]);
    }

    public function test_saving_twice_updates_one_row_instead_of_adding(): void
    {
        $user = $this->withPermission();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/certificates/settings', ['organization' => 'Первый'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/certificates/settings', ['organization' => 'Второй'])
            ->assertOk()
            ->assertJsonPath('data.organization', 'Второй');

        // Таблица-синглтон: вторая запись означала бы, что читается
        // не та строка и правки терялись бы по строкам.
        $this->assertSame(1, CertificateSetting::count());
    }

    public function test_logo_is_uploaded_and_served(): void
    {
        Storage::fake('public');

        $user = $this->withPermission();

        $response = $this->actingAs($user, 'sanctum')
            ->put('/api/certificates/settings', [
                'organization' => 'Центр',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($response['logo_path']);
        Storage::disk('public')->assertExists($response['logo_path']);

        // Имя файла не совпадает с присланным: в нём есть путь или
        // чужая расшифровка.
        $this->assertStringNotContainsString('logo.png', $response['logo_path']);
    }

    public function test_logo_replacement_does_not_leave_old_file(): void
    {
        Storage::fake('public');

        $user = $this->withPermission();

        $first = $this->actingAs($user, 'sanctum')
            ->put('/api/certificates/settings', [
                'logo' => UploadedFile::fake()->image('one.png'),
            ], ['Accept' => 'application/json'])
            ->json('data.logo_path');

        $second = $this->actingAs($user, 'sanctum')
            ->put('/api/certificates/settings', [
                'logo' => UploadedFile::fake()->image('two.png'),
            ], ['Accept' => 'application/json'])
            ->json('data.logo_path');

        // Старый логотип больше не нужен: иначе публичный диск
        // рос бы на каждой правке бланка.
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_logo_can_be_removed(): void
    {
        Storage::fake('public');

        $user = $this->withPermission();

        $path = $this->actingAs($user, 'sanctum')
            ->put('/api/certificates/settings', [
                'logo' => UploadedFile::fake()->image('logo.png'),
            ], ['Accept' => 'application/json'])
            ->json('data.logo_path');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/certificates/settings', ['remove_logo' => true])
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_trainee_cannot_read_or_change_settings(): void
    {
        $trainee = User::factory()->create(['role' => 'Обучаемый']);

        $this->actingAs($trainee, 'sanctum')
            ->getJson('/api/certificates/settings')
            ->assertForbidden();

        $this->actingAs($trainee, 'sanctum')
            ->putJson('/api/certificates/settings', ['organization' => 'Свой'])
            ->assertForbidden();
    }

    public function test_staff_without_permission_is_denied(): void
    {
        // Сотрудник без права: не суперадмин, поэтому проверка идёт
        // по матрице, а не по короткому «администратору можно всё».
        $withoutRight = User::factory()->create(['role' => 'Методист']);

        $this->actingAs($withoutRight, 'sanctum')
            ->putJson('/api/certificates/settings', ['organization' => 'Свой'])
            ->assertForbidden();
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/certificates/settings')->assertUnauthorized();
        $this->putJson('/api/certificates/settings', ['organization' => 'Свой'])->assertUnauthorized();
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $user = $this->withPermission();

        // Логотип печатается на листе; исполняемый или текстовый файл
        // в этом месте — дыра, а не «неудачное изображение».
        $this->actingAs($user, 'sanctum')
            ->put('/api/certificates/settings', [
                'logo' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_certificate_carries_template_values(): void
    {
        CertificateSetting::create([
            'key' => 'default',
            'organization' => 'Учебный центр «Авиатор»',
            'signatory_name' => 'Иванов И. И.',
            'footer_note' => 'Без отметки о печати',
        ]);

        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Петров Пётр']);
        $course = Course::create([
            'title' => 'Авиационная безопасность',
            'short_description' => 'Описание',
            'long_description' => 'Описание',
            'path' => 'cert-template',
            'visible' => true,
        ]);

        $group = \App\Models\Group::factory()->create();
        $user->group_id = $group->id;
        $user->save();

        $record = new \App\Models\Group2learning(['group_id' => $group->id, 'course_id' => $course->id]);
        $record->study_from = now()->subMonth();
        $record->study_to = now()->addMonth();
        $record->save();

        $lesson = \App\Models\Aukstructure::create([
            'course_id' => $course->id,
            'parent_id' => null,
            'title' => 'Урок 1',
            'type' => 1,
            'identifier' => 'CERT-TPL-1',
        ]);

        \App\Models\LessonProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => 100,
            'completed_at' => now(),
        ]);

        // Бланк едет вместе с сертификатом: фронт не должен делать
        // второй запрос и не должен знать про настройки.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/my/certificates/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.organization', 'Учебный центр «Авиатор»')
            ->assertJsonPath('data.signatory_name', 'Иванов И. И.')
            ->assertJsonPath('data.footer_note', 'Без отметки о печати');
    }
}
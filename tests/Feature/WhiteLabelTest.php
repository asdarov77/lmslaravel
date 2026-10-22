<?php

namespace Tests\Feature;

use App\Models\WhiteLabelSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * White-label настройки бренда.
 *
 * Проверяет: загрузку, сохранение, замену файлов, валидацию цветов,
 * 403 для сотрудников без права, возврат CSS-переменных.
 */
class WhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // white_label_settings не связан с users через FK, поэтому
        // RefreshDatabase не чистит её. Очищаем вручную.
        \DB::table('white_label_settings')->truncate();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Администратор']);
    }

    private function instructor(): User
    {
        return User::factory()->create(['role' => 'Инструктор']);
    }

    private function trainee(): User
    {
        return User::factory()->create(['role' => 'Обучаемый']);
    }

    public function test_settings_are_empty_by_default(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/white-label')
            ->assertOk()
            // Сырые значения — дефолты светлой темы
            ->assertJsonPath('data.primary_color', '#1976d2')
            ->assertJsonPath('data.accent_color', '#00bcd4')
            // CSS-переменные отдают dark overrides (фронтенд применяет их при dark mode)
            ->assertJsonPath('data.css_variables.--v-theme-primary', '#64b5f6')
            ->assertJsonPath('data.css_variables.--c-accent', '#4dd0e1');
    }

    public function test_colors_are_saved_and_returned(): void
    {
        $user = $this->admin();

        $payload = [
            'primary_color' => '#ff0000',
            'accent_color' => '#00ff00',
            'gradient_start' => '#ff0000',
            'gradient_end' => '#0000ff',
            'dark_bg' => '#111111',
            'dark_primary' => '#ff5555',
            'dark_accent' => '#55ff55',
        ];

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', $payload)
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#ff0000')
            ->assertJsonPath('data.accent_color', '#00ff00')
            ->assertJsonPath('data.dark_primary', '#ff5555')
            ->assertJsonPath('data.dark_accent', '#55ff55')
            ->assertJsonPath('data.css_variables.--v-theme-primary', '#ff5555')  // dark override
            ->assertJsonPath('data.css_variables.--c-accent', '#55ff55')        // dark override
            ->assertJsonPath('data.css_variables.--c-brand-gradient-1', '#ff0000')
            ->assertJsonPath('data.css_variables.--c-brand-gradient-2', '#0000ff')
            ->assertJsonPath('data.css_variables.--v-theme-bg', '#111111')
            ->assertJsonPath('data.css_variables.--v-theme-primary', '#ff5555')
            ->assertJsonPath('data.css_variables.--c-accent', '#55ff55');

        $this->assertDatabaseHas('white_label_settings', [
            'key' => 'default',
            'primary_color' => '#ff0000',
        ]);
    }

    public function test_organization_fields_are_saved(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', [
                'organization_name' => 'Мой Университет',
                'organization_short' => 'MU',
            ])
            ->assertOk()
            ->assertJsonPath('data.organization_name', 'Мой Университет')
            ->assertJsonPath('data.organization_short', 'MU');
    }

    public function test_logo_upload_and_url(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        $response = $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'logo_light' => UploadedFile::fake()->image('logo.png', 200, 200),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($response['logo_light_url']);
        $path = WhiteLabelSetting::current()->logo_light_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_dark_logo_separate_from_light(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        // Используем разные размеры, чтобы гарантировать разные файлы
        $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'logo_light' => UploadedFile::fake()->image('light.png', 200, 100),
                'logo_dark' => UploadedFile::fake()->image('dark.png', 100, 200),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.logo_light_url', fn ($v) => !empty($v))
            ->assertJsonPath('data.logo_dark_url', fn ($v) => !empty($v));

        $settings = WhiteLabelSetting::current();
        $this->assertNotEquals(
            $settings->logo_light_path,
            $settings->logo_dark_path
        );
    }

    public function test_favicon_upload(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        // Валидатор: mimes:ico,png,svg. PNG проходит.
        $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.favicon_url', fn ($v) => !empty($v));
    }

    public function test_logo_replacement_removes_old_file(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        $first = $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'logo_light' => UploadedFile::fake()->image('first.png'),
            ], ['Accept' => 'application/json'])
            ->json('data.logo_light_path'); // This won't work - API doesn't return path

        $settings = WhiteLabelSetting::current();
        $firstPath = $settings->logo_light_path;

        $second = $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'logo_light' => UploadedFile::fake()->image('second.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $settings->refresh();
        $secondPath = $settings->logo_light_path;

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_remove_logo_flag_clears_file(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'logo_light' => UploadedFile::fake()->image('logo.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $settings = WhiteLabelSetting::current();
        $path = $settings->logo_light_path;
        $this->assertNotNull($path);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['remove_logo-light' => true])
            ->assertOk()
            ->assertJsonPath('data.logo_light_url', null);

        // Принудительно перезагружаем модель
        $settings->refresh();
        $this->assertNull($settings->logo_light_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_remove_favicon_flag_clears_file(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $path = WhiteLabelSetting::current()->favicon_path;
        $this->assertNotNull($path);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['remove_favicon' => true])
            ->assertOk()
            ->assertJsonPath('data.favicon_url', null);

        $this->assertNull(WhiteLabelSetting::current()->favicon_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_invalid_color_format_rejected(): void
    {
        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => 'red'])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => '#ff00'])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => '#ff00000'])
            ->assertStatus(422);

        // Валидный — проходит
        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => '#ff0000'])
            ->assertOk();
    }

    public function test_non_image_upload_rejected(): void
    {
        Storage::fake('public');

        $user = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->put('/api/white-label', [
                'logo_light' => UploadedFile::fake()->create('payload.php', 100, 'application/x-php'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_trainee_can_read_cannot_change(): void
    {
        $trainee = $this->trainee();

        // Чтение разрешено всем авторизованным: бренд нужен для отрисовки UI.
        $this->actingAs($trainee, 'sanctum')
            ->getJson('/api/white-label')
            ->assertOk();

        // Запись требует права white_label.manage.
        $this->actingAs($trainee, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => '#ff0000'])
            ->assertForbidden();
    }

    public function test_staff_without_permission_denied(): void
    {
        // Сотрудник без права: не суперадмин, роль 'Методист' не имеет
        // white_label.manage в матрице.
        $withoutRight = User::factory()->create(['role' => 'Методист']);

        $this->actingAs($withoutRight, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => '#ff0000'])
            ->assertForbidden();
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/white-label')->assertUnauthorized();
        $this->putJson('/api/white-label', ['primary_color' => '#ff0000'])->assertUnauthorized();
    }

    public function test_css_variables_include_all_expected_keys(): void
    {
        $user = $this->admin();

        $vars = $this->actingAs($user, 'sanctum')
            ->getJson('/api/white-label')
            ->json('data.css_variables');

        $expected = [
            '--v-theme-primary', '--v-theme-primary-lighten', '--v-theme-primary-darken',
            '--c-accent', '--c-accent-lighten', '--c-accent-darken',
            '--c-brand-gradient-1', '--c-brand-gradient-2',
            '--v-theme-bg', '--v-theme-surface', '--v-theme-primary', '--c-accent',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $vars, "Отсутствует CSS-переменная: $key");
        }
    }
}
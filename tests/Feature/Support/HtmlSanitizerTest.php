<?php

namespace Tests\Feature\Support;

use App\Models\Aukstructure;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Санитизация материала (WYSIWYG-безопасность).
 *
 * Сохранённый материал не должен иметь путей к исполнению кода:
 * <script>, инлайновые обработчики on* и javascript:-ссылки
 * вырезаются при сохранении. Второй барьер фронтенда —
 * sandbox-атрибут у iframe в плеере.
 */
class HtmlSanitizerTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Feature\AuthenticatesApi;

    public function test_strips_script_onex_handlers_and_javascript_urls(): void
    {
        $dirty = '<p>Глава 1</p><script>alert(1)</script><a onclick="steal()" href="javascript:evil()">ссылка</a><iframe src="x"></iframe><b onerror="x()">ж</b>';

        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('Глава 1', $clean);
    }

    public function test_description_is_sanitized_on_store(): void
    {
        $this->admin();

        $course = \App\Models\Course::factory()->create();

        $this->postJson('/api/aukstructure', [
            'course_id' => $course->id,
            'title' => 'Урок с вредоносным описанием',
            'type' => 3,
            'description' => '<p>текст</p><script>alert(1)</script>',
        ])->assertStatus(201);

        $stored = Aukstructure::where('title', 'Урок с вредоносным описанием')->firstOrFail();
        $this->assertStringNotContainsString('<script', (string) $stored->description);
        $this->assertStringContainsString('<p>текст</p>', (string) $stored->description);
    }
}

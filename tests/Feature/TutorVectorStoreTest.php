<?php

namespace Tests\Feature;

use App\Models\TutorChunk;
use App\Models\TutorMaterial;
use App\Support\Tutor\PostgresVectorStore;
use App\Support\Tutor\SatelliteVectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Выбор хранилища поиска: сателлит (ChromaDB) или Postgres.
 *
 * Проверяется поведение, важное для устойчивости: сателлит не обязан
 * быть. Любой его сбой — не запущен, нет ChromaDB, таймаут — обязан
 * оставить тренажёр работоспособным на полнотекстовом поиске.
 * Проверка с обратной стороны тоже нужна: молчаливый переход на Postgres
 * означал бы, что векторный поиск никогда не включался, и это заметили бы
 * только по качеству вопросов.
 */
class TutorVectorStoreTest extends TestCase
{
    use RefreshDatabase;

    private function material(): TutorMaterial
    {
        $material = TutorMaterial::factory()->create();

        TutorChunk::factory()->create([
            'material_id' => $material->id,
            'seq' => 1,
            'content' => 'Цилиндр закрытия замков левой двери подключен к гидросистеме №1. '
                .'Управление осуществляется от рычага в кабине пилота.',
        ]);

        TutorChunk::factory()->create([
            'material_id' => $material->id,
            'seq' => 2,
            'content' => 'Проверка герметичности цилиндров выполняется раз в 12 месяцев при снятом питании.',
        ]);

        TutorChunk::factory()->create([
            'material_id' => $material->id,
            'seq' => 3,
            'content' => 'Рычаг управления замками расположен на пульте слева от кресла пилота.',
        ]);

        return $material;
    }

    // --- Postgres ------------------------------------------------------

    public function test_postgres_is_always_available(): void
    {
        $store = new PostgresVectorStore();

        $this->assertTrue($store->available(), 'полнотекстовый поиск обязан работать всегда');
        $this->assertSame('postgres-fts', $store->name());
    }

    public function test_postgres_finds_relevant_chunk(): void
    {
        $material = $this->material();

        $hits = (new PostgresVectorStore())->search(
            $material->id,
            'как часто проверяют герметичность',
            3
        );

        $this->assertNotEmpty($hits, 'по ключевым словам фрагмент должен находиться');
        $this->assertSame(2, $hits[0]['seq'], 'первым идёт фрагмент про периодичность проверки');
    }

    public function test_postgres_excludes_used_chunks(): void
    {
        $material = $this->material();

        $hits = (new PostgresVectorStore())->search(
            $material->id,
            'герметичность',
            3,
            [2]
        );

        $this->assertNotContains(2, array_column($hits, 'seq'), 'использованный фрагмент повторно не выдаётся');
    }

    public function test_postgres_falls_back_to_order_when_nothing_matches(): void
    {
        $material = $this->material();

        // Запрос без единого общего слова: вернуть пустую выдачу значило бы
        // сказать «вопросов нет» при заведомо пригодном материале.
        $hits = (new PostgresVectorStore())->search($material->id, 'zzzqqq', 2);

        $this->assertCount(2, $hits);
    }

    public function test_postgres_empty_query_returns_order(): void
    {
        $material = $this->material();

        $hits = (new PostgresVectorStore())->search($material->id, '', 3);

        $this->assertSame([1, 2, 3], array_column($hits, 'seq'));
    }

    // --- Сателлит ------------------------------------------------------

    public function test_satellite_unavailable_without_url(): void
    {
        $store = new SatelliteVectorStore('');

        $this->assertFalse($store->available(), 'без адреса сателлит не используется');
    }

    public function test_satellite_unavailable_when_down(): void
    {
        Http::fake(['*/health' => Http::response('', 500)]);

        $this->assertFalse((new SatelliteVectorStore('http://127.0.0.1:8100'))->available());
    }

    public function test_satellite_available_when_healthy(): void
    {
        Http::fake(['*/health' => Http::response(['service' => 'ok', 'chroma' => true])]);

        $store = new SatelliteVectorStore('http://127.0.0.1:8100');

        $this->assertTrue($store->available());
        $this->assertSame('satellite-chroma', $store->name());
    }

    public function test_satellite_ingest_sends_stable_ids(): void
    {
        Http::fake([
            '*/health' => Http::response(['chroma' => true]),
            '*/ingest' => Http::response(['count' => 2]),
        ]);

        $store = new SatelliteVectorStore('http://127.0.0.1:8100');

        $result = $store->index(7, [
            ['seq' => 1, 'content' => 'первый'],
            ['seq' => 2, 'content' => 'второй'],
        ]);

        $this->assertSame(2, $result['indexed']);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/ingest')) {
                return false;
            }

            $body = json_decode($request->body(), true);

            $this->assertSame('course_7', $body['collection'], 'коллекция создаётся на курс');

            // Идентификатор устойчив: повторная индексация обновляет
            // запись, а не плодит копии.
            $this->assertSame(sha1('7:1'), $body['documents'][0]['id']);

            return true;
        });
    }

    public function test_satellite_search_returns_seq_and_score(): void
    {
        Http::fake([
            '*/health' => Http::response(['chroma' => true]),
            '*/search' => Http::response([
                'results' => [
                    ['metadata' => ['seq' => 3, 'material_id' => 7], 'score' => 0.91],
                    ['metadata' => ['seq' => 1], 'score' => 0.42],
                ],
            ]),
        ]);

        $hits = (new SatelliteVectorStore('http://127.0.0.1:8100'))->search(7, 'рычаг', 5, [1]);

        $this->assertSame(3, $hits[0]['seq']);
        $this->assertSame(0.91, $hits[0]['score']);

        // Исключение отправляется на сторону сателлита: он не должен
        // возвращать уже использованные фрагменты.
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/search')
                && json_decode($request->body(), true)['exclude_seq'] === [1];
        });
    }

    public function test_satellite_failure_does_not_throw(): void
    {
        Http::fake([
            '*/health' => Http::response(['chroma' => true]),
            '*/search' => Http::response('boom', 500),
            '*/ingest' => Http::response('boom', 500),
        ]);

        $store = new SatelliteVectorStore('http://127.0.0.1:8100');

        // Сбой сателлита не должен ронять индексацию или генерацию:
        // вызывающий код переключается на Postgres.
        $this->assertSame([], $store->search(7, 'что угодно', 3));
        $this->assertSame(0, $store->index(7, [['seq' => 1, 'content' => 'x']])['indexed']);
    }

    // --- Переключение --------------------------------------------------

    public function test_indexer_picks_satellite_when_available(): void
    {
        config(['tutor.satellite.url' => 'http://127.0.0.1:8100']);

        Http::fake(['*/health' => Http::response(['chroma' => true])]);

        $this->assertInstanceOf(SatelliteVectorStore::class, app(\App\Support\Tutor\TutorMaterialIndexer::class)->store());
    }

    public function test_indexer_falls_back_to_postgres(): void
    {
        // Молчаливый переход на Postgres означал бы, что векторный поиск
        // никогда не включался, и это заметили бы только по качеству.
        config(['tutor.satellite.url' => '']);

        $this->assertInstanceOf(PostgresVectorStore::class, app(\App\Support\Tutor\TutorMaterialIndexer::class)->store());
    }
}
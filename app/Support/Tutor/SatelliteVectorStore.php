<?php

namespace App\Support\Tutor;

use App\Models\TutorChunk;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Векторный поиск через Python-сателлит с ChromaDB (Вариант A).
 *
 * Нужен потому, что на машине пользователя нет расширения pgvector, а
 * ставить его — отдельная задача администрирования Postgres. ChromaDB
 * внутри Python-сервиса решает то же самое без вмешательства в БД.
 *
 * Коллекция СОЗДАЁТСЯ НА КУРС. Права доступа при этом не раздаются
 * сателлитом: он получает только material_id уже проверенного материала
 * и отвечает за поиск по тексту. Отбор «что вообще можно искать» остаётся
 * в Laravel, где живут права.
 *
 * Устойчивость: любой сбой сателлита (не запущен, нет ChromaDB, таймаут)
 * НЕ ломает тренажёр — вызывающий код переключается на полнотекстовый
 * поиск. Иначе одна не установленная Python-зависимость выключала бы
 * всю функцию.
 */
final class SatelliteVectorStore implements TutorVectorStore
{
    private ?bool $healthy = null;

    public function __construct(
        private readonly string $url = '',
        private readonly int $timeout = 60,
    ) {
    }

    public function name(): string
    {
        return 'satellite-chroma';
    }

    public function available(): bool
    {
        if ($this->url === '') {
            return false;
        }

        if ($this->healthy !== null) {
            return $this->healthy;
        }

        try {
            $response = Http::timeout(5)->get($this->url.'/health');

            $this->healthy = $response->ok()
                && (bool) $response->json('chroma');
        } catch (ConnectionException $e) {
            $this->healthy = false;
        }

        return $this->healthy;
    }

    public function index(int $materialId, array $chunks): array
    {
        if (! $this->available()) {
            return ['indexed' => 0, 'backend' => $this->name()];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->url.'/ingest', [
                    'collection' => $this->collection($materialId),
                    'documents' => array_map(
                        fn (array $chunk) => [
                            // Идентификатор устойчив: повторная индексация
                            // того же фрагмента обновляет запись, а не
                            // плодит копии. Из sha1, как в инструкции RAG.
                            'id' => sha1($materialId.':'.$chunk['seq']),
                            'text' => $chunk['content'],
                            'metadata' => ['material_id' => $materialId, 'seq' => $chunk['seq']],
                        ],
                        $chunks
                    ),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Сателлит недоступен при индексации, остаёмся на полнотекстовом поиске', [
                'material_id' => $materialId,
                'error' => $e->getMessage(),
            ]);

            return ['indexed' => 0, 'backend' => $this->name()];
        }

        if ($response->failed()) {
            Log::warning('Сателлит отклонил индексацию', [
                'material_id' => $materialId,
                'status' => $response->status(),
            ]);

            return ['indexed' => 0, 'backend' => $this->name()];
        }

        return [
            'indexed' => (int) $response->json('count', count($chunks)),
            'backend' => $this->name(),
        ];
    }

    public function search(int $materialId, string $query, int $limit, array $excludeSeq = []): array
    {
        if (! $this->available()) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)->post($this->url.'/search', [
                'collection' => $this->collection($materialId),
                'query' => $query,
                'k' => $limit,
                // Исключение уже использованных фрагментов делает на
                // стороне сателлита: вернуть их обратно он не должен,
                // иначе сессия зациклится на одном и том же вопросе.
                'exclude_seq' => $excludeSeq,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Сателлит недоступен при поиске', ['error' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            return [];
        }

        $out = [];

        foreach ((array) $response->json('results', []) as $row) {
            $seq = (int) ($row['metadata']['seq'] ?? 0);

            if ($seq > 0) {
                $out[] = ['seq' => $seq, 'score' => (float) ($row['score'] ?? 0)];
            }
        }

        return $out;
    }

    /**
     * Имя коллекции на курс.
     *
     * Имя приводится к безопасному виду: Chroma не принимает произвольные
     * строки, а идентификатор курса — целое число, так что риска нет.
     */
    private function collection(int $materialId): string
    {
        return 'course_'.$materialId;
    }
}

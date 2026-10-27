<?php

namespace App\Support\Tutor;

use App\Models\Course;
use App\Models\TutorChunk;
use App\Models\TutorMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Индексация материала: текст → фрагменты → БД.
 *
 * Идемпотентна: повторный запуск пересобирает фрагменты с нуля, а не
 * добавляет дубли. Иначе после двух-трёх перезаливок курса тренажёр
 * предлагал бы один и тот же вопрос дважды, и дедупликация начала бы
 * работать против самой себя.
 *
 * Удаление старых фрагментов каскадом уносит вопросы, которые по ним
 * были созданы, и это правильно: вопрос, привязанный к тексту, которого
 * больше нет, нельзя предлагать. По той же причине сессии не
 * восстанавливаются на новых фрагментах «по номерам».
 */
final class TutorMaterialIndexer
{
    public function __construct(
        private readonly TutorMaterialExtractor $extractor,
        private readonly TutorChunker $chunker,
        private readonly TutorClient $client,
    ) {
    }

    /**
     * Хранилище для поиска: сателлит, если он настроен и отвечает,
     * иначе полнотекстовый поиск Postgres.
     *
     * Проверка «отвечает» здесь, а не единожды: сателлит может подняться
     * после индексации, и тогда переключение должно произойти само.
     */
    public function store(): TutorVectorStore
    {
        $satellite = new SatelliteVectorStore(
            (string) config('tutor.satellite.url', ''),
            (int) config('tutor.satellite.timeout', 60)
        );

        return $satellite->available()
            ? $satellite
            : new PostgresVectorStore();
    }

    /**
     * Пересобрать материалы курса — по одному на каждую специальность.
     *
     * Материал принадлежит паре (курс, специальность), потому что группа
     * записывается на курс в рамках своей специальности, а один курс
     * привязан к нескольким сразу. Материал без специальности не имеет
     * смысла: по нему нельзя решить, кому показывать.
     *
     * Текст курса читается ОДИН раз и раздаётся всем материалам: файлы
     * курса общие для специальностей, повторное чтение с диска было бы
     * лишней работой на курсе с шестью специальностями.
     *
     * @return array{material: TutorMaterial, chunks: int, characters: int, materials: int}
     */
    public function indexCourse(Course $course, ?string $title = null): array
    {
        $categories = $course->categories()->pluck('categories.id')->filter()->unique()->values();

        if ($categories->isEmpty()) {
            // Курс не привязан ни к одной специальности: материала для
            // него не существует, и создавать материал «на всех» нельзя —
            // это вернуло бы ту самую дыру, ради устранения которой всё
            // и затевалось.
            return ['material' => null, 'chunks' => 0, 'characters' => 0, 'materials' => 0];
        }

        $extracted = $this->extractor->fromCourse($course);
        $text = $extracted['text'] ?? '';

        $first = null;
        $chunks = 0;
        $characters = mb_strlen($text);
        $built = 0;

        foreach ($categories as $categoryId) {
            $material = TutorMaterial::updateOrCreate(
                ['course_id' => $course->id, 'category_id' => $categoryId, 'source' => 'course'],
                [
                    'title' => $title ?? ($course->title ?? 'Курс'),
                    'status' => TutorMaterial::STATUS_PENDING,
                    'error' => null,
                ]
            );

            $result = $this->rebuild($material, null, $text, $extracted);

            $first ??= $result['material'];
            $chunks = $result['chunks'];
            $built++;
        }

        return [
            'material' => $first,
            'chunks' => $chunks,
            'characters' => $characters,
            'materials' => $built,
        ];
    }

    /**
     * Пересобрать конкретный каталог материала.
     *
     * Нужен для файлов, загруженных отдельно от курса: у них нет
     * aircraft/course_path, и извлечение идёт по явному каталогу.
     */
    public function indexDirectory(Course $course, string $directory, string $title, ?int $categoryId = null): array
    {
        $material = TutorMaterial::updateOrCreate(
            ['course_id' => $course->id, 'category_id' => $categoryId, 'source' => 'upload'],
            [
                'title' => $title,
                'status' => TutorMaterial::STATUS_PENDING,
                'error' => null,
            ]
        );

        $material->setAttribute('__directory', $directory);

        return $this->rebuild($material, $directory);
    }

    /**
     * Основная работа: извлечь, нарезать, сохранить.
     *
     * @return array{material: TutorMaterial, chunks: int, characters: int}
     */
    public function rebuild(
        TutorMaterial $material,
        ?string $directory = null,
        ?string $readyText = null,
        ?array $readyResult = null
    ): array {
        try {
            if ($readyText !== null) {
                // Текст уже прочитан для этого курса: переиспользуем его,
                // чтобы не читать файлы курса по числу специальностей.
                $result = $readyResult ?? ['text' => $readyText, 'files' => 0, 'skipped' => 0];
            } elseif ($directory !== null) {
                $result = $this->extractor->fromDirectory($directory);
            } else {
                $course = $material->course()->first();

                if ($course === null) {
                    throw new \RuntimeException('Материал ссылается на несуществующий курс');
                }

                $result = $this->extractor->fromCourse($course);
            }
        } catch (Throwable $e) {
            $material->forceFill([
                'status' => TutorMaterial::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            throw $e;
        }

        $text = $result['text'] ?? '';
        $characters = mb_strlen($text);

        // Пустой материал — не ошибка. Курс может состоять из картинок,
        // и «вопросов нет» здесь честнее, чем бесконечные попытки.
        if (trim($text) === '') {
            $material->chunks()->delete();
            $material->forceFill([
                'status' => TutorMaterial::STATUS_EMPTY,
                'chunks_count' => 0,
                'error' => null,
            ])->save();

            return ['material' => $material->fresh(), 'chunks' => 0, 'characters' => 0];
        }

        $pieces = $this->chunker->split($text);

        DB::transaction(function () use ($material, $pieces) {
            $material->chunks()->delete();

            foreach ($pieces as $seq => $piece) {
                TutorChunk::create([
                    'material_id' => $material->id,
                    'seq' => $seq,
                    'content' => $piece['content'],
                    'token_count' => $piece['token_count'],
                    'indexed_at' => now(),
                ]);
            }

            $material->forceFill([
                'status' => TutorMaterial::STATUS_INDEXED,
                'chunks_count' => count($pieces),
                'error' => null,
            ])->save();
        });

        // Поисковый индекс. Без сателлита это tsvector, с сателлитом —
        // вектора в ChromaDB. Оба варианта рабочие.
        $store = $this->store();
        $indexed = $store->index(
            $material->id,
            $material->chunks()->get(['seq', 'content'])->map(
                fn ($chunk) => ['seq' => (int) $chunk->seq, 'content' => (string) $chunk->content]
            )->all()
        );

        // Эмбеддинги в Postgres остаются необязательными: собирать их
        // имеет смысл только если сателлита нет, а он всё равно нужен
        // для импорта. Показываем, чем закончилось, в логе.
        Log::info('Материал проиндексирован', [
            'material_id' => $material->id,
            'chunks' => $result['chunks'] ?? count($pieces),
            'search_backend' => $indexed['backend'],
            'indexed' => $indexed['indexed'],
        ]);

        $this->attachEmbeddings($material);

        return [
            'material' => $material->fresh(),
            'chunks' => count($pieces),
            'characters' => $characters,
        ];
    }

    /**
     * Посчитать и сохранить эмбеддинги, если модель установлена.
     *
     * Ошибка здесь не должна ломать индексацию: эмбеддинги ускоряют
     * отбор фрагментов, но не являются условием работы тренажёра.
     */
    private function attachEmbeddings(TutorMaterial $material): void
    {
        if (! $this->client->embedAvailable()) {
            return;
        }

        try {
            $chunks = $material->chunks()->get();
            $vectors = $this->client->embed(array_map(fn ($c) => $c->content, $chunks->all()));

            if ($vectors === null || ! is_array($vectors)) {
                return;
            }

            $single = isset($vectors[0]) && ! is_array($vectors[0]) ? $vectors : null;

            foreach ($chunks as $index => $chunk) {
                $vector = $single ?? ($vectors[$index] ?? null);

                if (is_array($vector)) {
                    $chunk->forceFill(['embedding' => array_values(array_map('floatval', $vector))])->save();
                }
            }
        } catch (Throwable $e) {
            Log::warning('Не удалось посчитать эмбеддинги, продолжаем без них', [
                'material_id' => $material->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
<?php

namespace App\Jobs;

use App\Models\TutorChunk;
use App\Models\TutorItem;
use App\Models\TutorSession;
use App\Support\Tutor\TutorClient;
use App\Support\Tutor\TutorMaterialIndexer;
use App\Support\Tutor\TutorQuestionGrounding;
use App\Support\Tutor\TutorUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Догенерация вопросов по фрагментам в фоне.
 *
 * Почему очередь, а не ответ сразу: генерация пачки на CPU занимает
 * секунды-десятки секунд (замер на qwen3.5:9b — около 11 с на три
 * вопроса вместе с back-check). Клик по «начать тренинг» не должен
 * ждать модель, поэтому запас вопросов делается заранее, а пользователь
 * получает его мгновенно.
 *
 * Вопросы пишутся пачками по фрагментам, а не «всё сразу»: один
 * фрагмент может не дать ни одного пригодного вопроса (например, текст
 * состоит из подписей к рисункам), и весь запуск из-за него провалился бы.
 *
 * shouldBeUnique: два параллельных запуска на одну сессию удваивали бы
 * расход и наполняли бы вопросами, которые тут же показываются.
 */
class GenerateTutorQuestions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $wanted,
    ) {
        $this->onQueue((string) config('tutor.queue', 'default'));
    }

    /**
     * Сколько вопросов нужно, чтобы не предлагать второй раз.
     *
     * Запас сверху: часть вопросов отбрасывается проверкой, и без запаса
     * сессия упиралась бы в «вопросы кончились» через несколько ответов.
     */
    public function handle(
        TutorQuestionGrounding $grounding,
        TutorClient $client,
    ): void {
        try {
            $this->generate($grounding, $client);
        } finally {
            // Метка снимается всегда, в том числе при падении движка.
            // Иначе интерфейс бесконечно показывал бы «готовятся
            // вопросы» после недоступного Ollama.
            TutorSession::whereKey($this->sessionId)->update(['generation_started_at' => null]);
        }
    }

    private function generate(
        TutorQuestionGrounding $grounding,
        TutorClient $client,
    ): void {
        $session = TutorSession::with('material.chunks')->find($this->sessionId);

        if ($session === null) {
            Log::warning('Генерация пропущена: сессия не найдена', ['session_id' => $this->sessionId]);

            return;
        }

        if (! $client->enabled()) {
            Log::info('Генерация пропущена: тренажёр выключен');

            return;
        }

        $wanted = max(1, (int) config('tutor.batch_size', 8)) + (int) ceil($this->wanted / 2);
        $chunkIds = $this->chunkOrder($session);

        $known = $this->knownFingerprints($session);
        $created = 0;
        $failed = false;

        /*
         * Предел на число фрагментов за один запуск.
         *
         * Без него задача не завершалась практически: на курсе 276
         * фрагментов, а слабая модель даёт пригодный вопрос примерно из
         * двух попыток. Вместо одного вызова получался обход сотен
         * фрагментов — очередь висела десятками минут и выглядела как
         * зависшая генерация (воспроизведено: таймаут 15 минут).
         *
         * Ограниченный запуск делает меньше за один раз, но следующий
         * запуск продолжает с того места, где остановился: уже
         * использованные фрагменты помечены в context_window и в
         * chunkOrder уходят в конец.
         */
        $maxChunks = max(1, (int) config('tutor.max_chunks_per_job', 8));

        foreach (array_slice($chunkIds, 0, $maxChunks) as $chunkId) {
            if ($created >= $wanted) {
                break;
            }

            $chunk = TutorChunk::find($chunkId);

            if ($chunk === null || trim($chunk->content) === '') {
                continue;
            }

            $missing = $wanted - $created;

            try {
                $result = $grounding->generate(
                    $chunk->content,
                    $this->types($session),
                    $missing,
                    $known
                );
            } catch (TutorUnavailableException $e) {
                // Движок упал посреди генерации. Прекращаем, а не
                // продолжаем по остальным фрагментам: если Ollama недоступен,
                // он недоступен для всех, и десяток повторов лишь
                // растянут ожидание.
                Log::warning('Генерация прервана: движок недоступен', [
                    'session_id' => $this->sessionId,
                    'error' => $e->getMessage(),
                ]);

                $failed = true;

                break;
            } catch (Throwable $e) {
                Log::error('Генерация вопросов упала', [
                    'chunk_id' => $chunkId,
                    'error' => $e->getMessage(),
                ]);

                $failed = true;

                break;
            }

            /*
             * Сессию могли удалить, пока шла генерация (тесты чистят за
             * собой, метододист мог удалить группу). Раньше вставка
             * вопросов падала по внешнему ключу, и в лог уходила ошибка
             * для пользователя, который уже не существует. Здесь
             * останавливаемся тихо: результат всё равно никому не
             * покажут.
             */
            if ($session->fresh() === null) {
                Log::info('Генерация остановлена: сессия удалена во время работы', [
                    'session_id' => $session->id,
                ]);

                return;
            }

            foreach ($result['items'] as $item) {
                if ($created >= $wanted) {
                    break;
                }

                TutorItem::create([
                    'session_id' => $session->id,
                    'chunk_id' => $chunk->id,
                    'qtype' => $item['qtype'],
                    'question' => $item['question'],
                    'options' => $item['options'],
                    'reference_answer' => $item['reference_answer'],
                    'source_quote' => $item['source_quote'],
                    'fingerprint' => $item['fingerprint'],
                    'backcheck_passed' => $item['backcheck_passed'],
                ]);

                $known[$item['fingerprint']] = true;
                $created++;
            }
        }

        Log::info('Генерация вопросов завершена', [
            'session_id' => $this->sessionId,
            'created' => $created,
            'wanted' => $wanted,
            'chunks_tried' => min($maxChunks, count($chunkIds)),
            'failed' => $failed,
        ]);
    }

    /**
     * Порядок фрагментов.
     *
     * Неиспользованные идут первыми, чтобы вопросы не повторялись. Внутри
     * этого набора порядок задаёт ПОИСК по тексту уже заданных вопросов:
     * так сессия движется по темам, связанным с тем, что обучаемый уже
     * отвечал, а не прыгает по материалу случайно. Это и есть «нарастающий
     * контекст» из плана — вопросы приходят по нарастающей вокруг уже
     * затронутого.
     *
     * Если сателлит недоступен, поиск лексический и при пустом тексте
     * запроса возвращает первые непоказанные фрагменты: деградация
     * предсказуемая, а не «вопросов нет».
     *
     * @return array<int,int>
     */
    private function chunkOrder(TutorSession $session): array
    {
        $material = $session->material;
        $chunks = $material?->chunks ?? collect();
        $used = collect($session->context_window ?? [])->pluck('chunk_id')->all();

        $fresh = $chunks->reject(fn ($c) => in_array($c->id, $used, true));
        $rest = $chunks->filter(fn ($c) => in_array($c->id, $used, true));

        if ($fresh->isEmpty()) {
            return $rest->pluck('id')->all();
        }

        $store = app(TutorMaterialIndexer::class)->store();

        $hits = $store->search(
            (int) $material->id,
            $this->contextText($session),
            min($fresh->count(), 16),
            $fresh->pluck('seq')->all()
        );

        if ($hits === []) {
            return array_values(array_merge($fresh->pluck('id')->all(), $rest->pluck('id')->all()));
        }

        // Порядок берём из выдачи поиска: id нужен, а отдача содержит seq.
        $bySeq = $fresh->keyBy('seq');
        $ordered = [];

        foreach ($hits as $hit) {
            $chunk = $bySeq->get($hit['seq']);

            if ($chunk !== null) {
                $ordered[] = $chunk->id;
            }
        }

        // Всё, что поиск не вернул, дописываем в конец: выдача могла
        // оказаться короче фрагментов материала.
        $restIds = $fresh->pluck('id')->reject(fn ($id) => in_array($id, $ordered, true))->all();

        return array_values(array_merge($ordered, $restIds, $rest->pluck('id')->all()));
    }

    /**
     * Текст запроса для поиска: формулировки уже заданных вопросов.
     *
     * Берутся именно вопросы, а не фрагменты: вопрос — это то, чем
     * обучаемый занят прямо сейчас, и вокруг него и нужен материал.
     */
    private function contextText(TutorSession $session): string
    {
        $asked = $session->items()
            ->orderByDesc('id')
            ->limit(3)
            ->pluck('question');

        // toArray() обязателен: implode() в PHP 8 не принимает
        // Collection, и поиск контекста падал TypeError.
        return trim(implode(' ', $asked->toArray()));
    }

    /**
     * Отпечатки вопросов, которые сессия уже знает.
     *
     * @return array<string,bool>
     */
    private function knownFingerprints(TutorSession $session): array
    {
        $session->loadMissing('items');

        return $session->items
            ->pluck('fingerprint')
            ->filter()
            ->mapWithKeys(fn ($fp) => [$fp => true])
            ->all();
    }

    /** @return array<int,string> */
    private function types(TutorSession $session): array
    {
        return (array) config('tutor.default_types', ['mcq', 'short']);
    }
}
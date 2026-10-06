<?php

namespace App\Console\Commands;

use App\Models\TutorChunk;
use App\Support\Tutor\TutorClient;
use App\Support\Tutor\TutorQuestionGrounding;
use Illuminate\Console\Command;

/**
 * Замер качества выдачи тренажёра.
 *
 * Зачем отдельная команда, а не тест: качество зависит от модели,
 * версии Ollama и загрузки процессора, поэтому проверять его в CI
 * бессмысленно — он «мигает» без всяких изменений в коде. Это
 * инструмент подбора модели: запустить на своём железе и посмотреть
 * цифры, а не «упал ли тест».
 *
 * Что измеряется (критерии приёмки из плана):
 *  - доля принятых вопросов — сколько модель смогла уложиться в схему
 *    и пройти проверки опоры на текст;
 *  - скорость — ради этого всё и затевалось, вопросы на CPU;
 *  - честный отказ — сколько фрагментов не дали ни одного вопроса.
 *
 * Отдельно печатается предупреждение, если принято ноль: чаще всего
 * это не поломка, а слишком маленькая модель.
 */
class TutorQualityCheck extends Command
{
    protected $signature = 'tutor:quality
                            {--chunks=5 : сколько фрагментов проверить}
                            {--count=5 : сколько вопросов просить на фрагмент}
                            {--material= : id материала, иначе берётся первый готовый}';

    protected $description = 'Замерить долю пригодных вопросов и скорость генерации';

    public function handle(TutorQuestionGrounding $grounding, TutorClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('Тренажёр выключен. Включите его в настройках или TUTOR_ENABLED=true.');

            return self::FAILURE;
        }

        $health = $client->health();

        if (! ($health['available'] ?? false)) {
            $this->error('Нейродвижок недоступен: '.($health['error'] ?? 'нет ответа'));
            $this->line('Ожидается Ollama на '.$client->url());

            return self::FAILURE;
        }

        if (! ($health['model_present'] ?? false)) {
            $this->error('Модель '.$client->model().' не установлена.');
            $this->line('Установите: ollama pull '.$client->model());

            return self::FAILURE;
        }

        $chunks = $this->chunks((int) $this->option('chunks'));

        if ($chunks->isEmpty()) {
            $this->error('Нет проиндексированных фрагментов. Сначала проиндексируйте материал.');

            return self::FAILURE;
        }

        $perChunk = max(1, (int) $this->option('count'));

        $this->line('Модель: <info>'.$client->model().'</info>');
        $this->line('Фрагментов: '.$chunks->count().' | просим по '.$perChunk.' вопросов');
        $this->line('Back-check: '.(config('tutor.backcheck') ? 'включён' : 'выключен'));
        $this->newLine();

        $asked = 0;
        $accepted = 0;
        $rejectedReasons = [];
        $elapsed = 0.0;
        $silentChunks = 0;

        foreach ($chunks as $chunk) {
            $started = microtime(true);

            try {
                $result = $grounding->generate($chunk->content, (array) config('tutor.default_types'), $perChunk);
            } catch (\Throwable $e) {
                $this->warn(sprintf('фрагмент %d: ошибка движка — %s', $chunk->seq, $e->getMessage()));

                continue;
            }

            $took = microtime(true) - $started;
            $elapsed += $took;
            $asked += $perChunk;
            $accepted += count($result['items']);

            foreach ($result['rejected'] as $reason) {
                $rejectedReasons[] = $reason;
            }

            if ($result['items'] === []) {
                $silentChunks++;
            }

            $this->line(sprintf(
                '  фрагмент %-5d %5.1fс  принято %d из %d',
                $chunk->seq,
                $took,
                count($result['items']),
                $perChunk
            ));
        }

        $percent = $asked > 0 ? round($accepted / $asked * 100) : 0;

        $this->newLine();
        $this->line('Принято: <info>'.$accepted.' из '.$asked.' ('.$percent.'%)</info>');
        $this->line('Среднее время на фрагмент: '.($chunks->count() > 0 ? round($elapsed / $chunks->count(), 1) : 0).'с');
        $this->line('Фрагментов без вопросов: '.$silentChunks.' из '.$chunks->count());

        if ($rejectedReasons !== []) {
            $counts = array_count_values(array_map(
                fn (string $r) => str_contains($r, 'back-check') ? 'back-check' : 'поля ответа',
                $rejectedReasons
            ));

            foreach ($counts as $reason => $count) {
                $this->line('  отклонено ('.$reason.'): '.$count);
            }
        }

        $this->newLine();

        if ($accepted === 0) {
            $this->error('Ни одного пригодного вопроса. По замерам это признак модели меньше 3B.');
            $this->line('Проверьте модель: TUTOR_MODEL, затем ollama pull <имя>.');

            return self::FAILURE;
        }

        if ($percent < 40) {
            $this->warn('Доля ниже 40%: тренажёр работает, но вопросы будут готовиться долго.');
            $this->line('Что помогает: qwen3:4b вместо меньших моделей, TUTOR_MAX_CHUNKS_PER_JOB=4, batch_size=8.');

            return self::SUCCESS;
        }

        $this->info('Доля пригодных вопросов в рабочем диапазоне.');

        return self::SUCCESS;
    }

    private function chunks(int $limit)
    {
        $query = TutorChunk::query()->orderBy('material_id')->orderBy('seq');

        if ($this->option('material')) {
            $query->where('material_id', (int) $this->option('material'));
        } else {
            $query->whereIn(
                'material_id',
                TutorChunk::query()->distinct()->orderBy('material_id')->limit(1)
                    ->pluck('material_id')
            );
        }

        return $query->limit($limit)->get();
    }
}
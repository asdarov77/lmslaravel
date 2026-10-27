<?php

namespace App\Console\Commands;

use App\Models\TutorChunk;
use App\Models\TutorItem;
use App\Models\TutorSession;
use Illuminate\Console\Command;

/**
 * Подготовка вопросов без модели — для демонстраций и сквозных тестов.
 *
 * Зачем. Генерация вопросов стохастична: даже на хорошей модели выброс по
 * фрагменту гуляет от 0 до 5 пригодных вопросов. Проверять этим интерфейс
 * нельзя — он «мигал» между пройденным и пропущенным без единого изменения
 * в коде. Качество выдачи измеряется отдельно (`tutor:quality`), а здесь
 * нужен предсказуемый вопрос.
 *
 * Вопросы строятся по шаблону из текста фрагмента: берётся предложение с
 * числом и составляется вопрос о нём. Это НЕ вопросы модели — они помечены
 * backcheck_passed = null и предназначены для показа интерфейса.
 *
 * В production команда отказывается работать: массовое появление шаблонных
 * вопросов в боевой базе выглядело бы как результат работы модели.
 */
class TutorSeedDemoQuestions extends Command
{
    protected $signature = 'tutor:seed-demo
                            {material : идентификатор материала}
                            {--count=6 : сколько вопросов на сессию}';

    protected $description = 'Подготовить шаблонные вопросы для активных сессий (демо и тесты)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Команда недоступна в production.');

            return self::FAILURE;
        }

        $materialId = (int) $this->argument('material');
        $perSession = max(1, (int) $this->option('count'));

        $sessions = TutorSession::where('material_id', $materialId)
            ->where('status', TutorSession::STATUS_ACTIVE)
            ->get();

        if ($sessions->isEmpty()) {
            $this->warn('Активных сессий по материалу '.$materialId.' нет.');

            return self::SUCCESS;
        }

        $chunks = TutorChunk::where('material_id', $materialId)->orderBy('seq')->get();

        if ($chunks->isEmpty()) {
            $this->error('Материал не проиндексирован: сначала tutor:index или индексация из UI.');

            return self::FAILURE;
        }

        $created = 0;

        foreach ($sessions as $session) {
            $made = 0;

            foreach ($chunks as $chunk) {
                if ($made >= $perSession) {
                    break;
                }

                $question = $this->fromChunk($chunk->content);

                if ($question === null) {
                    continue;
                }

                TutorItem::create([
                    'session_id' => $session->id,
                    'chunk_id' => $chunk->id,
                    'qtype' => 'mcq',
                    'question' => $question['question'],
                    'options' => $question['options'],
                    'reference_answer' => $question['reference'],
                    'source_quote' => $question['quote'],
                    // null, а не true: это не подтверждено моделью.
                    'backcheck_passed' => null,
                    'fingerprint' => hash('sha256', $question['question']),
                ]);

                $made++;
                $created++;
            }

            $this->line('сессия '.$session->id.': добавлено '.$made);
        }

        $this->info('Всего вопросов: '.$created);

        return self::SUCCESS;
    }

    /**
     * Вопрос по предложению с числом.
     *
     * Число нужно, чтобы варианты ответа отличались друг от друга и
     * проверка ответа имела смысл. Предложение цитируется дословно, так
     * что проверка опоры на текст проходит.
     *
     * @return array{question:string,options:array<int,string>,reference:string,quote:string}|null
     */
    private function fromChunk(string $content): ?array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $content) ?: [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if (mb_strlen($sentence) < 30 || mb_strlen($sentence) > 220) {
                continue;
            }

            if (! preg_match('/\d+/u', $sentence, $m)) {
                continue;
            }

            $number = $m[0];

            if ((int) $number < 2) {
                // Ответ «1» или «0» в вариантах выглядит искусственно.
                continue;
            }

            $reference = (string) $number;
            $offset = max(1, (int) $number - 1);
            $above = (string) ($offset + 2);
            $below = (string) max(1, $offset - 1);

            $options = array_values(array_unique([$reference, $above, $below, (string) ($offset + 10)]));

            if (count($options) < 3) {
                continue;
            }

            // Перемешивание детерминированное: одинаковый вход даёт
            // одинаковый вопрос, иначе демо «мигает» между прогонами.
            sort($options);

            return [
                'question' => 'Какое значение указано в следующем предложении: «'.mb_substr($sentence, 0, 120).'»?',
                'options' => $options,
                'reference' => $reference,
                'quote' => $sentence,
            ];
        }

        return null;
    }
}
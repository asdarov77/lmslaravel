<?php

namespace App\Support\Tutor;

use App\Models\TutorItem;
use App\Models\TutorResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Проверка ответа в тренажёре.
 *
 * Разделение на детерминированную и модельную проверку — не из
 * экономии, а из надёжности:
 *
 *  - mcq и short сверяются кодом. Модель здесь не нужна и была бы худшим
 *    выбором: она «помогает» и засчитывает неверный ответ, а вопрос
 *    с вариантами имеет однозначный правильный вариант.
 *  - open оценивается моделью против ЭТАЛОНА И ЦИТАТЫ, а не против
 *    «своих знаний». Ответ «предохранитель ПП-5 защищает двигатель» к
 *    вопросу о периодичности проверки — верный по смыслу факт, но не
 *    ответ на заданный вопрос, и модель по своим знаниям его зачтёт.
 *    Поэтому модель получает эталон и обязана оценивать соответствие
 *    именно ему.
 *
 * Ни один из путей не пишет в exam_attempts: тренажёр не влияет на
 * аттестацию, и оценка здесь — про обучение.
 */
final class TutorAnswerGrader
{
    public function __construct(private readonly TutorClient $client)
    {
    }

    /**
     * Проверить ответ и вернуть вердикт.
     *
     * @return array{verdict: string, score: ?float, feedback: ?string, meta: array<string,mixed>}
     */
    public function grade(TutorItem $item, ?string $answer): array
    {
        $answer = trim((string) $answer);

        if ($answer === '') {
            return [
                'verdict' => TutorResponse::VERDICT_UNGRADED,
                'score' => null,
                'feedback' => null,
                'meta' => ['reason' => 'пустой ответ'],
            ];
        }

        return match ($item->qtype) {
            'mcq' => $this->gradeChoice($item, $answer),
            'short' => $this->gradeShort($item, $answer),
            'open' => $this->gradeOpen($item, $answer),
            default => [
                'verdict' => TutorResponse::VERDICT_UNGRADED,
                'score' => null,
                'feedback' => null,
                'meta' => ['reason' => 'неизвестный тип вопроса: '.$item->qtype],
            ],
        };
    }

    /**
     * Вариант ответа: сравнение текста выбранного варианта с эталоном.
     *
     * Пользователь присылает индекс или сам текст: клиент отдаёт то,
     * что удобно, а серверная проверка не должна зависеть от того, что
     * прислал браузер. Текст важнее индекса — индекс можно подделать.
     *
     * @return array{verdict: string, score: ?float, feedback: ?string, meta: array<string,mixed>}
     */
    private function gradeChoice(TutorItem $item, string $answer): array
    {
        $options = array_values($item->options ?? []);
        $reference = (string) $item->reference_answer;

        $chosen = $answer;

        if (is_numeric($answer) && isset($options[(int) $answer])) {
            $chosen = $options[(int) $answer];
        }

        $correct = $this->same($chosen, $reference);

        return [
            'verdict' => $correct ? TutorResponse::VERDICT_CORRECT : TutorResponse::VERDICT_WRONG,
            'score' => $correct ? 1.0 : 0.0,
            'feedback' => $correct ? null : null,
            'meta' => [
                'method' => 'compare',
                'matched' => $correct,
            ],
        ];
    }

    /**
     * Короткий ответ: сравнение по нормализованному тексту.
     *
     * Перед сравнением убираются артикль-подобные слова и единицы
     * измерения: ответ «6 месяцев» и эталон «раз в 6 месяцев» — одно и
     * то же, но посимвольно не совпадают, и пользователь был бы озадачен.
     * Числа при этом сохраняются: «5 А» и «6 А» — разные ответы.
     *
     * @return array{verdict: string, score: ?float, feedback: ?string, meta: array<string,mixed>}
     */
    private function gradeShort(TutorItem $item, string $answer): array
    {
        $reference = (string) $item->reference_answer;

        if ($this->same($this->normalizeShort($answer), $this->normalizeShort($reference))) {
            return [
                'verdict' => TutorResponse::VERDICT_CORRECT,
                'score' => 1.0,
                'feedback' => null,
                'meta' => ['method' => 'compare'],
            ];
        }

        // Не совпало. Прежде чем ставить «неверно», спрашиваем модель:
        // короткий ответ часто верен по смыслу, но написан иначе
        // («полгода» против «раз в 6 месяцев»).
        $verdict = $this->askModel($item, $answer, $reference);

        if ($verdict === null) {
            return [
                'verdict' => TutorResponse::VERDICT_UNGRADED,
                'score' => null,
                'feedback' => null,
                'meta' => ['reason' => 'движок недоступен'],
            ];
        }

        return $verdict;
    }

    /**
     * Открытый ответ: оценка моделью против эталона и цитаты.
     *
     * @return array{verdict: string, score: ?float, feedback: ?string, meta: array<string,mixed>}
     */
    private function gradeOpen(TutorItem $item, string $answer): array
    {
        $verdict = $this->askModel($item, $answer, (string) $item->reference_answer);

        if ($verdict === null) {
            return [
                'verdict' => TutorResponse::VERDICT_UNGRADED,
                'score' => null,
                'feedback' => null,
                'meta' => ['reason' => 'движок недоступен'],
            ];
        }

        return $verdict;
    }

    /**
     * Спросить модель, верен ли ответ.
     *
     * null означает «спросить не удалось», и это НЕ значит «неверно».
     * Различать обязательно: иначе при остановленном движке все ответы
     * помечались бы как неверные и статистика тренажёра показывала бы
     * несправедливый ноль.
     *
     * @return array{verdict: string, score: ?float, feedback: ?string, meta: array<string,mixed>}|null
     */
    private function askModel(TutorItem $item, string $answer, string $reference): ?array
    {
        $system = <<<'TXT'
            Ты проверяющий ответ по УЧЕБНОМУ МАТЕРИАЛУ. Материал — единственный
            источник правильности. Твои личные знания не являются основанием
            засчитать или не засчитать ответ.

            Ответ считается верным, если он совпадает по смыслу с ЭТАЛОНОМ.
            Ответ, верный по общим знаниям, но отвечающий не на заданный вопрос,
            неверен.

            Ответь ТОЛЬКО JSON:
            {"verdict":"correct|partial|wrong","score":0..1,"feedback":"краткое пояснение по-русски"}
            TXT;

        $user = "Вопрос: ".$item->question
            ."\n\nЭталонный ответ: ".$reference
            ."\n\nПодтверждение в материале: ".(string) $item->source_quote
            ."\n\nОтвет пользователя: ".$answer;

        try {
            $result = $this->client->generate([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 500);
        } catch (Throwable $e) {
            Log::warning('Оценка ответа не удалась', ['error' => $e->getMessage()]);

            return null;
        }

        $payload = $result['json'];

        if (! is_array($payload)) {
            return null;
        }

        $verdict = strtolower((string) ($payload['verdict'] ?? ''));

        if (! in_array($verdict, [
            TutorResponse::VERDICT_CORRECT,
            TutorResponse::VERDICT_PARTIAL,
            TutorResponse::VERDICT_WRONG,
        ], true)) {
            return null;
        }

        $score = $payload['score'] ?? null;
        $score = is_numeric($score) ? max(0.0, min(1.0, (float) $score)) : null;

        if ($score === null) {
            $score = match ($verdict) {
                TutorResponse::VERDICT_CORRECT => 1.0,
                TutorResponse::VERDICT_PARTIAL => 0.5,
                default => 0.0,
            };
        }

        $feedback = $payload['feedback'] ?? null;

        return [
            'verdict' => $verdict,
            'score' => $score,
            'feedback' => is_string($feedback) ? mb_substr(trim($feedback), 0, 600) : null,
            'meta' => ['method' => 'model'],
        ];
    }

    /**
     * Нормализация для сравнения коротких ответов.
     *
     * Числа и единицы сохраняются: нормализация не должна делать разные
     * по смыслу ответы одинаковыми.
     */
    private function normalizeShort(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['«', '»', '"', "'", "\xc2\xa0"], ' ', $text);
        $text = preg_replace('/\b(раз|в|каждый|примерно|около|порядка)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/[.,;:!?]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Сравнение без учёта регистра и лишних пробелов. */
    private function same(string $a, string $b): bool
    {
        return $this->squash($a) === $this->squash($b);
    }

    private function squash(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
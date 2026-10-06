<?php

namespace Tests\Feature;

use App\Support\Tutor\TutorClient;
use App\Support\Tutor\TutorQuestionGrounding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Проверка выдачи модели: что тренажёр принимает и что отбрасывает.
 *
 * Это главный рубеж защиты от «самодеятельности». Модель — вероятностная,
 * и полагаться на её добросовестность нельзя: единственная проверка,
 * которая не может соврать, — детерминированная.
 *
 * Ключевое правило, ради которого всё затевалось: подтверждение
 * (source_quote) обязано ДОСЛОВНО найтись во фрагменте. Если цитаты в
 * тексте нет — вопроса быть не должно, независимо от того, насколько
 * правдоподобно он звучит.
 *
 * Модель подменена: тесты проверяют правила, а не поведение конкретной
 * нейросети.
 */
class TutorGroundingTest extends TestCase
{
    use RefreshDatabase;

    private const CHUNK = 'Предохранитель ПП-5 устанавливают на силовой установке. '
        .'Он срабатывает при перегрузке по току более 100 А. '
        .'Проверку проводят раз в 6 месяцев, при снятом питании.';

    private function grounding(
        array $questions,
        bool $backcheck = false,
        string $backcheckAnswer = 'подтверждение есть'
    ): TutorQuestionGrounding {
        config(['tutor.backcheck' => $backcheck]);

        $this->app->instance(
            TutorClient::class,
            new class($questions, $backcheckAnswer) extends TutorClient
            {
                public function __construct(
                    private readonly array $questions,
                    private readonly string $backcheckAnswer,
                ) {
                }

                public function health(): array
                {
                    return ['available' => true, 'models' => ['test'], 'model' => 'test', 'model_present' => true];
                }

                public function generate(array $messages, int $maxTokens = 900): array
                {
                    $system = $messages[0]['content'] ?? '';

                    if (str_contains($system, 'проверяющий')) {
                        return ['json' => ['support' => $this->backcheckAnswer], 'raw' => ''];
                    }

                    $payload = ['questions' => $this->questions];

                    return ['json' => $payload, 'raw' => json_encode($payload, JSON_UNESCAPED_UNICODE)];
                }

                public function embed(array $input): ?array
                {
                    return null;
                }
            }
        );

        return new TutorQuestionGrounding(app(TutorClient::class));
    }

    /** Корректный вопрос с цитатой из фрагмента. */
    private function validQuestion(array $overrides = []): array
    {
        return array_merge([
            'qtype' => 'mcq',
            'question' => 'При каком значении тока срабатывает предохранитель ПП-5?',
            'options' => ['Более 100 А', 'Более 50 А', 'Более 200 А', 'Не срабатывает'],
            'reference_answer' => 'Более 100 А',
            'source_quote' => 'Он срабатывает при перегрузке по току более 100 А.',
        ], $overrides);
    }

    // --- Принимается ----------------------------------------------------

    public function test_valid_question_is_accepted(): void
    {
        $result = $this->grounding([$this->validQuestion()])->generate(
            self::CHUNK,
            ['mcq'],
            1
        );

        $this->assertCount(1, $result['items']);
        $this->assertSame('mcq', $result['items'][0]['qtype']);
        $this->assertNotEmpty($result['items'][0]['fingerprint']);
    }

    public function test_quote_may_differ_in_case_and_spaces(): void
    {
        // Модель почти всегда «поправляет» кавычки и пробелы при
        // цитировании. Если бы сравнение было регистрозависимым, все
        // нормальные вопросы отбрасывались бы.
        $result = $this->grounding([
            $this->validQuestion([
                'source_quote' => '  срабатывает   ПРИ перегрузке по току более 100 А ',
            ]),
        ])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(1, $result['items']);
    }

    public function test_reference_must_match_one_of_options(): void
    {
        $result = $this->grounding([
            $this->validQuestion(['reference_answer' => 'Ровно 100 А']),
        ])->generate(self::CHUNK, ['mcq'], 1);

        // Эталона среди вариантов нет — вопрос непроверяем: выбрать
        // правильный вариант из списка невозможно.
        $this->assertCount(0, $result['items']);
        $this->assertCount(1, $result['rejected']);
    }

    // --- Отбрасывается --------------------------------------------------

    public function test_fabricated_quote_is_rejected(): void
    {
        // Главный случай: цитата правдоподобна, но в тексте её нет.
        $result = $this->grounding([
            $this->validQuestion([
                'source_quote' => 'Предохранитель ПП-5 срабатывает при перегрузке по току более 250 А.',
            ]),
        ])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(0, $result['items'], 'цитата, которой нет во фрагменте, обязана браковаться');
        $this->assertCount(1, $result['rejected']);
    }

    public function test_question_from_other_domain_is_rejected(): void
    {
        $result = $this->grounding([
            [
                'qtype' => 'mcq',
                'question' => 'Какая температура кипения воды при нормальном давлении?',
                'options' => ['100 °C', '90 °C', '80 °C', '120 °C'],
                'reference_answer' => '100 °C',
                'source_quote' => 'Вода кипит при 100 градусах Цельсия.',
            ],
        ])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(0, $result['items']);
    }

    public function test_question_without_quote_is_rejected(): void
    {
        $question = $this->validQuestion();
        unset($question['source_quote']);

        $result = $this->grounding([$question])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(0, $result['items'], 'вопрос без подтверждения недопустим');
    }

    public function test_quote_too_short_is_rejected(): void
    {
        $result = $this->grounding([
            $this->validQuestion(['source_quote' => 'по току']),
        ])->generate(self::CHUNK, ['mcq'], 1);

        // Короткая цитата подтверждает слишком многое: под неё
        // подходит любой вопрос, а проверять нечего.
        $this->assertCount(0, $result['items']);
    }

    public function test_duplicate_options_are_rejected(): void
    {
        $result = $this->grounding([
            $this->validQuestion([
                'options' => ['Более 100 А', 'Более 100 А', 'Другой', 'Ещё один'],
                'reference_answer' => 'Более 100 А',
            ]),
        ])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(0, $result['items'], 'дубли вариантов делают вопрос неоднозначным');
    }

    public function test_too_few_options_are_rejected(): void
    {
        $result = $this->grounding([
            $this->validQuestion(['options' => ['Более 100 А']]),
        ])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(0, $result['items']);
    }

    public function test_unexpected_question_type_is_rejected(): void
    {
        $result = $this->grounding([
            $this->validQuestion(['qtype' => 'essay']),
        ])->generate(self::CHUNK, ['mcq'], 1);

        // Тип вне запрошенных не берём: иначе модель может подсунуть
        // формат, который фронт не умеет отображать.
        $this->assertCount(0, $result['items']);
    }

    public function test_empty_answer_from_model_is_not_a_crash(): void
    {
        $result = $this->grounding([[]])->generate(self::CHUNK, ['mcq'], 1);

        $this->assertSame([], $result['items']);
        $this->assertNotEmpty($result['rejected']);
    }

    // --- Дедупликация ---------------------------------------------------

    public function test_identical_questions_are_deduplicated(): void
    {
        $question = $this->validQuestion();

        $result = $this->grounding([$question, $question, $question])
            ->generate(self::CHUNK, ['mcq'], 3);

        // Требование «вопросы каждый раз разные»: три одинаковых
        // вопроса в одной выдаче — брак генерации.
        $this->assertCount(1, $result['items']);
        $this->assertCount(2, $result['rejected']);
    }

    public function test_rephrased_duplicate_is_deduplicated(): void
    {
        // Та же мысль, другая формулировка. Хеш строится по нормализованному
        // тексту, поэтому перестановка слов и знаков даёт тот же отпечаток.
        $grounding = $this->grounding([
            $this->validQuestion(),
            $this->validQuestion([
                'question' => 'При каком значении тока срабатывает предохранитель ПП-5?',
                'options' => ['Не срабатывает', 'Более 200 А', 'Более 50 А', 'Более 100 А'],
            ]),
        ]);

        $result = $grounding->generate(self::CHUNK, ['mcq'], 2);

        $this->assertCount(1, $result['items']);
    }

    public function test_already_known_question_is_not_repeated(): void
    {
        // Отпечаток из предыдущей сессии передаётся в параметре: вопрос,
        // заданный раньше, повторно не предлагается.
        $known = [hash('sha256', $this->normalized($this->validQuestion()['question'])) => true];

        $result = $this->grounding([$this->validQuestion()])->generate(self::CHUNK, ['mcq'], 1, $known);

        $this->assertCount(0, $result['items']);
    }

    // --- Back-check ------------------------------------------------------

    /**
     * Модель прямо говорит, что подтверждения в тексте нет.
     *
     * Это единственный случай, когда вопрос отбрасывается по итогам
     * back-check: явный отказ модели означает, что вопрос не опирается на
     * фрагмент.
     */
    public function test_backcheck_rejects_ungrounded_question(): void
    {
        $result = $this->grounding(
            [$this->validQuestion()],
            backcheck: true,
            backcheckAnswer: 'no'
        )->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(0, $result['items']);
        $this->assertStringContainsString('back-check', implode(' ', $result['rejected']));
    }

    /**
     * Модель пересказала подтверждение вместо цитаты.
     *
     * Вопрос сохраняется, но помечается как непроверенный. Отбрасывать
     * его нельзя: при недоступном движке тренажёр лишился бы половины
     * вопросов, а здесь подтверждение просто не сошлось дословно.
     */
    public function test_paraphrased_backcheck_keeps_item_unverified(): void
    {
        $result = $this->grounding(
            [$this->validQuestion()],
            backcheck: true,
            backcheckAnswer: 'В тексте говорится про предохранитель и ток'
        )->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(1, $result['items']);
        $this->assertNull(
            $result['items'][0]['backcheck_passed'],
            'непроверенный вопрос обязан отличаться от подтверждённого'
        );
    }

    public function test_backcheck_is_skipped_when_disabled(): void
    {
        $result = $this->grounding([$this->validQuestion()], backcheck: false)
            ->generate(self::CHUNK, ['mcq'], 1);

        $this->assertCount(1, $result['items']);
        $this->assertNull($result['items'][0]['backcheck_passed'], 'выключенная проверка не должна выдавать подтверждение');
    }

    private function normalized(string $question): string
    {
        $normalized = mb_strtolower($question, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }
}
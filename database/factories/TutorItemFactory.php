<?php

namespace Database\Factories;

use App\Models\TutorChunk;
use App\Models\TutorItem;
use App\Models\TutorSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorItem>
 */
class TutorItemFactory extends Factory
{
    protected $model = TutorItem::class;

    public function definition(): array
    {
        return [
            'session_id' => TutorSession::factory(),
            'chunk_id' => TutorChunk::factory(),
            'qtype' => 'mcq',
            'question' => 'При каком токе срабатывает предохранитель ПП-5?',
            'options' => ['Более 100 А', 'Более 50 А', 'Более 200 А', 'Не срабатывает'],
            'reference_answer' => 'Более 100 А',
            'source_quote' => 'Он срабатывает при перегрузке по току более 100 А.',
            'fingerprint' => hash('sha256', 'item-'.fake()->unique()->uuid()),
            'asked_count' => 0,
            'backcheck_passed' => true,
        ];
    }

    public function shortAnswer(): static
    {
        return $this->state(fn () => [
            'qtype' => 'short',
            'options' => null,
            'question' => 'Как часто проводят проверку предохранителя ПП-5?',
            'reference_answer' => 'раз в 6 месяцев',
        ]);
    }

    public function openAnswer(): static
    {
        return $this->state(fn () => [
            'qtype' => 'open',
            'options' => null,
            'question' => 'Опишите порядок проверки предохранителя ПП-5.',
            'reference_answer' => 'Снять питание, проверить визуально, измерить сопротивление, установить.',
        ]);
    }
}

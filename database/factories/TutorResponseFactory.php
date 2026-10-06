<?php

namespace Database\Factories;

use App\Models\TutorItem;
use App\Models\TutorResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorResponse>
 */
class TutorResponseFactory extends Factory
{
    protected $model = TutorResponse::class;

    public function definition(): array
    {
        return [
            'item_id' => TutorItem::factory(),
            'verdict' => TutorResponse::VERDICT_CORRECT,
            'answer' => 'Более 100 А',
            'feedback' => 'Верно.',
            'auto_score' => 1.0,
        ];
    }

    public function wrong(): static
    {
        return $this->state(fn () => ['verdict' => TutorResponse::VERDICT_WRONG, 'auto_score' => 0.0]);
    }

    public function ungraded(): static
    {
        return $this->state(fn () => ['verdict' => TutorResponse::VERDICT_UNGRADED, 'auto_score' => null]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'title' => 'Экзамен '.fake()->unique()->word(),
            // max_attempts и passing_score NOT NULL со значениями по
            // умолчанию — заполнять нужно явно.
            'max_attempts' => 1,
            'passing_score' => 0.7,
        ];
    }
}

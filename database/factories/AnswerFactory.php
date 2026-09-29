<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AnswerFactory extends Factory
{
    public function definition()
    {
        return [
            'answer'      => $this->faker->word(),
            'is_correct'  => true,
            'question_id' => \App\Models\Question::factory(),
        ];
    }
}

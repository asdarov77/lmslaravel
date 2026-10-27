<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition()
    {
        return [
            'category_id'      => null,
            'aukstructure_id'  => null,
            'question_text'    => $this->faker->sentence(),
        ];
    }
}

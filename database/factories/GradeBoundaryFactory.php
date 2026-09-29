<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GradeBoundaryFactory extends Factory
{
    public function definition()
    {
        return [
            'boundary' => $this->faker->numberBetween(0, 100),
            'grade'    => (string) $this->faker->numberBetween(1, 5),
        ];
    }
}

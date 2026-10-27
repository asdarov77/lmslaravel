<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id'   => \App\Models\User::factory(),
            'course_id' => $this->faker->numberBetween(1, 1000),
            'title'     => $this->faker->sentence(3),
        ];
    }
}

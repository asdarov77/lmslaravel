<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AukstructureFactory extends Factory
{
    public function definition()
    {
        return [
            'course_id'    => \App\Models\Course::factory(),
            'parent_id'    => null,
            'title'        => $this->faker->word(),
            'type'         => 1,
            'description'  => $this->faker->sentence(),
            'categories'   => null,
            'identifier'   => $this->faker->unique()->word(),
        ];
    }
}

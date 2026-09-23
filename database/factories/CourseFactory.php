<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'short_description' => $this->faker->sentence(8),
            'long_description' => $this->faker->paragraph(),
            'path' => $this->faker->slug(),
            'visible' => true,
        ];
    }
}



<?php

namespace Database\Factories;

use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Factories\Factory;

class AircraftFactory extends Factory
{
    protected $model = Aircraft::class;

    public function definition(): array
    {
        return [
            'path' => $this->faker->optional()->slug(),
            'title' => $this->faker->unique()->word(),
        ];
    }
}

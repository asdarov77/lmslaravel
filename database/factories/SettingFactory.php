<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    public function definition()
    {
        return [
            'name'  => $this->faker->unique()->word(),
            'value' => (string) $this->faker->numberBetween(1, 100),
            'type'  => 'string',
        ];
    }
}

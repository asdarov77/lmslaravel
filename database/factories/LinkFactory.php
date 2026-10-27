<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LinkFactory extends Factory
{
    public function definition()
    {
        return [
            'aukstructure_id' => null,
            'link'            => $this->faker->url(),
        ];
    }
}

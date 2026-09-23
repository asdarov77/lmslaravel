<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
           'rolename' => 'Администратор',
//           'id' => $this->faker->numberBetween(1,10),
          'user_id' => $this->faker->numberBetween(1,20),

        ];
    }
}

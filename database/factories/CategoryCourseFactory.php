<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryCourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            //
         'category_id' => $this->faker->numberBetween(1,5),
         'course_id' => $this->faker->numberBetween(1,10),
        ];
    }
}

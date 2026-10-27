<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class Group2learningFactory extends Factory
{
    public function definition()
    {
        return [
            'course_id'    => \App\Models\Course::factory(),
            'group_id'     => \App\Models\Group::factory(),
            'category_id'  => \App\Models\Category::factory(),
            'parent_id'    => null,
            'teacher'      => 0,
            'typeOfLesson' => 'lecture',
            'study_from'   => now()->toDateString(),
            'study_to'     => now()->addMonth()->toDateString(),
        ];
    }
}

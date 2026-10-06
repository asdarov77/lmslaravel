<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\TutorMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorMaterial>
 */
class TutorMaterialFactory extends Factory
{
    protected $model = TutorMaterial::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => 'Материал '.fake()->word(),
            'source' => 'course',
            'status' => TutorMaterial::STATUS_INDEXED,
            'chunks_count' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => TutorMaterial::STATUS_PENDING, 'chunks_count' => 0]);
    }

    public function empty(): static
    {
        return $this->state(fn () => ['status' => TutorMaterial::STATUS_EMPTY, 'chunks_count' => 0]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => TutorMaterial::STATUS_FAILED, 'chunks_count' => 0, 'error' => 'текст не извлечён']);
    }
}

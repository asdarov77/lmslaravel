<?php

namespace Database\Factories;

use App\Models\TutorMaterial;
use App\Models\TutorSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorSession>
 */
class TutorSessionFactory extends Factory
{
    protected $model = TutorSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'material_id' => TutorMaterial::factory(),
            'status' => TutorSession::STATUS_ACTIVE,
            'started_at' => now(),
            'context_window' => [],
        ];
    }
}

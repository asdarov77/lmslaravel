<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'user_id' => User::factory(),
            'score' => 0.8,
            'correct_count' => 4,
            'total_count' => 5,
            'passed' => true,
            'submitted_at' => now(),
        ];
    }
}

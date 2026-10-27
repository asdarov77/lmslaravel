<?php

namespace Database\Factories;

use App\Models\TutorChunk;
use App\Models\TutorMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorChunk>
 */
class TutorChunkFactory extends Factory
{
    protected $model = TutorChunk::class;

    public function definition(): array
    {
        $content = 'Предохранитель ПП-5 срабатывает при перегрузке по току более 100 А.';

        return [
            'material_id' => TutorMaterial::factory(),
            'seq' => 1,
            'content' => $content,
            'token_count' => TutorChunk::estimateTokens($content),
        ];
    }
}

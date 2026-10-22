<?php

namespace Database\Seeders;   
use Illuminate\Database\Seeder;
use App\Models\GradeBoundary;

class GradeSeeder extends Seeder
{
    public function run()
    {
        $gradeBoundaries = [
            ['boundary' => 0, 'grade' => '2'],
            ['boundary' => 35, 'grade' => '3'],
            ['boundary' => 65, 'grade' => '4'],
            ['boundary' => 85, 'grade' => '5']
        ];

        // upsert, а не create: повторный запуск сидеров не должен
        // удваивать границы оценивания. Иначе пороги «0 → 2» и
        // «0 → 2» оказываются двумя строками, и журнал считал бы их
        // как два разных правила.
        foreach ($gradeBoundaries as $gradeBoundary) {
            GradeBoundary::updateOrCreate(
                ['boundary' => $gradeBoundary['boundary']],
                ['grade' => $gradeBoundary['grade']]
            );
        }
    }
}

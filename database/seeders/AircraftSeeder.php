<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AircraftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {        
        /*
         * Пути — это каталоги с материалами:
         * storage/app/courses/private/<path>/<курс>/.
         *
         * Раньше здесь стояли «Ка-52» и «Ми-28», которых в каталоге
         * материалов нет: курс открывался, но файла за ним не
         * находилось, и страница материала показывала ошибку. Значения
         * взяты из реальных каталогов, чтобы демо-курс открывался.
         */
        /*
         * updateOrCreate по path, а не insert.
         *
         * И insert, и upsert требуют уникального индекса на path,
         * которого в схеме нет: upsert падал бы с «нет уникального
         * ограничения… соответствующего ON CONFLICT». Повторный
         * запуск сидеров не должен плодить копии самолётов — иначе
         * курсы разъезжаются по двум «БПЛА».
         */
        foreach ([
            ['path' => 'БПЛА', 'title' => 'БПЛА'],
            ['path' => 'КЛЕН', 'title' => 'КЛЕН'],
        ] as $aircraft) {
            \Illuminate\Support\Facades\DB::table('aircrafts')->updateOrInsert(
                ['path' => $aircraft['path']],
                ['title' => $aircraft['title'], 'updated_at' => now()]
            );
        }
    }
}

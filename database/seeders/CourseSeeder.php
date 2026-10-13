<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
//use App\Models\Category;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*
         * Курсы привязываются к самолётам по кругу.
         *
         * Раньше aircraft_id не заполнялся вовсе, и каталог, который
         * группирует курсы по самолёту, показывал одну группу вместо
         * нескольких: все 15 курсов оказывались «без самолёта».
         * Записи без самолёта — не ошибка схемы, а неполные данные.
         */
        $aircraftIds = DB::table('aircrafts')->orderBy('id')->pluck('id')->all();

        if ($aircraftIds === []) {
            // Без самолётов каталог всё равно не соберётся, но падать
            // из-за сидеров нельзя: сначала должен отработать
            // AircraftSeeder.
            $aircraftIds = [null];
        }

        $index = 0;

        $rows = [
                    [
                        'title' => "Подготовка и выполнение полета. Боевое применение",
                        'short_description' => 'курсы для летчика',
                        'long_description' => '',
                        'path' => 'course-1',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Эксплуатация оборудования",
                        'short_description' => 'курсы для борт инженера',
                        'long_description' => '',
                        'path' => 'course-2',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Авиационное вооружение и десантно-транспортное оборудование",
                        'short_description' => 'курсы для инженера АВ',
                        'long_description' => '',
                        'path' => 'course-3',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Подготовка к полетам самолета, самолетных систем и силовой установки",
                        'short_description' => 'курсы для бортового техника',
                        'long_description' => '',
                        'path' => 'course-4',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Авиационное оборудование",
                        'short_description' => 'курсы для АО',
                        'long_description' => '',
                        'path' => 'course-5',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Планер самолета",
                        'short_description' => 'курсы планера самолета',
                        'long_description' => '',
                        'path' => 'course-6',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Силовая установка",
                        'short_description' => 'курсы двигатель',
                        'long_description' => '',
                        'path' => 'course-7',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Силовая установка",
                        'short_description' => 'курсы двигатель',
                        'long_description' => '',
                        'path' => 'course-8',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Конструкция вертолета и его системы. Описание и эксплуатация",
                        'short_description' => 'описание вертолета..',
                        'long_description' => '',
                        'path' => 'course-9',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Практическая аэродинамика",
                        'short_description' => 'курсы аэродинамика..',
                        'long_description' => '',
                        'path' => 'course-10',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Летная эксплуатация (раздел 4 Летные данные)",
                        'short_description' => 'курсы ЛЭ',
                        'long_description' => '',
                        'path' => 'course-11',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Конструкция вертолета и его системы. Техническое обслуживание",
                        'short_description' => 'курсы ТО',
                        'long_description' => '',
                        'path' => 'course-12',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Конструкция силовой установки. Техническое обслуживание",
                        'short_description' => 'курсы ТО',
                        'long_description' => '',
                        'path' => 'course-13',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Радиоэлектронное оборудование. Техническое обслуживание",
                        'short_description' => 'курсы РЭО',
                        'long_description' => '',
                        'path' => 'course-14',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    [
                        'title' => "Аварийно-спасательные средства. Техническое описание",
                        'short_description' => 'курсы РЭО',
                        'long_description' => '',
                        'path' => 'course-15',

                        'aircraft_id' => $aircraftIds[$index++ % count($aircraftIds)],
                    ],
                    
                    
                ];

        /*
         * updateOrCreate по path, а не пакетный insert.
         *
         * courses.path — естественный ключ курса: по нему строится путь
         * к материалам. Повторный запуск сидеров обновляет курс, а не
         * добавляет второй с тем же path, из-за чего в каталоге
         * появлялись дубли одного и того же курса.
         */
        foreach ($rows as $row) {
            DB::table('courses')->updateOrInsert(
                ['path' => $row['path']],
                [
                    'title' => $row['title'],
                    'short_description' => $row['short_description'],
                    'long_description' => $row['long_description'],
                    // aircraft_id тоже обновляем: категория курса могла
                    // смениться, и без этого курс остался бы на старом
                    // самолёте, а каталог группировал бы его неверно.
                    'aircraft_id' => $row['aircraft_id'] ?? null,
                    'updated_at' => now(),
                ]
            );
        }
            }
        }

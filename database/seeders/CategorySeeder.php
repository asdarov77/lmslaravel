<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('categories')->insert([
                    [
                        'title' => "Летчик",
                        'description' => 'курсы для летчика',
                        'code' => 'PILOT',
                    ],
                    [
                        'title' => "Борт инженер",
                        'description' => 'курсы для борт инженера',
                        'code' => 'FLIGHT_ENG',
                    ],
                    [
                        'title' => "Инженер АВ",
                        'description' => 'курсы для инженера АВ',
                        'code' => 'AV_ENG',
                    ],
                    [
                        'title' => "Инженер АСУ",
                        'description' => 'курсы для инженера АСУ',
                        'code' => 'ASU_ENG',
                    ],
                    [
                        'title' => "Штурман",
                        'description' => 'курсы для штурмана',
                        'code' => 'NAV',
                    ],

                ]);
            }
        }

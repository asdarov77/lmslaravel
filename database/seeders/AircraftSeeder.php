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
        DB::table('aircrafts')->insert([
            [

                'path' => 'Ка-52',
                'title' => 'title Ка-52',
            ],
            [

                'path' => 'Ми-28',
                'title' => 'title Ми-28',
            ],
        ]);
    }
}

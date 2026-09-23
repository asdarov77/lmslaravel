<?php

namespace Database\Seeders;   
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            ['name' => 'name1', 'value' => '2',  'type' => 'type1'],
            ['name' => 'name2', 'value' => '3',  'type' => 'type1'],
            ['name' => 'name3', 'value' => '4',  'type' => 'type1'],
            ['name' => 'name4', 'value' => '5',  'type' => 'type2'],
            ['name' => 'name5', 'value' => '55',  'type' => 'type2'],
            ['name' => 'name6', 'value' => '105',  'type' => 'type3'],
        ];

        foreach ($settings as $_settings) {
            Setting::create($_settings);
        }
    }
}

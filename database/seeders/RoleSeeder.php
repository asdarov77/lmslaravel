<?php

namespace Database\Seeders;
use App\Models\Role;
use Illuminate\Database\Seeder;
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $administrator = new Role();
        $administrator->rolename = 'Администратор';
        $administrator->slug = 'Администратор';
        $administrator->save();
        $instructor = new Role();
        $instructor->rolename = 'Инструктор';
        $instructor->slug = 'Инструктор';
        $instructor->save();
        $student = new Role();
        $student->rolename = 'Обучаемый';
        $student->slug = 'Обучаемый';
        $student->save();
    }
}


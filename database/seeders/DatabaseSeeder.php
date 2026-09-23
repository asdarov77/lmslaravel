<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // DB::table('categories')->insert(
        //     [
        //         [
        //             'title' => "Летчик",
        //             'description' => 'курсы для летчика',
        //         ],
        //         [
        //             'title' => "Борт инженер",
        //             'description' => 'курсы для борт инженера',
        //         ],
        //         [
        //             'title' => "Инженер АВ",
        //             'description' => 'курсы для инженера АВ',
        //         ],
        //         [
        //             'title' => "Инженер АСУ",
        //             'description' => 'курсы для инженера АСУ',
        //         ],
        //         [
        //             'title' => "Штурман",
        //             'description' => 'курсы для штурмана',
        //         ]
        //     ]
        // );

        //         DB::table('roles')->insert(
        //     [
        //         [
        //             'rolename' => "Администратор",
        //         ],
        //         [
        //             'rolename' => "Инструктор",
        //         ],
        //         [
        //             'rolename' => "Обучаемый",
        //         ],

        //     ]
        // );

        \App\Models\Group::factory(10)->create();

        $this->call([
            UserSeeder::class,
            PermissionSeeder::class,
            GradeSeeder::class,
            SettingSeeder::class,
            CategorySeeder::class,
            CourseSeeder::class,

        ]);

        //\App\Models\User::factory(10)->create();
//        \App\Models\Group::factory(10)->create();
        //\App\Models\Course::factory(10)->create();
        //\App\Models\CategoryCourse::factory(15)->create();
        //\App\Models\Category::factory(5)->create();

                 DB::table('permissions_users')->insert(
             [
                 [
                     'user_id' => 1,
                     'permission_id' => 1,
                 ],
                 [
                     'user_id' => 1,
                     'permission_id' => 2,
                 ],

             ]
         );

    }
}

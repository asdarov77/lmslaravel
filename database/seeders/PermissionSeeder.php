<?php

namespace Database\Seeders;
use App\Models\Permission;
use Illuminate\Database\Seeder;
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $manageUser = new Permission();
        $manageUser->name = 'управление пользователями';
        $manageUser->slug = 'manage-users';
        $manageUser->save();

        $createTasks = new Permission();
        $createTasks->name = 'Create Tasks';
        $createTasks->slug = 'create-tasks';
        $createTasks->save();

        $manageCourse = new Permission();
        $manageCourse->name = 'управление курсами';
        $manageCourse->slug = 'manage-course';
        $manageCourse->save();
    }
}


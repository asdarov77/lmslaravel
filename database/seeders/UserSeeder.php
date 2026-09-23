<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'fio' => 'Администратор',
                'role' => 'Администратор',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Иванов Иван Иванович',
                'role' => 'Инструктор',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Петров Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Сидоров Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Корнеев Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Семенов Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Крупнов Максим Витальевич',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Ненадович Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Пименов Иван Иванович',
                'role' => 'Инструктор',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Пельш Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 1,
            ],
            [
                'fio' => 'Моисеев Иван Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 2,
            ],
            [
                'fio' => 'Рабинович Хаим Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 2,
            ],
            [
                'fio' => 'Медведев Дмитрий Анатольевич',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 2,
            ],
            [
                'fio' => 'Трунов Иван Семенович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 2,
            ],
            [
                'fio' => 'Селезнев Тимофей Михайлович',
                'role' => 'Инструктор',
                'password' => Hash::make('123'),
                'group_id'=> 2,
            ],
            [
                'fio' => 'Нагиев Дмитрий Владимирович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 3,
            ],
            [
                'fio' => 'Шойгу Сергей Кужугетович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 3,
            ],
            [
                'fio' => 'Великов Вадим Иванович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 3,
            ],
            [
                'fio' => 'Фурсов Андрей Ильич',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 3,
            ],
            [
                'fio' => 'Савельев Сергей Вячеславович',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 3,
            ],
            [
                'fio' => 'Панчин Александр Юрьевич',
                'role' => 'Обучаемый',
                'password' => Hash::make('123'),
                'group_id'=> 3,
            ],
        ]);
    }
}

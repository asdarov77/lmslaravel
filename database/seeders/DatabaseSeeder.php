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

        // Сначала каталог прав: RoleSeeder назначает ролям id из таблицы
        // permissions. Раньше RoleSeeder здесь отсутствовал, и на чистой
        // установке в базе не было ни Администратора, ни Инструктора,
        // ни Обучаемого — из-за чего назначать права было нечем.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            GradeSeeder::class,
            SettingSeeder::class,
            CategorySeeder::class,
            // Самолёты идут ДО курсов: курсы ссылаются на них, а без
            // aircraft_id каталог группирует всё в одну группу.
            AircraftSeeder::class,
            CourseSeeder::class,
            // Уроки и связи курс↔категория: без них каталог и форма
            // записи групп показывают пустое дерево.
            CourseLessonsSeeder::class,

        ]);

        /*
         * Демо-записи групп на курсы.
         *
         * Без них у обучаемого нет учебного плана: дашборд пуст,
         * «продолжить обучение» нечего продолжать, расписание пустое.
         * Группы и курсы уже созданы выше, поэтому берём их из базы.
         */
        $groupIds = \App\Models\Group::orderBy('id')->pluck('id')->all();
        $courseIds = \App\Models\Course::orderBy('id')->pluck('id')->all();

        if ($groupIds !== [] && $courseIds !== []) {
            $rows = [];
            $now = now();

            foreach ($groupIds as $groupIndex => $groupId) {
                foreach (array_slice($courseIds, $groupIndex, 3) as $courseId) {
                    $row = new \App\Models\Group2learning([
                        'group_id' => $groupId,
                        'course_id' => $courseId,
                    ]);

                    // Периоды разнесены по группам, чтобы календарь и
                    // статусы («активен», «завершён») не были одинаковыми.
                    $row->study_from = $now->copy()->subMonths(2)->addDays($groupIndex * 3)->toDateString();
                    $row->study_to = $now->copy()->addMonths(2)->addDays($groupIndex * 3)->toDateString();
                    $row->save();
                }
            }
        }

        //\App\Models\User::factory(10)->create();
//        \App\Models\Group::factory(10)->create();
        //\App\Models\Course::factory(10)->create();
        //\App\Models\CategoryCourse::factory(15)->create();
        //\App\Models\Category::factory(5)->create();

                 // Прямые права администратора. Раньше здесь стояли жёсткие
        // user_id = 1 / permission_id = 1, 2, что падало с нарушением
        // permissions_users_*_foreign, если sequence уже сдвинуты
        // (RefreshDatabase откатывает транзакцию, но не nextval).
        $admin = \App\Models\User::where('fio', 'Администратор')->first();

        if ($admin) {
            $adminPermissionIds = \App\Models\Permission::pluck('id')->all();

            if ($adminPermissionIds !== []) {
                $now = now();
                DB::table('permissions_users')->insertOrIgnore(
                    array_map(
                        static fn ($permissionId): array => [
                            'user_id' => $admin->id,
                            'permission_id' => $permissionId,
                        ],
                        $adminPermissionIds
                    )
                );
            }
        }

    }
}

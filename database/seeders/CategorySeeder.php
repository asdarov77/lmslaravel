<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Справочник специальностей.
     *
     * Отдельный метод, а не литерал в run(): список нужен как данные,
     * а run() занимается только их применением. Так его проще
     * переиспользовать и проверять.
     *
     * @return array<int, array{title: string, description: string, code: string}>
     */
    public function categories(): array
    {
        return [
            ['title' => "Летчик", 'description' => 'курсы для летчика', 'code' => 'PILOT'],
            ['title' => "Борт инженер", 'description' => 'курсы для борт инженера', 'code' => 'FLIGHT_ENG'],
            ['title' => "Инженер АВ", 'description' => 'курсы для инженера АВ', 'code' => 'AV_ENG'],
            ['title' => "Инженер АСУ", 'description' => 'курсы для инженера АСУ', 'code' => 'ASU_ENG'],
            ['title' => "Штурман", 'description' => 'курсы для штурмана', 'code' => 'NAV'],
        ];
    }

    public function run()
    {
        /*
         * updateOrCreate по code.
         *
         * У upsert потребовался бы уникальный индекс на code, которого
         * нет. Через PHP тот же эффект достигается без изменения схемы:
         * повторный запуск обновляет категорию, а не добавляет вторую
         * с тем же кодом — а по коду ищутся уроки курса.
         */
        foreach ($this->categories() as $category) {
            Category::updateOrCreate(['code' => $category['code']], $category);
        }
    }
}

<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Учебная запись «группа записана на курс в рамках своей специальности»
 * (group2learnings: course_id, group_id, category_id, parent_id).
 *
 * Это единственный источник прав на курсы, материалы и экзамены. Запись
 * несёт свою category_id намеренно: один курс бывает привязан к нескольким
 * специальностям, и проверка «есть запись на курс» без специализации отдала бы
 * лётчику материалы радиста.
 *
 * parent_id (модуль) заполняется не всегда — ограничение по конкретному
 * модулю пока не реализовано.
 */
class Group2learning extends Model
{
    use HasFactory;
    use Filterable;
    protected $fillable = ['course_id','group_id','category_id','parent_id'];

// fix связи !!!, имея категорию и курс,я могу определить связь через таблицу

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
        public function category()
    {
        return $this->belongsTo(Category::class);
    }
        public function course()
    {
        return $this->belongsTo(Course::class);
    }

}

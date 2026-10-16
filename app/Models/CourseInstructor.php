<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Связь курс — преподаватель.
 *
 * Многие-ко-многим с доп. полями (в будущем можно добавить роль:
 * leading, assistant, reviewer). Сейчас просто pivot.
 */
class CourseInstructor extends Model
{
    protected $fillable = ['course_id', 'user_id'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

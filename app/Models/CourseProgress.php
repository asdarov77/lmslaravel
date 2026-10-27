<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Серверная точка возврата чтения (resume-point) пользователя в курсе.
 */
class CourseProgress extends Model
{
    protected $table = 'course_progress';

    protected $fillable = [
        'user_id',
        'course_id',
        'last_item_id',
        'last_anchor',
        'percent',
    ];

    protected $casts = [
        'percent' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lastItem()
    {
        return $this->belongsTo(Aukstructure::class, 'last_item_id');
    }
}

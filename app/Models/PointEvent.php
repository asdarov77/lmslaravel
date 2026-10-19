<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Начисление XP.
 *
 * Запись, а не изменение счётчика на пользователе: журнал объясняет
 * итог и позволяет пересчитать его после правки правил.
 */
class PointEvent extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'kind',
        'points',
        'source_key',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
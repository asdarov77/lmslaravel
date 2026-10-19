<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Заметка студента к материалу курса.
 *
 * Хранит текст заметки пользователя к конкретному узлу
 * структуры курса (aukstructure).
 */
class MaterialNote extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'aukstructure_id',
        'content',
    ];

    protected $casts = [
        'content' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function aukstructure()
    {
        return $this->belongsTo(Aukstructure::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Прогресс пользователя по одному уроку курса.
 *
 * Строка заводится при первом открытии урока и дальше обновляется:
 * percent не уменьшается (материал можно перечитать), last_file
 * и last_anchor всегда указывают на последнее место просмотра.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $lesson_id
 * @property int $percent
 * @property string|null $last_file
 * @property string|null $last_anchor
 * @property \Illuminate\Support\Carbon|null $last_viewed_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property-read Aukstructure $lesson
 * @property-read Course $course
 */
class LessonProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'percent',
        'last_file',
        'last_anchor',
        'last_viewed_at',
        'completed_at',
    ];

    protected $casts = [
        'percent' => 'integer',
        'last_viewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'percent' => 0,
    ];

    /**
     * Проставляет время просмотра, если его не задали явно.
     *
     * Без этого строка, созданная в обход контроллера, оставалась с
     * пустым last_viewed_at, и «продолжить» выбирал между такими
     * уроками произвольный — порядок становился случайным.
     */
    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if ($row->last_viewed_at === null) {
                $row->last_viewed_at = now();
            }
        });
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Aukstructure::class, 'lesson_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null || $this->percent >= 100;
    }
}
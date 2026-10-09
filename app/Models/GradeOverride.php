<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ручная оценка преподавателя за экзамен.
 *
 * Перекрывает автоматический результат попытки: пока строка есть,
 * грейдбук и страница результатов показывают grade/comment из неё.
 * Удаление строки возвращает автоматический результат.
 *
 * @property int $id
 * @property int $user_id
 * @property int $exam_id
 * @property int $grade
 * @property string|null $comment
 * @property int|null $teacher_id
 * @property-read Exam $exam
 * @property-read User $user
 */
class GradeOverride extends Model
{
    use HasFactory;

    protected $table = 'grade_overrides';

    protected $fillable = [
        'user_id',
        'exam_id',
        'grade',
        'comment',
        'teacher_id',
    ];

    protected $casts = [
        'grade' => 'integer',
    ];
}
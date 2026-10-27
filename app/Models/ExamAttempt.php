<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Попытка сдачи экзамена.
 *
 * Попытка — цельная единица: один заход обучаемого, один результат.
 * Именно поэтому она отдельная сущность, а не по строке на ответ.
 */
class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'user_id',
        'total_count',
        'correct_count',
        'score',
        'passed',
        'submitted_at',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'correct_count' => 'integer',
        'score' => 'float',
        'passed' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

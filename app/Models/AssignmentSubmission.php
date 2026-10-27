<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Сданная работа: файл, комментарий и проверка преподавателя.
 *
 * @property int $id
 * @property int $assignment_id
 * @property int $user_id
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $comment
 * @property string $status
 * @property int|null $score
 * @property string|null $feedback
 * @property int|null $reviewer_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property-read Assignment $assignment
 * @property-read User $user
 */
class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'file_path',
        'file_name',
        'comment',
        'status',
        'score',
        'feedback',
        'reviewer_id',
        'reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Проверена ли работа.
     *
     * Статус выводится из наличия оценки, а не хранится отдельно:
     * отдельная колонка разошлась бы с оценкой при первом же
     * изменении — «проверено, но без балла» или наоборот.
     */
    public function isReviewed(): bool
    {
        return $this->score !== null || $this->feedback !== null;
    }
}
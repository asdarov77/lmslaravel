<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Домашнее задание курса для конкретной группы.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $course_id
 * @property int|null $aukstructure_id
 * @property int $group_id
 * @property int|null $author_id
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property int $max_score
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property-read Course $course
 * @property-read Group $group
 * @property-read HasMany<AssignmentSubmission> $submissions
 */
class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'course_id',
        'aukstructure_id',
        'group_id',
        'author_id',
        'due_at',
        'max_score',
        'published_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'published_at' => 'datetime',
        'max_score' => 'integer',
    ];

    protected $attributes = [
        'max_score' => 5,
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Aukstructure::class, 'aukstructure_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /** Опубликовано ли задание (обучаемый не видит черновики). */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Просрочено ли задание. Задание без срока просроченным не бывает. */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }
}
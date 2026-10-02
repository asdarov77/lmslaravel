<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Экзамен — назначение проверки знаний.
 *
 * Вопросы не хранятся в экзамене: они принадлежат паре
 * (category_id, aukstructure_id), как и в банке. Экзамен задаёт эту пару
 * вместе с окном доступности, лимитом попыток и проходным баллом.
 */
class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'course_id',
        'aukstructure_id',
        'category_id',
        'group_id',
        'user_id',
        'opens_at',
        'closes_at',
        'due_at',
        'max_attempts',
        'passing_score',
        'question_limit',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'due_at' => 'datetime',
        'max_attempts' => 'integer',
        'passing_score' => 'float',
        'question_limit' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Aukstructure::class, 'aukstructure_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Отбор вопросов для экзамена.
     *
     * Возвращает null, если не задана ни одна из пары, — тогда вопросы
     * не отбираются вовсе, и это осознанный сценарий «весь банк»,
     * задаваемый явно полем scope_all.
     */
    public function questionQuery()
    {
        $query = Question::with('answers')
            ->when($this->aukstructure_id, fn ($q) => $q->where('questions.aukstructure_id', $this->aukstructure_id))
            ->when($this->category_id, fn ($q) => $q->where('questions.category_id', $this->category_id))
            ->orderBy('questions.id');

        // Лимит применяется здесь, а не в контроллере выдачи: тогда и
        // вопросы, и сверка ответов при приёме попытки работают с ОДНИМ
        // и тем же набором. Иначе вопросы показывались одни, а
        // засчитывались другие.
        if ($this->question_limit !== null) {
            $query->limit($this->question_limit);
        }

        return $query;
    }

    /**
     * Состояние экзамена для конкретного пользователя и «сейчас».
     *
     * @return array{key: string, label: string, available: bool}
     */
    public function stateFor(?User $user): array
    {
        $now = now();
        $attempts = $user ? $this->attempts()->where('user_id', $user->id)->count() : 0;

        if ($this->opens_at !== null && $this->opens_at->isFuture()) {
            return ['key' => 'planned', 'label' => 'Запланирован', 'available' => false];
        }

        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return ['key' => 'closed', 'label' => 'Закрыт', 'available' => false];
        }

        if ($user && $attempts >= $this->max_attempts) {
            return ['key' => 'exhausted', 'label' => 'Попытки исчерпаны', 'available' => false];
        }

        return ['key' => 'available', 'label' => 'Доступен', 'available' => true];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Сессия тренажёра.
 *
 * Хранится на сервере: окно можно закрыть и продолжить. В localStorage
 * это невозможно, а терять прогресс тренировки при закрытии вкладки —
 * плохое поведение для вещи, которой пользуются регулярно.
 */
class TutorSession extends Model
{
    use HasFactory;

    protected $table = 'tutor_sessions';

    protected $fillable = [
        'user_id',
        'material_id',
        'status',
        'started_at',
        'finished_at',
        'generation_started_at',
        'context_window',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'generation_started_at' => 'datetime',
        'context_window' => 'array',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function material()
    {
        return $this->belongsTo(TutorMaterial::class, 'material_id');
    }

    public function items()
    {
        return $this->hasMany(TutorItem::class, 'session_id');
    }

    /**
     * Ответы по всем вопросам сессии — для статистики.
     *
     * hasManyThrough, а не hasMany: ответа принадлежит вопросу, а не
     * сессии напрямую. Прежний вариант подставлял item_id = session.id,
     * то есть молча отдавал ответы чужой сессии (у того же item_id).
     */
    /** Идёт ли сейчас генерация вопросов по сессии. */
    public function isGenerating(): bool
    {
        return $this->generation_started_at !== null;
    }

    public function responses()
    {
        return $this->hasManyThrough(
            TutorResponse::class,
            TutorItem::class,
            'session_id',
            'item_id'
        );
    }

    /** Сколько раз тренировались по чанку: основа дедупликации. */
    public function chunkAskedCount(int $chunkId): int
    {
        return (int) collect($this->context_window ?? [])
            ->firstWhere('chunk_id', $chunkId)['asked'] ?? 0;
    }

    /** Отметить, что чанк использован. */
    public function touchChunk(int $chunkId): void
    {
        $window = collect($this->context_window ?? []);
        $entry = $window->firstWhere('chunk_id', $chunkId) ?? ['chunk_id' => $chunkId, 'asked' => 0, 'correct' => 0];

        $entry['asked'] = (int) $entry['asked'] + 1;
        $window->push($entry);

        $this->context_window = $window->values()->all();
    }
}

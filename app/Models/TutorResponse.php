<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ответ пользователя в тренажёре.
 *
 * verdict: correct | partial | wrong | ungraded.
 * 'ungraded' — не то же, что 'wrong': ответ не оценён (свободный текст
 * без эталона или движок недоступен). Если считать ungraded как wrong,
 * в статистике «не знал» и «не смог проверить» сливаются.
 */
class TutorResponse extends Model
{
    use HasFactory;

    protected $table = 'tutor_responses';

    protected $fillable = [
        'item_id',
        'verdict',
        'answer',
        'feedback',
        'auto_score',
        'seconds_spent',
        'llm_meta',
    ];

    protected $casts = [
        'auto_score' => 'float',
        'seconds_spent' => 'integer',
        'llm_meta' => 'array',
    ];

    public const VERDICT_CORRECT = 'correct';

    public const VERDICT_PARTIAL = 'partial';

    public const VERDICT_WRONG = 'wrong';

    public const VERDICT_UNGRADED = 'ungraded';

    public function item()
    {
        return $this->belongsTo(TutorItem::class, 'item_id');
    }
}

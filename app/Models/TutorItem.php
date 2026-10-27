<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Сгенерированный вопрос.
 *
 * В колонках намеренно нет признака правильного варианта: варианты
 * лежат в options, а эталон — в reference_answer, и он отдаётся только
 * по явному запросу «показать эталон». Иначе угадать правильный ответ
 * можно было бы из ответа API, не ответив ни одного вопроса.
 */
class TutorItem extends Model
{
    use HasFactory;

    protected $table = 'tutor_items';

    protected $fillable = [
        'session_id',
        'chunk_id',
        'qtype',
        'question',
        'options',
        'reference_answer',
        'source_quote',
        'fingerprint',
        'asked_count',
        'backcheck_passed',
    ];

    protected $casts = [
        'options' => 'array',
        'asked_count' => 'integer',
        'backcheck_passed' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(TutorSession::class, 'session_id');
    }

    public function chunk()
    {
        return $this->belongsTo(TutorChunk::class, 'chunk_id');
    }

    public function responses()
    {
        return $this->hasMany(TutorResponse::class, 'item_id');
    }

    /**
     * Что отдаём пользователю.
     *
     * Без reference_answer и source_quote: эталон должен требовать
     * явного действия, иначе он висит в сетевом трафике рядом с
     * вопросом и делает тренажёр проверяемым на автомате.
     *
     * @return array<string,mixed>
     */
    public function toPlayerArray(): array
    {
        return [
            'id' => $this->id,
            'chunk_id' => $this->chunk_id,
            'qtype' => $this->qtype,
            'question' => $this->question,
            'options' => $this->qtype === 'mcq' ? array_values($this->options ?? []) : null,
        ];
    }
}

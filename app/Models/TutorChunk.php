<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Фрагмент материала — единица работы тренажёра.
 *
 * Именно фрагмент, а не файл, отдаётся модели: принцип «не знает тему —
 * переформулирует вопрос из фрагмента» держится именно на этом. Если
 * отдать модели файл целиком, она начнёт отвечать по смежным темам, и
 * проверять выдачу будет нечем.
 */
class TutorChunk extends Model
{
    use HasFactory;

    protected $table = 'tutor_chunks';

    protected $fillable = [
        'material_id',
        'seq',
        'content',
        'token_count',
        'embedding',
        'indexed_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'indexed_at' => 'datetime',
        'token_count' => 'integer',
        'seq' => 'integer',
    ];

    public function material()
    {
        return $this->belongsTo(TutorMaterial::class, 'material_id');
    }

    public function items()
    {
        return $this->hasMany(TutorItem::class, 'chunk_id');
    }

    /**
     * Отбор релевантных фрагментов полнотекстовым поиском.
     *
     * tsvector в приложении не пересчитывается на каждый запрос: он
     * вычисляется на лету, потому что выражение индекса — функция от
     * колонки, и в самой колонке его нет.
     *
     * Словарь simple, а не русский: в материалах полно технических
     * терминов («ПП-5», «АСУ», «ДТО»), на которых русский словарь
     * разваливается и молча теряет слова. simple не мешает, а работает
     * всегда.
     *
     * @param  string|null  $queryText  текст запроса; пустой — все подряд
     */
    public function scopeRelevant(Builder $query, ?string $queryText): Builder
    {
        $text = trim((string) $queryText);

        if ($text === '') {
            return $query;
        }

        return $query->whereRaw(
            "to_tsvector('simple', content) @@ plainto_tsquery('simple', ?)",
            [$text]
        );
    }

    /** Грубая оценка числа токенов: ~4 символа на токен для русского. */
    public static function estimateTokens(string $text): int
    {
        return (int) max(1, round(mb_strlen($text) / 4));
    }
}

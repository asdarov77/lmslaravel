<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * Внешняя ссылка, привязанная к модулю курса (links).
 *
 * Заполняется при импорте самолёта из imsmanifest.xml вместе с Course и
 * Aukstructure — в одной транзакции.
 */
class Link extends Model
{
    use HasFactory;
    protected $fillable = ['link','aukstructure_id'];

    protected static function booted(): void
    {
        // Дерево плеера кэшируется целиком (CoursePlayerService), а links()
        // входит в payload. Любое изменение ссылки делает кэш неактуальным —
        // сбрасываем точечно по course_id затронутого узла.
        $invalidate = function (?int $aukstructureId): void {
            $courseId = Aukstructure::whereKey($aukstructureId)->value('course_id');
            if ($courseId) {
                \App\Services\CoursePlayerService::invalidateTreeCache((int) $courseId);
            }
        };

        static::saved(fn (self $l) => $invalidate((int) $l->aukstructure_id));
        static::deleted(fn (self $l) => $invalidate((int) $l->aukstructure_id));
    }

    public function aukstructure()
    {
        return $this->belongsTo(Aukstructure::class);
    }
}

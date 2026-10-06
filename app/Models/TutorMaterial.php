<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Материал, по которому тренажёр генерирует вопросы.
 *
 * course_id — источник истины для прав: готовить индекс может
 * методист для любого курса, а тренироваться разрешено только по тем
 * курсам, которые назначены группе пользователя. Если бы здесь был
 * только file_id, проверка «свой ли курс» уехала бы в файлы и её
 * пришлось бы дублировать в каждом месте.
 */
class TutorMaterial extends Model
{
    use HasFactory;

    protected $table = 'tutor_materials';

    protected $fillable = [
        'course_id',
        'category_id',
        'file_id',
        'title',
        'source',
        'status',
        'chunks_count',
        'error',
    ];

    protected $casts = [
        'chunks_count' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_INDEXED = 'indexed';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_FAILED = 'failed';

    /** Готов ли материал к тренировке. */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_INDEXED && $this->chunks_count > 0;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Внешний ключ указан явно: по умолчанию Laravel выводит его из
     * имени модели — tutor_material_id, а колонка называется
     * material_id. Ошибка проявляется не сразу: связь молча отдаёт
     * пустой результат, и материал выглядит пустым без ошибок.
     */
    public function chunks()
    {
        return $this->hasMany(TutorChunk::class, 'material_id')->orderBy('seq');
    }

    public function sessions()
    {
        return $this->hasMany(TutorSession::class, 'material_id');
    }

    /** Сколько фрагментов ещё не израсходовано. */
    public function freshChunksCount(): int
    {
        return $this->chunks()->count();
    }
}

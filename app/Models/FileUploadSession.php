<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Незавершённая загрузка файлового менеджера.
 *
 * Строка описывает не файл (файла ещё нет), а разбираемый на части
 * временный каталог. Нужна, чтобы:
 *
 *  1. адрес частей был известен до первой записи (init идёт раньше
 *     первого чанка);
 *  2. загрузку можно было продолжить после обрыва — по отпечатку
 *     fingerprint клиент получает тот же upload_id и список принятых
 *     частей вместо «начать заново»;
 *  3. можно было посчитать, сколько байт реально принято, и не дать
 *     одному запросу заполнить диск вопреки объявленному размеру.
 *
 * Поле completed_at оставлено и после сборки: оно защищает от повторной
 * сборки из двух вкладок — вторая получает «уже собран» и не создаёт
 * второй файл.
 */
class FileUploadSession extends Model
{
    protected $table = 'file_upload_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'folder_id',
        'filename',
        'size',
        'mime',
        'fingerprint',
        'chunk_bytes',
        'total_chunks',
        'received_bytes',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'folder_id' => 'integer',
            'size' => 'integer',
            'chunk_bytes' => 'integer',
            'total_chunks' => 'integer',
            'received_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, static> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FileFolder, static> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(FileFolder::class, 'folder_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Отпечаток незавершённой загрузки.
     *
     * Включает имя, размер и время изменения файла: по этой тройке
     * браузер узнаёт «тот самый» файл после перезагрузки страницы.
     * Имя каталога намеренно НЕ входит: перенос незавершённой загрузки
     * в другую папку — осмысленное действие, и init его обслуживает.
     */
    public static function fingerprint(int $userId, string $filename, int $size, ?int $lastModified): string
    {
        return hash('sha256', implode('|', [
            $userId,
            $filename,
            $size,
            $lastModified ?? 0,
        ]));
    }
}

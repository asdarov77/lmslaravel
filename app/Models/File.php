<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Загруженные пользователем файлы (files), belongsTo user.
 *
 * Используется как file_id в материалах тренажёра. $guarded = [] — валидация
 * загрузки в контроллере (FilesController).
 *
 * Поля path, folder_id, size, mime добавлены файловым менеджером. У
 * записей, созданных до него, path = null: они лежат на диске по схемам,
 * которые менеджер не знает (FilesController::upload писал в
 * uploads/{ФИО}, FilesController::store — в /public/{ФИО}_{id}/{тип}/), и
 * выдумывать для них путь значило бы либо указать не туда, либо
 * переименовать лишнее. Поэтому scopeManaged() отделяет «файлы
 * менеджера» от прочих, а старые endpoints продолжают работать как
 * раньше.
 */
class File extends Model
{

//  protected $fillable = [
//    'name', 'type', 'extension', 'user_id'
//  ];

  protected $guarded =[]; // разрешение добавления аттрибутов в базу, защищать аттрибут не нужно

  /** @return array<string, string> */
  protected function casts(): array
  {
    return [
      'user_id' => 'integer',
      'folder_id' => 'integer',
      // int, а не string: 4 ГБ не помещаются в 32-битный int, а
      // JSON-сериализация 64-битного числа в JS даёт точное число.
      'size' => 'integer',
    ];
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /** @return BelongsTo<FileFolder, static> */
  public function folder(): BelongsTo
  {
    return $this->belongsTo(FileFolder::class, 'folder_id');
  }

  /**
   * Только файлы файлового менеджера (есть путь на диске).
   *
   * Отдельный scope, а не условие в контроллере: правило «файлом
   * менеджера считается файл с непустым path» должно звучать в одном
   * месте, иначе один забытый вызов начнёт отдавать чужие старые записи.
   */
  public function scopeManaged(Builder $query): Builder
  {
    return $query->whereNotNull('path');
  }

  /** Файлы непосредственно в каталоге $folderId (null — корень). */
  public function scopeInFolder(Builder $query, ?int $folderId): Builder
  {
    return $folderId === null
      ? $query->whereNull('folder_id')
      : $query->where('folder_id', $folderId);
  }

  /**
   * Плоское представление для интерфейса файлового менеджера.
   *
   * @return array<string, mixed>
   */
  public function toManagerArray(): array
  {
    return [
      'id' => (int) $this->id,
      'name' => $this->name,
      'folder_id' => $this->folder_id === null ? null : (int) $this->folder_id,
      'size' => $this->size === null ? null : (int) $this->size,
      'extension' => $this->extension,
      'mime' => $this->mime,
      'type' => $this->type,
      'updated_at' => $this->updated_at?->toIso8601String(),
      'download_url' => route('api.filemanager.download', ['file' => $this->id]),
    ];
  }
}

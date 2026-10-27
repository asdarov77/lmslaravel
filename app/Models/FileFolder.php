<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Папка файлового менеджера.
 *
 * Папка принадлежит пользователю (user_id) и вложена в другую папку
 * того же пользователя (parent_id, null — корень). Имя уникально в
 * пределах одного каталога.
 *
 * Почему папка в базе, а не просто каталог на диске: каталог не помнит
 * владельца и не отличает «папку» от мусора, оставшегося после сбоя.
 * С таблицей список папок — один запрос, права проверяются по user_id,
 * а расхождение с диском можно найти и починить отдельной командой
 * (см. App\Support\FileManager\FolderTree и Location).
 */
class FileFolder extends Model
{
    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Folder-like, self> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<static> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, static> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<File, static> */
    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'folder_id');
    }

    /** Файлы в корне пользователя. */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Непосредственные дети папки. */
    public function scopeChildrenOf(Builder $query, ?int $parentId): Builder
    {
        return $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);
    }

    /** Файлы, заведённые файловым менеджером (у них есть путь на диске). */
    public function scopeManaged(Builder $query): Builder
    {
        return $query->whereNotNull('path');
    }

    /**
     * Плоское представление для интерфейса.
     *
     * @return array{id: int, name: string, parent_id: int|null, updated_at: string|null}
     */
    public function toManagerArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id === null ? null : (int) $this->parent_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

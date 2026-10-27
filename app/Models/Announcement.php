<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Объявление для обучаемых и сотрудников.
 *
 * Аудитория хранится комбинацией флагов и json-массивов: объявление
 * может быть для всех, для группы, курса или роли одновременно.
 * @see visibilityFor() — единственное место, где решается, кому видно.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property int|null $author_id
 * @property bool $audience_all
 * @property array|null $audience_groups
 * @property array|null $audience_courses
 * @property array|null $audience_roles
 * @property bool $pinned
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property-read User|null $author
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'author_id',
        'audience_all',
        'audience_groups',
        'audience_courses',
        'audience_roles',
        'pinned',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'audience_all' => 'boolean',
        'audience_groups' => 'array',
        'audience_courses' => 'array',
        'audience_roles' => 'array',
        'pinned' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Опубликовано ли объявление (срок наступил, срок не истёк). */
    public function isLive(?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now();

        if ($this->published_at !== null && $this->published_at->gt($now)) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->gte($now);
    }

    /**
     * Видно ли объявление этому пользователю.
     *
     * Проверка идёт по тому же признаку «управляющий», что и в меню:
     * права приходят из role_user, а строке users.role доверять нельзя.
     * Руководителю показывается всё — иначе он не увидит объявление,
     * адресованное «всем сотрудникам».
     */
    public function visibilityFor(?User $user): bool
    {
        if (! $this->isLive()) {
            return false;
        }

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasPermission('users.permissions')) {
            return true;
        }

        if ($this->audience_all) {
            return true;
        }

        if ($this->audience_roles && in_array(
            $this->userRoleSlug($user),
            array_map(fn ($r) => mb_strtolower((string) $r), $this->audience_roles),
            true
        )) {
            return true;
        }

        if ($this->audience_groups && $user->group_id !== null) {
            if (in_array((int) $user->group_id, array_map('intval', $this->audience_groups), true)) {
                return true;
            }
        }

        if ($this->audience_courses) {
            $courseIds = array_map('intval', $this->audience_courses);

            if ($user->group_id !== null && $courseIds !== []) {
                return \App\Models\Group2learning::where('group_id', $user->group_id)
                    ->whereIn('course_id', $courseIds)
                    ->exists();
            }
        }

        return false;
    }

    /** Роль пользователя в виде slug'а, как их зовёт RBAC. */
    private function userRoleSlug(User $user): ?string
    {
        return $user->roles->first()->slug ?? null;
    }
}
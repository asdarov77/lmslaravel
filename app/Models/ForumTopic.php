<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Тема форума: вопрос по курсу или уроку.
 *
 * Видимость: тема с пустым group_id видна всем, кто открыл курс
 * (CourseAccess), приватная — только своей группе. Это сделано полем
 * group_id, а не флагом «приватно», потому что приватность всегда
 * привязана к конкретной группе, а не к абстрактному «закрыто».
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property int $course_id
 * @property int|null $aukstructure_id
 * @property int $author_id
 * @property int|null $group_id
 * @property bool $pinned
 * @property bool $locked
 * @property int|null $solution_post_id
 * @property int $views
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property-read Course $course
 * @property-read Aukstructure|null $lesson
 * @property-read User $author
 * @property-read HasMany<ForumPost> $posts
 */
class ForumTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'course_id',
        'aukstructure_id',
        'author_id',
        'group_id',
        'pinned',
        'locked',
        'views',
        'last_activity_at',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'locked' => 'boolean',
        'views' => 'integer',
        'last_activity_at' => 'datetime',
    ];

    protected $attributes = [
        'pinned' => false,
        'locked' => false,
        'views' => 0,
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Aukstructure::class, 'aukstructure_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class)->orderBy('id');
    }

    public function solution(): HasOne
    {
        return $this->hasOne(ForumPost::class, 'id', 'solution_post_id');
    }

    /** Видна ли тема этому пользователю. */
    public function visibleFor(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->group_id !== null && (int) $this->group_id !== (int) $user->group_id) {
            // Руководитель смотрит все темы: без этого он не увидит
            // жалоб и вопросов из групп, за которые отвечает.
            $isManager = $user->isSuperAdmin() || $user->hasPermission('groups.manage');

            return $isManager;
        }

        return true;
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ответ в теме форума.
 *
 * @property int $id
 * @property int $forum_topic_id
 * @property int $author_id
 * @property string $body
 * @property bool $from_staff
 * @property-read ForumTopic $topic
 * @property-read User $author
 */
class ForumPost extends Model
{
    use HasFactory;

    protected $table = 'forum_posts';

    protected $fillable = [
        'forum_topic_id',
        'author_id',
        'body',
        'from_staff',
    ];

    protected $casts = [
        'from_staff' => 'boolean',
    ];

    protected $attributes = [
        'from_staff' => false,
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
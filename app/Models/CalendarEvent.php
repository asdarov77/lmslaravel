<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ручное событие календаря.
 *
 * Отличается от производных событий (периоды обучения, сроки сдачи):
 * те собираются из group2learnings и правятся там же, а это — запись,
 * заведённая человеком.
 *
 * user_id — автор (кто может удалить), group_id — кому видно.
 * Событие без группы видно всем.
 */
class CalendarEvent extends Model
{
    use HasFactory;

    protected $table = 'calendar_events';

    protected $fillable = [
        'title',
        'start',
        'end',
        'user_id',
        'group_id',
    ];

    protected $casts = [
        'start' => 'date',
        'end' => 'date',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
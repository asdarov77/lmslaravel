<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Выданный бейдж.
 *
 * Бейджи выводятся из агрегатов (config/gamification.php), а не
 * назначаются вручную. Строка — факт выдачи: в галерее видно, что
 * достижение получено, и когда.
 */
class UserBadge extends Model
{
    protected $fillable = [
        'user_id',
        'badge_key',
        'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
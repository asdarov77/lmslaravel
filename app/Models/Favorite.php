<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Избранное пользователя (favorites: user_id, course_id, title).
 *
 * Все связи с моделями закомментированы, поэтому избранное сейчас читается
 * только напрямую из таблицы. В index() контроллера фильтра по user_id нет —
 * это дыра, а не задумка.
 */
class Favorite extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'course_id',
        'title'
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    // public function item()
    // {
    //     return $this->belongsTo(Item::class);
    // }
}

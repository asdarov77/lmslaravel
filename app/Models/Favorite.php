<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

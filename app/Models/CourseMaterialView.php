<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Событийный прогресс просмотра материала (event-based progress).
 */
class CourseMaterialView extends Model
{
    public $timestamps = false;

    protected $table = 'course_material_views';

    protected $fillable = [
        'user_id',
        'course_id',
        'aukstructure_id',
        'seconds_viewed',
        'max_scroll_percent',
        'completed',
        'last_viewed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'last_viewed_at' => 'datetime',
    ];
}

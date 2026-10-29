<?php

namespace App\Models;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



/**
 * Модуль курса (aukstructures) с самоссылкой parent/children.
 *
 * Иерархия используется в LessonsController для построения дерева тем и в
 * Exam как пара отбора вопросов вместе со специальностью.
 */
class Aukstructure extends Model
{
    use HasFactory;
    use Filterable;
    // M4 (design-manifest §2.3): unlock_mode/unlock_at/prerequisite_id/min_score —
    // правила доступа («drip content»), пишет PUT /aukstructure/{id}/rules.
    protected $fillable = ['title','course_id','parent_id','type','position','description','categories','identifier','unlock_mode','unlock_at','prerequisite_id','min_score'];

    protected $casts = [
        'unlock_at' => 'datetime',
        'min_score' => 'integer',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
        public function links()
    {
        return $this->hasMany(Link::class);
    }
        public function course()
    {
        return $this->belongsTo(Course::class);
    }

// потомки родители вспомогательное
    public function parent()
    {
        return $this->belongsTo(Aukstructure::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Aukstructure::class, 'parent_id');
    }
 
}

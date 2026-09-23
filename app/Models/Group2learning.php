<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group2learning extends Model
{
    use HasFactory;
    use Filterable;
    protected $fillable = ['course_id','group_id','category_id','parent_id'];

// fix связи !!!, имея категорию и курс,я могу определить связь через таблицу

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
        public function category()
    {
        return $this->hasOne(Category::class);
    }
        public function course()
    {
        return $this->belongsTo(Course::class);
    }

}

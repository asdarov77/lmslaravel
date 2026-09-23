<?php

namespace App\Models;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class Aukstructure extends Model
{
    use HasFactory;
    use Filterable;
    protected $fillable = ['title','course_id','parent_id','type','description','categories','identifier'];

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

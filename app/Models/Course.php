<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\User;
use App\Models\Aircraft;
use App\Models\Aukstructure;

class Course extends Model
{
    use HasFactory;
    use Filterable;
    protected $fillable = [
        'title',
        'short_description',
        'long_description',
        'path',
        'visible',
        'aircraft_id',
    ];
    // public function categories() {
    //        return $this->belongsToMany(Category::class,
    //        'category_course',
    //        'course_id',
    //        'category_id'
    //     );
    // }
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function aircraft()
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function aukstructures() {
        return $this->hasMany(Aukstructure::class);
    }

  //  public function groups()
  //  {
  //      return $this->belongsToMany(Group::class);
  //  }


     public function group2learnings() {
           return $this->hasMany(Group2learning::class);
    }

}

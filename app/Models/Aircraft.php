<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;


class Aircraft extends Model
{
    use HasFactory;
    //protected $guarded =[];
    protected $table = 'aircrafts';
    // public function categories() {
    //        return $this->belongsToMany(Category::class,
    //        'category_course',
    //        'course_id',
    //        'category_id'
    //     );
    // }
//      public function categories() {
//          return $this->belongsToMany(Category::class);
//   }

public function courses() {
   return $this->hasMany(Course::class);
}
public function categories() {
   return $this->hasMany(Category::class);
}

}

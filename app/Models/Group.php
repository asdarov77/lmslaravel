<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\User;

/**
 * Учебная группа (groups).
 *
 * Точка привязки всех проверок области видимости: «своя группа» в
 * CourseVisibility, UserPolicy, Group2learningPolicy, CalendarController
 * определяется через неё.
 */
class Group extends Model
{
    use HasFactory;
    //protected $guarded =[];
// public function user()
//   {
//     return $this->belongsTo(User::class);
//   }
     public function users() {
           return $this->hasMany(User::class);
    }
     public function group2learnings() {
           return $this->hasMany(Group2learning::class);
    }

}

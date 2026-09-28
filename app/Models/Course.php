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
        'status',
        'category_id',
        'aircraft_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Инструкторы курса — пользователи с ролью «Инструктор» из групп, набранных на курс
    public function instructors()
    {
        return $this->belongsToMany(User::class, 'group2learnings', 'course_id', 'group_id')
            ->withPivot(['id', 'teacher', 'typeOfLesson', 'study_from', 'study_to'])
            ->where('users.role', 'Инструктор');
    }

    // Обучаемые курса — пользователи, состоящие в группах, набранных на курс
    public function students()
    {
        return $this->belongsToMany(User::class, 'group2learnings', 'course_id', 'group_id')
            ->withPivot(['id', 'teacher', 'typeOfLesson', 'study_from', 'study_to'])
            ->where('users.role', 'Обучаемый');
    }

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

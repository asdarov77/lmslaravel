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
        'category_id',
        'status',
        'duration',
    ];

    /**
     * Поля API v1, которые являются алиасами существующих колонок.
     * Принимаются оба варианта на входе и отдаются оба в ответе.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
        'description',
    ];

    /**
     * Алиас title для API v1.
     */
    public function getNameAttribute(): ?string
    {
        return $this->title;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->title = $value;
    }

    /**
     * Алиас short_description для API v1.
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->short_description;
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->short_description = $value;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function instructors()
    {
        return $this->belongsToMany(User::class, 'course_instructors');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'course_students');
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

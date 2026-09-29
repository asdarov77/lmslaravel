<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Aircraft;
use App\Models\Course;
use App\Models\User;
use App\Models\Question;

class Category extends Model
{
    use HasFactory;
    use Filterable;
    protected $guarded = [];

    /**
     * API v1 отдаёт категорию с полем name (алиас title).
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->title;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->title = $value;
    }
    //    public function courses() {
    //        return $this->belongsToMany(Course::class,
    //        'category_course',          
    //        'category_id',
    //        'course_id'
    //     );


    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
    
    // связь многие ко многим с таблицей пользователей
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

        public function aukstructures()
    {
        return $this->belongsToMany(Aukstructure::class);
    }
        public function aircraft()
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

}

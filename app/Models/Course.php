<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\User;
use App\Models\Aircraft;
use App\Models\Aukstructure;

/**
 * Курс (courses).
 *
 * belongsTo категория и самолёт, hasMany модули (aukstructures) и учебные
 * записи (group2learnings), manyToMany инструкторы и студенты через
 * course_instructors / course_students, manyToMany специальности через
 * category_course.
 *
 * Алиасы name=title и description=short_description в $appends существуют для
 * API v1: фронт v1 и внутренний код зовут одно поле по-разному.
 *
 * Один курс может быть привязан к нескольким специальностям — поэтому право на
 * материал определяется парой (курс, специальность), а не курсом.
 */
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
        'opens_at',
        'closes_at',
        'aircraft_id',
        'category_id',
        'status',
        'duration',
    ];

    protected $casts = [
        'opens_at' => 'date',
        'closes_at' => 'date',
    ];

    /**
     * Открыт ли курс сейчас: окно расписания, если задано, перекрывает
     * флаг visible. Не задано окно — курс просто читает visible.
     */
    public function isOpenNow(): bool
    {
        if (! $this->visible) {
            return false;
        }

        $today = now()->toDateString();

        if ($this->opens_at !== null && $today < $this->opens_at) {
            return false;
        }

        if ($this->closes_at !== null && $today > $this->closes_at) {
            return false;
        }

        return true;
    }

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

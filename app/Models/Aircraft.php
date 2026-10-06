<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;


/**
 * Тип воздушного судна (aircrafts: title + path).
 *
 * Поле path задаёт каталог контента private/<путь>: по нему находятся и
 * список папок (AircraftController), и материалы (PrivateController), и
 * internal-location nginx. Переименование path здесь ломает выдачу сразу у
 * всех курсов этого типа.
 */
class Aircraft extends Model
{
    use HasFactory;

    protected $table = 'aircrafts';

    /**
     * Без $fillable вызов Aircraft::create([...]) молча отбрасывал бы поля
     * (модель не $guarded), и импортёр создавал самолёт без title/path.
     */
    protected $fillable = [
        'title',
        'path',
    ];
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

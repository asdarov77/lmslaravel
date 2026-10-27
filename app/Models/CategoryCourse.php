<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pivot-модель category_course (курс ↔ специальность).
 *
 * Существует только для напоминания: в коде связь объявлена прямо на Course и
 * Category, а сама модель нигде не инстанцируется.
 */
class CategoryCourse extends Model
{
    use HasFactory;
    protected $table = 'category_course';
}

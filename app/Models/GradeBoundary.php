<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Границы оценочной шкалы (grade_boundaries: boundary, grade).
 *
 * Связей с моделями нет: шкала общая. Меняется по порядковой позиции, а не по
 * id (см. GradeBoundaryController) — порядок здесь и есть смысл записи.
 */
class GradeBoundary extends Model
{
    use HasFactory;

    protected $table = 'grade_boundaries';
    protected $fillable = ['boundary', 'grade'];
}


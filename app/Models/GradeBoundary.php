<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeBoundary extends Model
{
    protected $table = 'grade_boundaries';
    protected $fillable = ['boundary', 'grade'];
}


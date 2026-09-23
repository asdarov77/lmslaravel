<?php

namespace App\Models;
use App\Traits\Filterable;
use App\Models\Answer;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;
    use Filterable;
    //protected $fillable = ['question_text', /* other fillable columns */];
    protected $guarded = [];
    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

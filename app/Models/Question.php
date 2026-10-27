<?php

namespace App\Models;
use App\Traits\Filterable;
use App\Models\Answer;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Вопрос банка (questions) с hasMany answers.
 *
 * Вопрос принадлежит паре (category_id, aukstructure_id). $guarded = [] и
 * трейт Filterable — запись полностью открыта коду, валидация живёт в
 * контроллере и в фильтрах.
 */
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

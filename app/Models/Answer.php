<?php

namespace App\Models;
use App\Models\Question;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Вариант ответа (answers: answer, is_correct, question_id).
 *
 * Носитель правильного варианта. Из этого следует важное: любой, кто видит
 * вопрос вместе с вариантами, видит и ответы — поэтому чтение банка отделено
 * от сдачи экзамена правами (QuestionBankPolicy).
 */
class Answer extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
        public function testResults()
    {
        return $this->hasMany(TestResult::class);
    }
}

                          <?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Исторический результат прохождения теста (test_results).
 *
 * Остаток прежней схемы оценивания: нет casts, нет HasFactory, нет связей.
 * Новые результаты пишутся в exam_attempts, и на это поле уже опирается только
 * отчётность.
 */
class TestResult extends Model
{
    protected $fillable = ['result'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }
}

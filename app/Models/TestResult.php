                          <?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

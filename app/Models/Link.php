<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * Внешняя ссылка, привязанная к модулю курса (links).
 *
 * Заполняется при импорте самолёта из imsmanifest.xml вместе с Course и
 * Aukstructure — в одной транзакции.
 */
class Link extends Model
{
    use HasFactory;
    protected $fillable = ['link','aukstructure_id'];
    
    public function aukstructure()
    {
        return $this->belongsTo(Aukstructure::class);
    }
}

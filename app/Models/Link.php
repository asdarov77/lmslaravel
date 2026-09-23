<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Link extends Model
{
    use HasFactory;
    protected $fillable = ['link','aukstructure_id'];
    
    public function aukstructure()
    {
        return $this->belongsTo(Aukstructure::class);
    }
}

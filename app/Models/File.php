<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{

//  protected $fillable = [
//    'name', 'type', 'extension', 'user_id'
//  ];

  protected $guarded =[]; // разрешение добавления аттрибутов в базу, защищать аттрибут не нужно
  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pivot-модель role_user (role_id, user_id).
 *
 * Существует ради единой точки синхронизации ролей в AuthController::chroll.
 */
class RoleUser extends Model
{
    use HasFactory;

    protected $table = 'role_user';

    protected $fillable = [
        'role_id',
        'user_id',
    ];
}

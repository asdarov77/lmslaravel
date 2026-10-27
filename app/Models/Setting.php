<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Универсальные настройки key-value (settings: name, value, type).
 *
 * Сюда пишутся переключатели, которые должны действовать без правки .env и без
 * перезапуска воркеров: content_delivery (см. ContentDelivery) и
 * tutor_enabled (см. TutorSettings).
 *
 * Значение хранится строкой. Тип в type используется только как подсказка для
 * UI; приведение к булеву или перечислению делает читающий класс, и недопустимое
 * значение там НЕ молча заменяется дефолтом — пишется в лог.
 */
class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'value', 'type']; // Добавлен столбец "type" в список заполняемых полей
}


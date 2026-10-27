<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Заготовка pivot-модели для aukstructure_category.
 *
 * ВАЖНО: явного имени таблицы здесь нет. По соглашению Laravel получилось бы
 * aukstructure_categories, а реальный pivot называется aukstructure_category.
 * Связь в коде объявлена прямо на Aukstructure/Category, поэтому модель не
 * используется — но обращение к ней молча упало бы на «таблицы нет».
 */
class AukstructureCategory extends Model
{
    use HasFactory;
}

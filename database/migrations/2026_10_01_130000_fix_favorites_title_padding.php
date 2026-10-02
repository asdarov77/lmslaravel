<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * char -> string в favorites.title.
 *
 * Зачем: char(255) дополняет значение пробелами до 255 символов, поэтому
 * заголовок избранного сохранялся как «Модуль» + ~240 пробелов. В списке
 * это выглядело как «сломанные» данные, а любое сравнение по title
 * (например, поиск по избранному) не находило совпадений.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->char('title')->nullable(false)->change();
        });
    }
};

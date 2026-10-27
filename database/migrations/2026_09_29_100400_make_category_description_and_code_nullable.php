<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Контроллер объявляет description и code как nullable, но в БД они NOT NULL —
     * запрос без этих полей падал с 500. Приводим схему к контракту валидации.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->char('code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
            $table->char('code')->nullable(false)->change();
        });
    }
};

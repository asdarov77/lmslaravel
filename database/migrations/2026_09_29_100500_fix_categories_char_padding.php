<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Исходная миграция объявляла title/code как `char` (bpchar(255)).
     * PostgreSQL дополняет значения пробелами до 255 символов, поэтому API
     * отдавал "title" с хвостовыми пробелами (и ломал сравнения на фронтенде).
     * Переводим колонки в varchar — при ALTER TYPE хвостовые пробелы срезаются.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->string('code', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->char('title', 255)->change();
            $table->char('code', 255)->nullable()->change();
        });
    }
};

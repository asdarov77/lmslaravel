<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Факт просмотра материала (event-based progress из P2).
 * Даёт преподавателю честную статистику: материал считается прочитанным
 * не по факту «доскроллить», а по событию view со временем в кадре.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_material_views')) {
            return;
        }

        Schema::create('course_material_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('aukstructure_id')->index();
            // суммарное время в кадре, сек (накопительно через upsert)
            $table->unsignedInteger('seconds_viewed')->default(0);
            // достигнутый процент прокрутки 0..100
            $table->unsignedTinyInteger('max_scroll_percent')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('last_viewed_at')->nullable();

            $table->unique(['user_id', 'aukstructure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_material_views');
    }
};

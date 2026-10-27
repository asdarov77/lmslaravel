<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сессия тренажёра.
 * 
 * Живёт на сервере намеренно: по плану окно можно закрыть и продолжить
 * позже, а localStorage для этого не годится — он живёт в одном браузере
 * и теряется вместе с ним.
 * 
 * material_id, а не course_id: тренировка идёт по конкретному
 * материалу. Курс известен через material.
 * 
 * context_window — какие фрагменты сессия уже затронула и с каким
 * результатом. Это и есть «нарастающий контекст», и одновременно
 * основа дедупликации: вопрос по чанку, который уже использовался трижды,
 * в четвёртый раз не предлагается.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('tutor_materials')->cascadeOnDelete();

            // active | finished | abandoned
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // [{"chunk_id":1,"asked":2,"correct":1}, ...]
            $table->jsonb('context_window')->nullable();

            $table->timestamps();

            // Одна активная сессия на материал: иначе два окна тренажёра
            // делят один и тот же вопрос и портят статистику.
            $table->index(['user_id', 'material_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_sessions');
    }
};

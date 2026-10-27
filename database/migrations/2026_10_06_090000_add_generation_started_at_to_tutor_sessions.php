<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Метка «генерация идёт» в сессии тренажёра.
 *
 * Зачем: без неё фронт определял готовность по наличию вопросов, и при
 * первом пустом ответе переставал опрашивать сервер. Генерация при этом
 * продолжалась в фоне, и вопросы появлялись позже — уже никто их не
 * забирал. Пользователь видел «вопросы закончились» при полной ленте.
 *
 * Теперь признак ставится при постановке задания и снимается заданием
 * по завершении, поэтому «готовятся вопросы» означает ровно то, что
 * задание ещё работает, а не «вопросов пока нет».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tutor_sessions')) {
            return;
        }

        Schema::table('tutor_sessions', function (Blueprint $table) {
            $table->timestamp('generation_started_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tutor_sessions') || ! Schema::hasColumn('tutor_sessions', 'generation_started_at')) {
            return;
        }

        Schema::table('tutor_sessions', function (Blueprint $table) {
            $table->dropColumn('generation_started_at');
        });
    }
};

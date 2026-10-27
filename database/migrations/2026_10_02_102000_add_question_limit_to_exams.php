<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ограничение количества вопросов в экзамене.
 *
 * Зачем: экзамен отбирает вопросы по паре (модуль, специальность), а в
 * реальном модуле их сотни — на проверке знаний вышло 120 вопросов, что
 * непроходимо. Лимит задаёт методист; NULL означает «все вопросы по
 * паре» и сохраняет прежнее поведение.
 *
 * Отбор детерминированный (по id), а не случайный: иначе повторная
 * попытка давала бы другой набор вопросов, и сравнивать результаты
 * попыток было бы бессмысленно.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exams', 'question_limit')) {
            return;
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedSmallInteger('question_limit')->nullable()->after('passing_score');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('exams', 'question_limit')) {
            return;
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('question_limit');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Запись групп на курсы: необязательные категория, инструктор и вид занятия.
 *
 * Зачем: колонки были NOT NULL без указания прочерка, поэтому любое
 * неполное заполнение формы (не выбрана категория / инструктор / вид
 * занятия) давало 500 на уровне СУБД — без внятного сообщения.
 * Теперь это допустимое состояние: NULL и означает «не задано».
 *
 * Заодно char -> string: char(N) дополняет значение пробелами до N,
 * поэтому вид занятия сохранялся как «Лекция» + 245 пробелов, и любое
 * сравнение ('Лекция' === typeOfLesson) давало ложь.
 *
 * study_from/study_to остаются обязательными — без сроков запись
 * бессмысленна.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group2learnings', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->string('teacher')->nullable()->change();
            $table->string('typeOfLesson')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('group2learnings', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->string('teacher')->nullable(false)->change();
            $table->string('typeOfLesson')->nullable(false)->change();
        });
    }
};
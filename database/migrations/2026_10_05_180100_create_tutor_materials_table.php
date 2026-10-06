<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Материалы, по которым тренажёр генерирует вопросы.
 * 
 * Зачем отдельная таблица, а не читать файлы курса на лету: индекс
 * дорогой (чанки, эмбеддинги), и он переживает перезаливку файлов. Плюс
 * статус индексации — без него «готовим вопросы» и «вопросы готовы» не
 * различить, и страница показывала бы пустоту вместо «идёт подготовка».
 * 
 * category_id и file_id nullable не для красивости: часть материала
 * приходит из файла курса (file_id), часть из общего раздела
 * специальности (category_id без файла), часть — из учебного материала,
 * загруженного отдельно.
 * 
 * course_id — источник истины для прав: тренажёр работает только по
 * курсам, назначенным группе пользователя (Group2learning). Именно на
 * этом строится запрет утечки: индекс можно готовить для любого курса
 * методистом, а тренироваться — только по своему.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('file_id')->nullable();

            $table->string('title');

            // Где взялся материал: upload | lyx | gift-context | course
            $table->string('source')->default('course');

            // pending | indexed | failed | empty
            //
            // empty — отдельное состояние от failed: материал проиндексирован,
            // но текста в нём нет (например, только картинки). Это не ошибка
            // и не бесконечный retry, а «по этому материалу вопросов не будет».
            $table->string('status')->default('pending');

            $table->unsignedInteger('chunks_count')->default(0);
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_materials');
    }
};

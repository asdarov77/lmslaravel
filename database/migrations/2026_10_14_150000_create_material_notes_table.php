<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заметки студента к материалам курса.
 *
 * Пользователь может писать заметки к любому материалу (уроку,
 * экзамену, модулю) курса. Заметки привязаны к aukstructure (узлу
 * структуры курса) и пользователю.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('aukstructure_id')->constrained('aukstructures')->cascadeOnDelete();

            // Сам текст заметки. Ограничиваем 5000 символов —
            // достаточно для конспекта, но не для хранения файлов.
            $table->text('content')->nullable();

            $table->timestamps();

            // Один пользователь — одна заметка на материал.
            $table->unique(['user_id', 'aukstructure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_notes');
    }
};
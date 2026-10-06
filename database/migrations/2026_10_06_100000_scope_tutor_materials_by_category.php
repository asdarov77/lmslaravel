<?php

use App\Models\TutorMaterial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Специальность как часть идентичности материала тренажёра.
 *
 * Зачем: видимость тренажёра определялась парой (группа, курс). Одного
 * курса достаточно, чтобы группа видела материалы ЧУЖИХ специальностей:
 * в этой базе один курс «Конструкция самолета» привязан сразу к шести
 * специальностям (командир экипажа, штурман, бортовой радист и другие),
 * а запись группы в group2learnings несёт СВОЮ category_id. Лётчик,
 * записанный на этот курс как командир экипажа, получал материалы и
 * радиста — материалы специальности, к которой его не записывали.
 *
 * Теперь материал принадлежит паре (курс, специальность), и материал
 * тренажёра показывается группе только если есть запись ровно на эту
 * пару. Несколько специальностей у группы — это несколько записей, и
 * каждая даёт свои материалы: ровно то, что описано как «каждая группа
 * записывается на свою специальность, может быть на несколько, но
 * другой записью».
 *
 * Материалы без специальности удаляются: они производные (восстанавливаются
 * индексацией), и оставить их значило бы оставить неработающие записи,
 * которые никто не увидит.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tutor_materials')) {
            return;
        }

        TutorMaterial::whereNull('category_id')->delete();

        Schema::table('tutor_materials', function ($table) {
            // Пара (курс, специальность) определяет материал: повторная
            // индексация обновляет существующий, а не плодит копии.
            $table->unique(['course_id', 'category_id'], 'tutor_materials_course_category_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tutor_materials')) {
            return;
        }

        Schema::table('tutor_materials', function ($table) {
            $table->dropUnique('tutor_materials_course_category_unique');
        });
    }
};
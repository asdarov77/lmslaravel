<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Автор и группа ручного события календаря.
 *
 * Зачем: у store/destroy не было чьё это событие. Любой, у кого есть
 * users.courses, удалял чужое событие, а в ленте оно показывалось
 * ВСЕМ группам — то есть заметка одной группы висела в календаре
 * другой. Теперь у события есть автор, удалять может он сам (или
 * суперадмин), а группа ограничивает видимость.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_events', 'user_id')) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('calendar_events', 'group_id')) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->foreignId('group_id')->nullable()->after('user_id')
                    ->constrained('groups')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
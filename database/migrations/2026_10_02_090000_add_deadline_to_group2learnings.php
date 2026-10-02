<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Дедлайн периода обучения.
 *
 * Зачем: у записи группы на курс есть период (study_from/study_to — когда
 * группа занимается), но не было даты, к которой обучающийся обязан
 * закончить. Из-за этого «Подходит к завершению» на дашборде считалось
 * по study_to — концу периода, а не по реальному сроку сдачи, и
 * календарь не мог показать ничего жёстче «занятия идут».
 *
 * Nullable и без значения по умолчанию: существующие записи остаются
 * валидными, дедлайн появляется только там, где методист его задал.
 * Откат — dropColumn, данные не теряются, так как колонка новая.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('group2learnings', 'deadline')) {
            return;
        }

        Schema::table('group2learnings', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('study_to');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('group2learnings', 'deadline')) {
            return;
        }

        Schema::table('group2learnings', function (Blueprint $table) {
            $table->dropColumn('deadline');
        });
    }
};

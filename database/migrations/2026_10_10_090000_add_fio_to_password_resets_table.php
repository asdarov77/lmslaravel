<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Токены восстановления пароля по ФИО.
 *
 * В этой системе вход идёт по ФИО, а не по email, поэтому стандартный
 * сценарий Laravel (email как идентификатор в password_resets) здесь не
 * работает: в таблице нет колонки fio, и «забыл пароль» пришлось бы
 * делать поверх чужой схемы.
 *
 * Добавляем fio как идентификатор для поиска, email оставляем для
 * адреса доставки: он у пользователей есть, но необязательный. Если
 * email пуст, токен всё равно создаётся — иначе по ответу API можно
 * было бы перебрать, какие ФИО существуют.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_resets', function (Blueprint $table) {
            // Идентификатор, по которому пользователь запрашивает сброс.
            $table->string('fio')->nullable()->after('email');

            // Один активный запрос на ФИО: иначе повторные запросы
            // плодили бы токены, и старые ссылки оставались бы рабочими.
            $table->index('fio');
        });
    }

    public function down(): void
    {
        Schema::table('password_resets', function (Blueprint $table) {
            $table->dropIndex(['fio']);
            $table->dropColumn('fio');
        });
    }
};
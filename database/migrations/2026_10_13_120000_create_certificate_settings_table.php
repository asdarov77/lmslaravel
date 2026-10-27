<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Настройки бланка сертификата.
 *
 * Таблица-синглтон: настройки относятся ко всей организации, а не к
 * пользователю и не к курсу. Ключ-константа в таблице позволяет менять
 * их без правки кода и без новой миграции на каждое поле.
 *
 * Отдельная таблица, а не строки в settings: у настроек есть понятная
 * предметная область и форма, а общие settings — плоский ключ-значение,
 * где брендинг сертификата потерялся бы среди прочих записей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_settings', function (Blueprint $table) {
            $table->id();

            // Единственная строка: 'default'. Идентификатор не в коде —
            // иначе смена «ключа» означала бы новую миграцию.
            $table->string('key')->unique();

            $table->string('organization')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();

            // Логотип лежит на диске публичным: он печатается на листе
            // и не является персональными данными.
            $table->string('logo_path')->nullable();

            $table->string('footer_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_settings');
    }
};
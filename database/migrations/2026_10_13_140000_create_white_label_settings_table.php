<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label настройки бренда.
 *
 * Таблица-синглтон: один набор настроек для всей системы.
 * Хранит CSS-переменные, которые инжектятся в <head> на лету.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'default'

            // Логотип (SVG/PNG, публичный, печатается в шапке и на страницах)
            $table->string('logo_light_path')->nullable();
            $table->string('logo_dark_path')->nullable();

            // Цветовые токены (в формате CSS: #rrggbb или rgba(...))
            $table->string('primary_color')->default('#1976d2');
            $table->string('primary_lighten_color')->default('#42a5f5');
            $table->string('primary_darken_color')->default('#1565c0');
            $table->string('accent_color')->default('#00bcd4');
            $table->string('accent_lighten_color')->default('#4dd0e1');
            $table->string('accent_darken_color')->default('#0097a7');

            // Градиенты для split-screen / hero
            $table->string('gradient_start')->default('#1976d2');
            $table->string('gradient_end')->default('#00bcd4');

            // Dark theme overrides
            $table->string('dark_bg')->default('#0e1116');
            $table->string('dark_surface')->default('#161a20');
            $table->string('dark_primary')->default('#64b5f6');
            $table->string('dark_accent')->default('#4dd0e1');

            // Название организации (для title, footer, PDF)
            $table->string('organization_name')->nullable();
            $table->string('organization_short')->nullable();

            // Favicon
            $table->string('favicon_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_settings');
    }
};
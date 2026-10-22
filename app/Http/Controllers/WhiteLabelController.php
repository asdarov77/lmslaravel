<?php

namespace App\Http\Controllers;

use App\Models\WhiteLabelSetting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * White-label настройки: бренд, цвета, логотипы.
 *
 * GET  /api/white-label  — текущие настройки + CSS-переменные
 * PUT  /api/white-label  — сохранение (в т.ч. файлы логотипов)
 */
class WhiteLabelController extends Controller
{
    private const DISK = 'public';
    private const KEY = 'default';

    /** Папка для логотипов и фавикона. */
    private const LOGO_DIR = 'white-label/logo';
    private const FAVICON_DIR = 'white-label/favicon';

    /**
     * Получить настройки бренда.
     *
     * Возвращает и сырые значения, и готовые CSS-переменные для
     * инжекта в <head>. Фронт может применить их через style-тег
     * или обновить CSS-переменные на лету.
     *
     * ПРАВО ЧТЕНИЯ — ЛЮБОЙ АВТОРИЗОВАННЫЙ, намеренно.
     *
     * Раньше здесь стояло `white_label.manage`, и это была ошибка
     * двух уровней сразу:
     *
     *  1. Брендинг (цвета, логотип, название организации) нужен
     *     КАЖДОМУ, кто вошёл в систему: из этих переменных строится
     *     оформление всего интерфейса. Закрыв их правом управления,
     *     мы запрещали обучаемому видеть интерфейс в его же цветах.
     *  2. Последствие было хуже запрета. Фронт грузит бренд один раз
     *     при старте приложения (app.js -> initWhiteLabel), а
     *     httpClient на любой 403 уводит ВСЁ SPA на страницу
     *     «В доступе отказано». То есть обучаемый, у которого нет
     *     white_label.manage, после ЛЮБОЙ перезагрузки страницы
     *     (F5, вход по прямой ссылке, открытие в новой вкладке)
     *     оказывался на 403 вместо своего кабинета.
     *
     * Запись по-прежнему закрыта правом white_label.manage — менять
     * бренд может только тот, кому это положено.
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $settings = WhiteLabelSetting::current();

        return response()->json([
            'data' => $this->payload($settings),
        ]);
    }

    /**
     * Сохранить настройки бренда.
     *
     * Принимает multipart/form-data:
     * - цветовые поля (primary_color, accent_color, ...)
     * - organization_name, organization_short
     * - logo_light, logo_dark (файлы)
     * - favicon (файл)
     * - remove_logo_light, remove_logo_dark, remove_favicon (boolean)
     */
    public function update(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('white_label.manage'), 403);

        $validated = $request->validate([
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'primary_lighten_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'primary_darken_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_lighten_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_darken_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gradient_start' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gradient_end' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_bg' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_surface' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_primary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'organization_short' => ['nullable', 'string', 'max:50'],
            'logo_light' => ['nullable', 'file', 'image', 'max:512'],
            'logo_dark' => ['nullable', 'file', 'image', 'max:512'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:64'],
            'remove_logo_light' => ['sometimes', 'boolean'],
            'remove_logo_dark' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ], [], [
            'primary_color' => 'основной цвет',
            'accent_color' => 'акцентный цвет',
            'logo_light' => 'логотип (светлая тема)',
            'logo_dark' => 'логотип (тёмная тема)',
            'favicon' => 'фавикон',
        ]);

        $settings = WhiteLabelSetting::firstOrNew(['key' => self::KEY]);

        // Цветовые поля
        $colorFields = [
            'primary_color', 'primary_lighten_color', 'primary_darken_color',
            'accent_color', 'accent_lighten_color', 'accent_darken_color',
            'gradient_start', 'gradient_end',
            'dark_bg', 'dark_surface', 'dark_primary', 'dark_accent',
        ];
        foreach ($colorFields as $field) {
            if (array_key_exists($field, $validated)) {
                $settings->{$field} = $validated[$field];
            }
        }

        // Текстовые поля
        foreach (['organization_name', 'organization_short'] as $field) {
            if (array_key_exists($field, $validated)) {
                $settings->{$field} = $validated[$field];
            }
        }

        // Логотипы и фавикон: замена с удалением старого файла
        foreach (['logo_light', 'logo_dark', 'favicon'] as $fileField) {
            $removeFlag = 'remove_' . Str::of($fileField)->replace('_', '-');
            if ($request->boolean($removeFlag)) {
                $path = $settings->{$fileField . '_path'};
                if ($path) {
                    Storage::disk(self::DISK)->delete($path);
                }
                $settings->{$fileField . '_path'} = null;
            } elseif (!empty($validated[$fileField])) {
                $path = $this->replaceFile($settings->{$fileField . '_path'}, $validated[$fileField], $fileField);
                $settings->{$fileField . '_path'} = $path;
            }
        }

        $settings->save();

        return response()->json(['data' => $this->payload($settings)]);
    }

    /**
     * Заменяет файл, удаляя старый.
     *
     * @return string|null путь к новому файлу или null
     */
    private function replaceFile(?string $oldPath, UploadedFile $file, string $field): ?string
    {
        if ($oldPath) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        $dir = $field === 'favicon' ? self::FAVICON_DIR : self::LOGO_DIR;
        $ext = $file->extension() ?: 'png';

        return $file->storeAs(
            $dir,
            Str::random(24) . '.' . $ext,
            self::DISK
        );
    }

    /**
     * Формирует ответ: сырые поля + CSS-переменные.
     */
    private function payload(?WhiteLabelSetting $settings): array
    {
        if (! $settings) {
            $settings = WhiteLabelSetting::current();
        }

        // Дефолты для полей, которые могут быть null в unsaved модели
        $defaults = [
            'primary_color' => '#1976d2',
            'primary_lighten_color' => '#42a5f5',
            'primary_darken_color' => '#1565c0',
            'accent_color' => '#00bcd4',
            'accent_lighten_color' => '#4dd0e1',
            'accent_darken_color' => '#0097a7',
            'gradient_start' => '#1976d2',
            'gradient_end' => '#00bcd4',
            'dark_bg' => '#0e1116',
            'dark_surface' => '#161a20',
            'dark_primary' => '#64b5f6',
            'dark_accent' => '#4dd0e1',
            'organization_name' => null,
            'organization_short' => null,
        ];

        // URL файлов (null если файл не задан)
        $logoLightUrl = $settings->logo_light_path
            ? Storage::disk(self::DISK)->url($settings->logo_light_path)
            : null;
        $logoDarkUrl = $settings->logo_dark_path
            ? Storage::disk(self::DISK)->url($settings->logo_dark_path)
            : null;
        $faviconUrl = $settings->favicon_path
            ? Storage::disk(self::DISK)->url($settings->favicon_path)
            : null;

        // Берём значение из модели или дефолт
        $get = function ($field) use ($settings, $defaults) {
            return $settings->{$field} ?? $defaults[$field] ?? null;
        };

        return [
            // Сырые значения
            'primary_color' => $get('primary_color'),
            'primary_lighten_color' => $get('primary_lighten_color'),
            'primary_darken_color' => $get('primary_darken_color'),
            'accent_color' => $get('accent_color'),
            'accent_lighten_color' => $get('accent_lighten_color'),
            'accent_darken_color' => $get('accent_darken_color'),
            'gradient_start' => $get('gradient_start'),
            'gradient_end' => $get('gradient_end'),
            'dark_bg' => $get('dark_bg'),
            'dark_surface' => $get('dark_surface'),
            'dark_primary' => $get('dark_primary'),
            'dark_accent' => $get('dark_accent'),
            'organization_name' => $get('organization_name'),
            'organization_short' => $get('organization_short'),
            'logo_light_url' => $logoLightUrl,
            'logo_dark_url' => $logoDarkUrl,
            'favicon_url' => $faviconUrl,

            // Готовые CSS-переменные для инжекта
            'css_variables' => $this->cssVariables($settings),
        ];
    }

    /**
     * CSS-переменные для инжекта в <head>.
     *
     * Имена совпадают с тем, что используют компоненты (--v-theme-primary,
     * --c-accent, --c-brand-gradient-1/2, --v-theme-bg и т.д.).
     */
    private function cssVariables(WhiteLabelSetting $s): array
    {
        return [
            // Vuetify core (primary theme)
            '--v-theme-primary' => $s->primary_color ?? '#1976d2',
            '--v-theme-primary-lighten' => $s->primary_lighten_color ?? '#42a5f5',
            '--v-theme-primary-darken' => $s->primary_darken_color ?? '#1565c0',

            // Custom accent (используется в компонентах через --c-accent)
            '--c-accent' => $s->accent_color ?? '#00bcd4',
            '--c-accent-lighten' => $s->accent_lighten_color ?? '#4dd0e1',
            '--c-accent-darken' => $s->accent_darken_color ?? '#0097a7',

            // Brand gradients
            '--c-brand-gradient-1' => $s->gradient_start ?? '#1976d2',
            '--c-brand-gradient-2' => $s->gradient_end ?? '#00bcd4',

            // Dark theme overrides (применяются frontend'ом при isDarkMode)
            '--v-theme-bg' => $s->dark_bg ?? '#0e1116',
            '--v-theme-surface' => $s->dark_surface ?? '#161a20',
            '--v-theme-primary' => $s->dark_primary ?? '#64b5f6',
            '--c-accent' => $s->dark_accent ?? '#4dd0e1',
        ];
    }
}

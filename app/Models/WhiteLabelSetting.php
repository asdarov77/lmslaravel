<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * White-label настройки бренда.
 *
 * Таблица-синглтон: настройки относятся ко всей системе.
 */
class WhiteLabelSetting extends Model
{
    protected $fillable = [
        'key',
        'logo_light_path',
        'logo_dark_path',
        'primary_color',
        'primary_lighten_color',
        'primary_darken_color',
        'accent_color',
        'accent_lighten_color',
        'accent_darken_color',
        'gradient_start',
        'gradient_end',
        'dark_bg',
        'dark_surface',
        'dark_primary',
        'dark_accent',
        'organization_name',
        'organization_short',
        'favicon_path',
    ];

    protected $casts = [
        'primary_color' => 'string',
        'primary_lighten_color' => 'string',
        'primary_darken_color' => 'string',
        'accent_color' => 'string',
        'accent_lighten_color' => 'string',
        'accent_darken_color' => 'string',
        'gradient_start' => 'string',
        'gradient_end' => 'string',
        'dark_bg' => 'string',
        'dark_surface' => 'string',
        'dark_primary' => 'string',
        'dark_accent' => 'string',
    ];

    public const DEFAULT_KEY = 'default';

    public static function current(): self
    {
        return static::firstOrNew(['key' => static::DEFAULT_KEY]);
    }
}
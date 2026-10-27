<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Настройки бланка сертификата.
 *
 * Таблица-синглтон: организация, подписант и логотип относятся ко
 * всей системе, а не к отдельному сертификату. Поэтому запись одна,
 * опознаётся по ключу и создаётся по требованию, а не сидером.
 *
 * @property int $id
 * @property string $key
 * @property string|null $organization
 * @property string|null $signatory_name
 * @property string|null $signatory_title
 * @property string|null $logo_path
 * @property string|null $footer_note
 */
class CertificateSetting extends Model
{
    protected $fillable = [
        'key',
        'organization',
        'signatory_name',
        'signatory_title',
        'logo_path',
        'footer_note',
    ];

    /** Запись настроек по умолчанию; создаётся при первом обращении. */
    public const DEFAULT_KEY = 'default';

    /** Настройки с пустыми значениями — чтобы фронт всегда получил ключи. */
    public static function current(): self
    {
        return static::firstOrNew(['key' => static::DEFAULT_KEY]);
    }
}
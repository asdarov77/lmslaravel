<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Режим раздачи приватного контента.
 *
 * Один источник истины для всех, кто решает «отдавать файл самому
 * Laravel или отдать nginx через X-Accel-Redirect»: настройка в
 * админке, .env, сам контроллер и тесты. Если режим определять в двух
 * местах, рано или поздно одно из них разойдётся с другим, и материал
 * либо перестанет отдаваться, либо начнёт утекать мимо проверки подписи.
 *
 * Приоритет: settings.content_delivery (галка в админке) → config
 * (PRIVATE_CONTENT_DELIVERY) → 'php'.
 *
 * Значение читается БЕЗ кэша между запросами намеренно: переключатель
 * должен срабатывать сразу, а чтение одной строки из settings по
 * индексу name для этого достаточно дёшево. Внутри запроса значение
 * запоминается, чтобы 200 вложенных ресурсов не сделали 200 запросов.
 */
final class ContentDelivery
{
    /** Отдаёт файл сам Laravel, потоком. */
    public const PHP = 'php';

    /** Laravel проверяет подпись, файл отдаёт nginx. */
    public const NGINX = 'nginx';

    /** @var array<int, string> */
    public const MODES = [self::PHP, self::NGINX];

    /**
     * Значение и время его чтения.
     *
     * TTL обязателен: процесс воркера очереди живёт часами, и при
     * запоминании «навсегда» переключатель в админке не действовал бы до
     * перезапуска воркера — молча отдавая материал по-старому.
     */
    private static ?string $resolved = null;

    private static int $resolvedAt = 0;

    private const TTL = 5;

    /**
     * Текущий режим: 'php' | 'nginx'.
     *
     * Недопустимое значение НЕ превращается в 'php' молча: сначала
     * пишем в лог. Иначе опечатка в настройке выглядит как «у меня
     * nginx не работает», а на самом деле приложение тихо ушло в php
     * и под нагрузкой опять упёрлось в workers.
     */
    public static function mode(): string
    {
        if (self::$resolved !== null && (time() - self::$resolvedAt) < self::TTL) {
            return self::$resolved;
        }

        self::$resolvedAt = time();

        $fromSettings = self::readSetting();

        if ($fromSettings !== null) {
            return self::$resolved = $fromSettings;
        }

        return self::$resolved = self::normalize(
            (string) config('private_content.delivery', self::PHP)
        ) ?? self::PHP;
    }

    /**
     * Включён ли режим nginx.
     *
     * Одной галки в настройках МАЛО.
     *
     * Приложение часто запускают без nginx: `php artisan serve` на
     * localhost, тесты, artisan-команды. Там заголовок
     * X-Accel-Redirect никто не обрабатывает, и Symfony отдаёт
     * наружу пустое тело со статусом 200 — материал выглядит как пустая
     * страница, без единой ошибки в логе. Молчаливую порчу лучше
     * предотвратить, чем задокументировать.
     *
     * Поэтому X-Accel включается только когда запрос помечен самим
     * nginx. Метка — не просто флаг: значение выводится из APP_KEY и
     * подставляется в конфиг nginx генератором `content:nginx-config`.
     * Клиент такой заголовок подделать не может: прямой запрос к
     * artisan serve идёт мимо nginx, а подделанное значение не
     * совпадёт с вычисленным.
     */
    public static function isAccel(?\Illuminate\Http\Request $request = null): bool
    {
        if (self::mode() !== self::NGINX) {
            return false;
        }

        if ($request === null) {
            return false;
        }

        $expected = self::accelMarker();
        $received = (string) $request->header(self::ACCEL_HEADER, '');

        if ($received === '' || ! hash_equals($expected, $received)) {
            Log::warning('Режим nginx включён, но запрос не прошёл через nginx — отдаём через PHP', [
                'header' => self::ACCEL_HEADER,
            ]);

            return false;
        }

        return true;
    }

    /** Заголовок-метка «этот запрос от nginx». */
    public const ACCEL_HEADER = 'X-Lms-Accel';

    /**
     * Значение метки. Секрет выводится из APP_KEY: отдельная строка в
     * .env создала бы второе место, где можно разъехаться.
     */
    public static function accelMarker(): string
    {
        $configured = config('private_content.accel_marker');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return substr(hash('sha256', (string) config('app.key').'|lms-accel-marker'), 0, 32);
    }

    /**
     * Допустимое значение режима или null, если значение недопустимо.
     *
     * @param  mixed  $value
     */
    public static function normalize($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return in_array($value, self::MODES, true) ? $value : null;
    }

    /** Имя строки в settings. */
    public static function settingName(): string
    {
        return (string) config('private_content.setting_name', 'content_delivery');
    }

    /**
     * Записать режим в settings.
     *
     * updateOrCreate, а не update: строки может не быть вовсе, и
     * update() молча ничего не сделал бы — галка «включена», а материал
     * по-прежнему отдаёт PHP.
     */
    public static function persist(string $mode): bool
    {
        $normalized = self::normalize($mode);

        if ($normalized === null) {
            return false;
        }

        Setting::updateOrCreate(
            ['name' => self::settingName()],
            ['value' => $normalized, 'type' => self::settingName()]
        );

        // Сбросить запомненное значение: текущий процесс уже мог
        // прочитать старый режим ( Artisan-команда, долгий скрипт).
        self::$resolved = null;

        return true;
    }

    /**
     * Сбросить запомненное значение. Для тестов.
     */
    public static function flush(): void
    {
        self::$resolved = null;
        self::$resolvedAt = 0;
    }

    /**
     * Значение из settings или null, если строки нет / значение плохое.
     */
    private static function readSetting(): ?string
    {
        $name = self::settingName();

        try {
            $raw = Setting::where('name', $name)->value('value');
        } catch (\Throwable $e) {
            // Таблицы settings может не быть (свежая установка, миграции
            // не применены). Отсутствие настройки не должно ломать
            // выдачу материала — отдаём как есть, из config.
            return null;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = self::normalize($raw);

        if ($normalized === null) {
            Log::warning('Недопустимый режим раздачи контента, берём значение из config', [
                'setting' => $name,
                'value' => $raw,
                'allowed' => self::MODES,
            ]);

            return null;
        }

        return $normalized;
    }
}

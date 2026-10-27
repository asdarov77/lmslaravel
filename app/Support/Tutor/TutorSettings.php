<?php

namespace App\Support\Tutor;

use App\Models\Setting;

/**
 * Переключатель тренажёра.
 *
 * Отдельная настройка, а не только TUTOR_ENABLED в .env, по двум
 * причинам:
 *
 *  - .env требует перезапуска воркеров очереди и веб-процессов. Включить
 *    и выключить тренажёр из интерфейса, не трогая конфигурацию
 *    развёртывания, — ожидаемое поведение для готового продукта.
 *  - режим по умолчанию обязан быть безопасным: тренажёр выключен,
 *    пока методист его не включил. Значение в .env остаётся запасным
 *    вариантом для развёртываний, где настройки в базе недоступны.
 *
 * Приоритет: settings.tutor_enabled → config('tutor.enabled') → false.
 */
final class TutorSettings
{
    public const SETTING = 'tutor_enabled';

    /**
     * Запомненное значение и время его чтения.
     *
     * Запоминание нужно, чтобы сотня вложенных ресурсов не сделала сотню
     * запросов в settings. Но запоминать НАВСЕГДА нельзя: процесс
     * воркера очереди живёт часами, и первое прочитанное значение
     * закреплялось бы навсегда — переключатель в админке перестал бы
     * действовать до перезапуска воркера. Поэтому значение живёт
     * несколько секунд: достаточно для одного запроса или одной задачи.
     */
    private static ?bool $resolved = null;

    private static int $resolvedAt = 0;

    /** Сколько секунд живёт запомненное значение. */
    private const TTL = 5;

    /** Включён ли тренажёр. */
    public static function enabled(): bool
    {
        if (self::$resolved !== null && (time() - self::$resolvedAt) < self::TTL) {
            return self::$resolved;
        }

        $fromSettings = self::readSetting();

        self::$resolvedAt = time();

        if ($fromSettings !== null) {
            return self::$resolved = $fromSettings;
        }

        return self::$resolved = (bool) config('tutor.enabled', false);
    }

    /** Записать состояние. */
    public static function persist(bool $enabled): void
    {
        Setting::updateOrCreate(
            ['name' => self::SETTING],
            [
                'value' => $enabled ? '1' : '0',
                'type' => 'tutor',
            ]
        );

        self::$resolved = null;
    }

    /** Откуда взялось значение: settings или config. */
    public static function source(): string
    {
        return self::readSetting() === null ? 'config' : 'settings';
    }

    /** Запомненное значение или null, если его нет. Для тестов. */
    public static function peek(): ?bool
    {
        return self::$resolved;
    }

    /** Сбросить запомненное значение. Для тестов и после записи. */
    public static function flush(): void
    {
        self::$resolved = null;
        self::$resolvedAt = 0;
    }

    /**
     * Значение из settings или null, если строки нет.
     *
     * Только «1» и «true» включают: строка 'kafka' или пустая не должны
     * молча включать генерацию вопросов сторонней моделью.
     */
    private static function readSetting(): ?bool
    {
        try {
            $raw = Setting::where('name', self::SETTING)->value('value');
        } catch (\Throwable $e) {
            // Таблицы settings может не быть — это не повод ломать
            // выдачу, просто используем config.
            return null;
        }

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = strtolower(trim((string) $raw));

        if (in_array($value, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return null;
    }
}
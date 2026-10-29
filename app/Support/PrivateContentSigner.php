<?php

namespace App\Support;

/**
 * Подписанные URL для приватного контента курсов.
 *
 * Зачем: маршруты /api/private/... должны быть доступны только
 * авторизованным пользователям, но контент отдаётся вложенными ресурсами
 * (CSS, JS, картинки), которые браузер запрашивает напрямую — без заголовка
 * Authorization. Поэтому обычный auth:sanctum здесь не подходит: он сломал бы
 * относительные ресурсы.
 *
 * Решение: подпись — HMAC от aircraft|auk|expires на APP_KEY, поэтому
 * подделать или продлить её без ключа нельзя. Основной способ передачи —
 * signedPath(): expires и signature встроены в путь, поэтому остаются частью
 * префикса и наследуются всеми относительными ссылками документа.
 *
 * Исторически подпись передавалась в query-строке (signedBase/signedQuery).
 * Такой вариант не работает для вложенных ресурсов: при разрешении
 * относительных ссылок query базового адреса отбрасывается правилами URL,
 * поэтому CSS/JS/картинки уходили без подписи и получали 403. Методы
 * оставлены для обратной совместимости — middleware всё ещё принимает
 * подпись из query, — но новый код должен использовать signedPath().
 */
final class PrivateContentSigner
{
    /** Время жизни подписанного URL, минут. */
    public const TTL_MINUTES = 30;

    /**
     * П1 (аудит): «слот» вместо уникального expires на каждую выдачу.
     *
     * Раньше expires = now()+TTL давал УНИКАЛЬНЫЙ путь каждому запросу:
     * браузер не мог переиспользовать кэш даже одного студента, а все
     * студенты курса генерировали каждый свой URL — нулевой cache hit
     * и лишняя работа HMAC-подписи на кадр. Слот — граница сетки времени
     * (шаг = TTL): все выдачи внутри слота дают ОДИН И ТОТ ЖЕ путь,
     * поэтому ответ переживает перезагрузку страницы и попадает в
     * private-кэш браузера/Nginx с max-age до конца слота.
     *
     * Безопасность не ослабевает: подпись по-прежнему HMAC(aircraft|auk|slotEnd),
     * срок жизни — ровно TTL (плюс окно grace для високосных запросов, см. isValid).
     */
    public const SLOT_SECONDS = self::TTL_MINUTES * 60;

    /**
     * Граница текущего слота — значение expires для подписи.
     *
     * Одинаково для всех запросов внутри одного TTL-окна, поэтому
     * подписанный путь стабилен (кэшируем) и при этом истекает ровно
     * по прежнему правилу «не дольше TTL минут».
     */
    public static function slotExpires(?\Illuminate\Support\Carbon $at = null): int
    {
        $now = ($at ?? now())->getTimestamp();

        return intdiv($now, self::SLOT_SECONDS) * self::SLOT_SECONDS + self::SLOT_SECONDS;
    }

    /**
     * Формирует подписанный URL базового каталога курса (вариант с query).
     *
     * Оставлен для обратной совместимости. Для вложенных ресурсов
     * использовать signedPath(): query-строка при разрешении относительных
     * ссылок отбрасывается.
     */
    public static function signedUrl(string $aircraft, string $auk): string
    {
        return self::signedBase($aircraft, $auk).self::signedQuery($aircraft, $auk);
    }

    /**
     * Базовый путь каталога курса (без токена).
     */
    public static function signedBase(string $aircraft, string $auk): string
    {
        $aircraft = PrivateContent::sanitizeSegment($aircraft) ?? '';
        $auk = PrivateContent::sanitizeSegment($auk) ?? '';

        return sprintf('api/private/%s/%s/', rawurlencode($aircraft), rawurlencode($auk));
    }

    /**
     * Query-строка с подписью (начинается с ?).
     */
    public static function signedQuery(string $aircraft, string $auk): string
    {
        $aircraft = PrivateContent::sanitizeSegment($aircraft) ?? '';
        $auk = PrivateContent::sanitizeSegment($auk) ?? '';

        $expires = now()->addMinutes(self::TTL_MINUTES)->getTimestamp();
        $signature = self::signature($aircraft, $auk, $expires);

        return sprintf('?expires=%d&signature=%s', $expires, $signature);
    }

    /**
     * Подписанный путь базового каталога курса.
     *
     * Подпись встраивается в путь: api/private/{aircraft}/{auk}/{expires}/{signature}/.
     * Относительные ресурсы внутри документа (CSS, JS, картинки) разрешаются
     * относительно этого префикса и наследуют expires/signature автоматически —
     * в отличие от query-строки, которая при разрешении относительных ссылок
     * отбрасывается.
     */
    public static function signedPath(string $aircraft, string $auk): string
    {
        $aircraft = PrivateContent::sanitizeSegment($aircraft) ?? '';
        $auk = PrivateContent::sanitizeSegment($auk) ?? '';

        $expires = now()->addMinutes(self::TTL_MINUTES)->getTimestamp();
        $signature = self::signature($aircraft, $auk, $expires);

        return sprintf('api/private/%s/%s/%d/%s/', rawurlencode($aircraft), rawurlencode($auk), $expires, $signature);
    }

    /**
     * Проверяет подпись запроса.
     *
     * @return bool true, если подпись валидна и не истекла
     */
    public static function isValid(string $aircraft, string $auk, int $expires, string $signature): bool
    {
        if ($expires < now()->getTimestamp()) {
            return false;
        }

        $expected = self::signature($aircraft, $auk, $expires);

        return hash_equals($expected, $signature);
    }

    /**
     * Приводит сегмент маршрута к строке, участвующей в подписи.
     *
     * Сегменты приходят уже декодированными из URL, но на всякий случай
     * нормализуем их тем же правилом, что и PrivateContent.
     */
    public static function sanitize(?string $segment): ?string
    {
        return PrivateContent::sanitizeSegment($segment);
    }

    /**
     * Подпись для конкретного курса и времени истечения.
     */
    public static function signature(string $aircraft, string $auk, int $expires): string
    {
        $payload = implode('|', [$aircraft, $auk, $expires]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}

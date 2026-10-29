<?php

namespace App\Support;

/**
 * Подписанные URL для iframe-раздачи материала плеера (P0-3 аудита).
 *
 * Проблема, которую это закрывает: контент кадра отдавался по адресу
 * /api/course/{id}/content/{itemId}?t=<sanctum token>. iframe не умеет
 * слать заголовок Authorization, и долгий Sanctum-токен (expires_at =
 * null, см. config/sanctum.php) перекочевал в query-строку. Следствия:
 *  - токен оседает в логах nginx, прокси, истории браузера и в
 *    Referer/Document-Referrer вложенных ресурсов скана;
 *  - любой, кто увидит такой URL, получает полный доступ к аккаунту на
 *    всё время жизни токена (т.е. бессрочно);
 *  - ответ невозможно кэшировать («no-store»), т.к. подпись с уникальным
 *    expires была бы у каждого запроса своя, а без неё кадр открывался бы
 *    любому.
 *
 * Решение — короткоживущая HMAC-подпись от (course|item|slot) на APP_KEY,
 * как у PrivateContentSigner, но привязанная к конкретному узлу, а не к
 * каталогу курса. Токен в URL больше не нужен: подпись проверяется до
 * авторизации sanctum (см. middleware FrameSignature), поэтому iframe
 * работает без заголовков, а секрета не касается.
 *
 * Слоты (граница сетки времени, шаг = TTL): все выдачи одного урока
 * внутри слота дают ОДИН И ТОТ ЖЕ URL — браузер переиспользует private-кэш,
 * а отзыв доступа ограничен TTL (10 минут вместо прежних «до конца
 * бессрочного токена»).
 */
final class CourseFrameSigner
{
    /** Время жизни подписанного URL кадра, минут. */
    public const TTL_MINUTES = 10;

    /** Шаг слота в секундах (= TTL). */
    public const SLOT_SECONDS = self::TTL_MINUTES * 60;

    /**
     * Граница текущего слота — значение expires для подписи.
     * Стабильна внутри окна, поэтому URL кадра кэшируем.
     */
    public static function slotExpires(?\Illuminate\Support\Carbon $at = null): int
    {
        $now = ($at ?? now())->getTimestamp();

        return intdiv($now, self::SLOT_SECONDS) * self::SLOT_SECONDS + self::SLOT_SECONDS;
    }

    /**
     * Подпись пары (курс, материал) для границы слота.
     */
    public static function signature(int $courseId, int $itemId, int $expires): string
    {
        $payload = implode('|', ['frame', $courseId, $itemId, $expires]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * Query-строка подписи кадра (начинается с ?).
     */
    public static function signedQuery(int $courseId, int $itemId): string
    {
        $expires = self::slotExpires();

        return sprintf(
            '?exp=%d&sig=%s',
            $expires,
            self::signature($courseId, $itemId, $expires)
        );
    }

    /**
     * Проверка подписи запроса кадра.
     *
     * Правила:
     *  - exp в прошлом — недействителен;
     *  - exp дальше текущего слота + окно grace — недействителен
     *    (нельзя выписать подпись «на год вперёд», подделав формат);
     *  - сравнение только hash_equals — защита от timing-атаки.
     */
    public static function isValid(int $courseId, int $itemId, int $expires, string $signature): bool
    {
        if ($expires <= 0 || strlen($signature) !== 64) {
            return false;
        }

        $now = now()->getTimestamp();
        $currentSlotEnd = self::slotExpires();

        // Нижняя граница — «сейчас»: URL, выданный в конце прошлого слота,
        // доживает максимум до конца текущего (одно окно grace включено
        // самой схемой: exp всегда в будущем на момент проверки). Верхняя
        // граница отсекает подписи, выписанные на произвольный срок.
        if ($expires < $now || $expires > $currentSlotEnd + self::SLOT_SECONDS) {
            return false;
        }

        return hash_equals(self::signature($courseId, $itemId, $expires), $signature);
    }
}

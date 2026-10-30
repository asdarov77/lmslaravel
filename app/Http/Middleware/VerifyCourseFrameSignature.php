<?php

namespace App\Http\Middleware;

use App\Support\CourseFrameSigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверяет HMAC-подпись кадра материала (P0-3 аудита).
 *
 * Заменяет авторизацию по Sanctum-токену в query (?t=...): долгий токен
 * оседал в логах nginx/прокси, истории браузера и Referer вложенных
 * ресурсов — любой, увидевший такой URL, получал полный доступ к
 * аккаунту. Подпись привязана к паре (course, item) и границе слота
 * (TTL 10 минут), поэтому её утечка открывает ровно один урок на
 * короткое окно, а не аккаунт целиком.
 *
 * Middleware зарегистрирован как alias 'frame.signature' (app/Http/Kernel.php)
 * и подключается к маршрутам /course/{id}/content|raw в routes/api.php.
 */
class VerifyCourseFrameSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $courseId = (int) $request->route('courseId');
        // raw-маршрут не имеет itemId: подпись вычисляется с нулевым
        // идентификатором узла — тот же контракт, что у фронтенда
        // (frameContentUrl(courseId, 0)).
        $itemId = (int) $request->route('itemId', 0);

        $expires = (int) $request->query('exp', 0);
        $signature = (string) $request->query('sig', '');

        if (! CourseFrameSigner::isValid($courseId, $itemId, $expires, $signature)) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Недействительная или истёкшая подпись кадра',
                'meta'    => null,
            ], 403);
        }

        return $next($request);
    }
}

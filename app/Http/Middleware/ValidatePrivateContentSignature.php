<?php

namespace App\Http\Middleware;

use App\Support\PrivateContentSigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверяет подпись запроса к приватному контенту курсов.
 *
 * Подпись передаётся в пути: api/private/{aircraft}/{auk}/{expires}/{signature}/{file}.
 * Так она наследуется всеми относительными ресурсами внутри документа
 * (CSS, JS, картинками) — в отличие от query-строки, которая при разрешении
 * относительных ссылок отбрасывается.
 *
 * Раньше эти маршруты были публичными: auth:sanctum был закомментирован,
 * и контент курсов мог прочитать любой, кто знает URL.
 */
class ValidatePrivateContentSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $aircraft = (string) $request->route('aircraft', '');
        $auk = (string) $request->route('auk', '');

        // Сегменты маршрута могут содержать URL-кодирование — приводим к
        // тому виду, в котором они участвуют в подписи.
        $aircraft = PrivateContentSigner::sanitize($aircraft) ?? '';
        $auk = PrivateContentSigner::sanitize($auk) ?? '';

        if ($aircraft === '' || $auk === '') {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Некорректный путь к материалу курса',
                'meta'    => null,
            ], 403);
        }

        // Подпись может быть в пути (основной вариант) или в query
        // (для обратной совместимости со старыми ссылками).
        //
        // Дефект №4 (ИСПРАВЛЕН): раньше query имела приоритет, и на запросе
        // с path-форматом {expires}/{signature}/... по-прежнему принималась
        // любая валидная query-подпись. Это допускало replay: старую
        // query-подпись, слитую из логов/истории браузера, можно было
        // «надеть» на новый path-URL. Правило: если в path есть signed-префикс
        // (первый сегмент — чистое число expires), query игнорируется;
        // query принимается только когда path не подписан.
        $path = (string) $request->route('path', '');
        $parts = explode('/', $path);
        $pathSigned = count($parts) >= 2 && preg_match('/^\d+$/', $parts[0]) === 1;

        if ($pathSigned) {
            $expires = (int) $parts[0];
            $signature = $parts[1];
        } else {
            $expires = (int) $request->query('expires', 0);
            $signature = (string) $request->query('signature', '');
        }

        if (! PrivateContentSigner::isValid($aircraft, $auk, $expires, $signature)) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Доступ к материалу курса запрещён: недействительная или истёкшая подпись',
                'meta'    => null,
            ], 403);
        }

        return $next($request);
    }
}

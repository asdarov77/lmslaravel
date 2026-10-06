<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Приводит любой JSON-ответ к единому конверту {success, data, error, meta}.
 *
 * Из этого следуют вещи, которые удивляют при чтении кода и тестов:
 *
 *  - Потоковые и файловые ответы (отдача материалов курса) не
 *    оборачиваются: конверт положил бы внутрь себя поток.
 *  - response()->json(null, 200) на выходе даёт data: [], а не null.
 *    Фронт и тесты обязаны читать это как «пусто», а не как «ошибка».
 *  - «Голая» модель или массив, возвращённые контроллером, тоже
 *    оборачиваются — поэтому в контроллере можно не заворачивать
 *    вручную, и это не ошибка.
 */
class ApiResponseEnvelope
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $original = $response->getData(true);
            $status = $response->getStatusCode();

            $enveloped = [
                'success' => $status >= 200 && $status < 300,
                // array_key_exists, а не ??: при data => null оператор
                // ?? подставлял $original, и ответ с пустым payload
                // превращался в конверт внутри конверта — клиент
                // получал {success, data:{success,data,...}} вместо
                // {success, data:null}. Затрагивало любой эндпоинт,
                // который честно возвращает «ничего» (пустой список
                // не существует, вопросы кончились, ресурс удалён).
                'data' => array_key_exists('data', $original) ? $original['data'] : $original,
                'error' => $status >= 400 ? [
                    'code' => $original['code'] ?? (string)$status,
                    'message' => $original['message'] ?? ($original['error'] ?? 'Error'),
                    'details' => $original['errors'] ?? null,
                ] : null,
                'meta' => $original['meta'] ?? null,
            ];

            return response()->json($enveloped, $status, $response->headers->all(), JSON_UNESCAPED_UNICODE);
        }

        return $response;
    }
}



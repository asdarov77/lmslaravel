<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Проверка прав доступа к маршруту (RBAC).
 *
 * Использование:
 *   ->middleware('permission:users.view')             — одно право
 *   ->middleware('permission:users.view,groups.view') — любое из перечисленных (OR)
 *   ->middleware('permission.all:a,b')                — все права сразу (AND)
 *
 * ВАЖНО (исправление уязвимости): раньше здесь был хардкод
 * User::find(1), из-за чего ЛЮБОЙ аутентифицированный запрос проходил
 * проверку под первым пользователем БД (администратором). Теперь
 * проверяется фактический Auth::user(), а при отказе возвращается
 * корректный 403 (раньше — 404, что ломало обработку ошибок на фронте).
 */
class CheckUserPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /*
         * Именно $request->user(), а НЕ Auth::user().
         *
         * Auth::user() обращается к guard'у по умолчанию, тогда как
         * маршруты авторизуются через auth:sanctum. Значение, оставшееся
         * в guard от предыдущего запроса, «протекало» в проверку прав: два
         * обращения подряд разными пользователями давали 403 инструктору
         * с правом exams.manage, потому что проверялся ПРЕДЫДУЩИЙ
         * пользователь. Для проверки прав это потенциально уязвимость, а
         * не только проблема тестов.
         *
         * $request->user() берёт того, кого поставил auth:sanctum именно
         * для этого запроса, и устареть не может.
         */
        $user = $request->user() ?? Auth::user();

        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Необходимая авторизация');
        }

        // Супер-администратор имеет все права (как Moodle site admin /
        // Canvas root account admin).
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Режим AND включается отдельным алиасом middleware (см. Kernel).
        $mode = $request->route()?->getAction('permission_mode') ?? 'or';

        $granted = $mode === 'and'
            ? collect($permissions)->every(fn ($p) => $user->hasPermission($p))
            : collect($permissions)->some(fn ($p) => $user->hasPermission($p));

        if (!$granted) {
            abort(
                Response::HTTP_FORBIDDEN,
                'Недостаточно прав для выполнения запроса'
            );
        }

        return $next($request);
    }
}

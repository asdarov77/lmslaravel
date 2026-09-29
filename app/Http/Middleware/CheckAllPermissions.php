<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

/**
 * Алиас permission.all — требует НАЛИЧИЯ ВСЕХ перечисленных прав (AND).
 * Реализация наследует логику CheckUserPermission, меняя только режим.
 */
class CheckAllPermissions extends CheckUserPermission
{
    public function handle(Request $request, \Closure $next, string ...$permissions)
    {
        // Передаём режим через действие маршрута до вызова родителя.
        $route = $request->route();
        if ($route) {
            $action = $route->getAction();
            $action['permission_mode'] = 'and';
            $route->setAction($action);
        }

        return parent::handle($request, $next, ...$permissions);
    }
}

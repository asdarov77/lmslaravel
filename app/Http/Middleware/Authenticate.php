<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // Для API без токена возвращаем 401 вместо редиректа на несуществующий маршрут 'login'
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return route('login');
        }
        return null;
    }
}

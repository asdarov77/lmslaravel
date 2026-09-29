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
        // Named-маршрута login в проекте нет: аутентификация выполняется на
        // фронтенде и через API. Возвращаем null, чтобы Laravel отдал 401 JSON,
        // а не падал с "Route [login] not defined".
        // return("Not auth!");
        // return route('login');
        // return redirect()->route('login');
        return null;
    }
}

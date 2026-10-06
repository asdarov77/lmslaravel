<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

/**
 * Исключает api/* из проверки CSRF.
 *
 * В Kernel строка с этим middleware закомментирована, то есть проверка
 * отключена целиком. Для API (Bearer-токен) это правильно; для веб-оболочки
 * защита полагается на то, что мутации идут через /api.
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
        'api/*'
    ];
}

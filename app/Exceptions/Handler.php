<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

/**
 * Обработка исключений.
 *
 * reportable() глушит сообщения, которые приходят от локального нейродвижка
 * тренажёра: они описывают состояние окружения (Ollama не запущен), а не
 * ошибку приложения, и не должны тонуть в общем потоке.
 *
 * unauthenticated() отдаёт 401 JSON: страницы логина у API нет.
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * В проекте нет named-маршрута login (аутентификация живёт на фронтенде
     * и в API), поэтому стандартный редирект падал с "Route [login] not defined".
     * Всегда отдаём 401 JSON.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], 401);
    }
}

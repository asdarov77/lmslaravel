<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),


    //'prefix' => 'api/sanctum/csfr-cookie',   //https://www.mql5.com/ru/articles/10370
    'prefix' => 'api/sanctum/',
    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    /*
     * Sanctum по умолчанию сначала спрашивает сессию и только потом
     * токен (см. Sanctum\Guard::__invoke: сначала цикл по guard'ам,
     * затем getTokenFromRequest). Из-за этого личность API-запроса
     * определяла СЕССИЯ, а не Bearer-токен.
     *
     * Здесь это было не теоретически: домен приложения входит в
     * stateful (SANCTUM_STATEFUL_DOMAINS по умолчанию включает
     * 127.0.0.1:8000), EnsureFrontendRequestsAreStateful запускает
     * сессию на каждом API-запросе, и сессионный пользователь получал
     * приоритет над токеном. Практическое следствие: пользователь
     * проверялся не тот, чей токен прислали.
     *
     * Приложение работает на токенах (POST /api/login -> Bearer), и
     * Auth::login() в коде не вызывается НИГДЕ, поэтому сессионной
     * аутентификации тут просто нет — пустой список guards оставляет
     * единственный источник личности: токен.
     */
    'guard' => [],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do
    | not expire. This won't tweak the lifetime of first-party sessions.
    |
    */

    'expiration' => null,// бесконечный срок жизни токена
    //'expiration' => 1,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

];

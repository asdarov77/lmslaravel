<?php

namespace App\Http;

//use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Стек middleware.
 *
 * Неочевидные решения:
 *
 *  - StartSession убран из глобального стека. Иначе на каждый запрос
 *    создавался файл сессии, а Auth::login() в этом проекте не
 *    вызывается — сессии копились впустую.
 *  - В группе api нет EnsureFrontendRequestsAreStateful: иначе сессия
 *    получила бы приоритет над Bearer-токеном, и проверки прав видели бы
 *    не того пользователя.
 *  - throttle:api закомментирован. Ограничения частоты запросов на API
 *    фактически нет, несмотря на configureRateLimiting() в
 *    RouteServiceProvider. Это осознанное «пока не трогаем», а не
 *    забытый middleware.
 *  - VerifyCsrfToken в стеке отсутствует: CSRF для API не нужен, но
 *    и для веб-оболочки проверка выключена — см. сам класс.
 */
class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        /*
         * StartSession убран ИЗ ГЛОБАЛЬНОГО стека.
         *
         * Здесь он был, поэтому сессия заводилась на КАЖДЫЙ запрос,
         * включая API — в storage/framework/sessions накопились тысячи
         * файлов, хотя Auth::login() в приложении не вызывается нигде и
         * аутентификация держится на Bearer-токенах. Для web-запросов
         * сессия всё равно доступна: StartSession стоит в группе 'web'
         * (ниже), где ему и место.
         */
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
//            \App\Http\Middleware\VerifyCsrfToken::class,  // отключаем проверку токена
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        /*
         * API работает на Bearer-токенах, поэтому EnsureFrontendRequestsAreStateful
         * здесь не нужен и вреден:
         *  - он запускает сессию на КАЖДОМ API-запросе (в
         *    storage/framework/sessions лежало 3387 файлов, тогда как
         *    Auth::login() в приложении не вызывается нигде);
         *  - он добавляет cookie-аутентификацию как второй источник
         *    личности рядом с токеном, и сессия получает приоритет
         *    (см. Sanctum\Guard::__invoke и config/sanctum.php).
         *
         * CSRF-угрозы здесь тоже не было: SPA не шлёт X-XSRF-TOKEN,
         * а middleware VerifyCsrfToken в группу api не входит.
         */
        'api' => [
//            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ApiResponseEnvelope::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'role'  =>  \App\Http\Middleware\RoleMiddleware::class,
        'permission'  =>  \App\Http\Middleware\CheckUserPermission::class,
        'permission.all'  =>  \App\Http\Middleware\CheckAllPermissions::class,
         'custom' =>  \App\Http\Middleware\CustomAuthenticateSessionMiddleware::class,
        'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        // Подпись приватного контента курсов (HMAC в query-строке).
        'private.content.signature' => \App\Http\Middleware\ValidatePrivateContentSignature::class,
    ];
}

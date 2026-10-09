<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SinglePageController;
use App\Http\Controllers\PrivateController;
use App\Http\Controllers\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('/', [SinglePageController::class, 'index']);
Route::get('/', [SinglePageController::class, 'index']);

//Route::get('/search', [SearchController::class, 'index'])->name('search.index');
//Route::get('/{html}', [PrivateController::class, 'htmles0']);

// Route::get('/', function () {   
//    return view('app');
// });

// Route::post( 'secret', function(Request $request){
//     $temp =$request->url;
//     //$temp = $request->URL::signedRoute('secret');
//     // if (! $request -> hasValidSignature()){
//     //     abort(401);
//     // }
//     //return "secret message";
//     return $temp;
    
//     //return URL::signedRoute('secret' );
// })->name('secret');

Route::post( 'secret', function(){
        
        $temp=URL::signedRoute('secret');
        // if (! $request -> hasValidSignature()){
        //     abort(401);
        // }
        //return "secret message";
        return $temp;
        
        //return URL::signedRoute('secret' );
    })->name('secret');


    Route::get('/greeting', function () {
    return view('greeting', ['name' => 'James']);
});


// Fallback для SPA: все не-API маршруты отдают index.html,
// чтобы роутер на фронтенде мог обработать их сам.
// Без этого прямые переходы и обновление страниц на вложенных
// маршрутах (/course/16, /courses/list и т.п.) давали 404.
/*
 * Fallback не тянет сессионное middleware.
 *
 * Маршрут объявлен в группе web, а через него проходят и запросы к
 * /api/*, для которых маршрут не нашёлся. StartSession и
 * AuthenticateSession на таких запросах обращаются к сессионному
 * guard'у, а у него нет метода viaRemember: неизвестный путь /api/*
 * падал с 500 вместо честного 404. Сама проверка кода и разбора
 * методов сессии не нужна, поэтому middleware снимаются точечно, а
 * вся группа web — нет.
 */
Route::fallback(function () {
    if (request()->is('api/*')) {
        // Fallback регистрируется как обычный маршрут и срабатывает на
        // ЛЮБОЙ метод, поэтому он «съедал» 405: GET /api/city (а маршрут
        // объявлен только POST) возвращал 404 вместо 405 и клиент не мог
        // отличить неверный путь от неверного метода.
        $uri = trim(request()->path(), '/');
        $methods = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === $uri)
            ->flatMap(fn ($route) => $route->methods())
            ->reject(fn ($method) => in_array($method, ['HEAD', 'OPTIONS'], true))
            ->unique()
            ->values();

        if ($methods->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Метод не поддерживается',
                'meta'    => ['allowed' => $methods->all()],
            ], 405);
        }

        return response()->json([
            'success' => false,
            'data'    => null,
            'error'   => 'Маршрут не найден',
            'meta'    => null,
        ], 404);
    }

    return app(SinglePageController::class)->index();
})->withoutMiddleware([
    // Сессия и её проверка на /api/* не нужны и вредны: guard по
    // умолчанию здесь sanctum, у него нет viaRemember, и любой
    // несуществующий путь /api/* отвечал 500 вместо 404.
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Session\Middleware\AuthenticateSession::class,
]);



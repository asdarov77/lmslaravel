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
});



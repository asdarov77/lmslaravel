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


// Route::fallback(function() {
//     return redirect('api/') ;
// });



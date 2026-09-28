<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\PrivateController;
use App\Http\Controllers\PrivateManiController;
use App\Http\Controllers\Group2learningController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\AukstructureController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\QuestionsController;
use App\Http\Controllers\GradeBoundaryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AircraftController;
use App\Http\Controllers\CoursesListController;
use App\Http\Controllers\CategoryListController;
use App\Http\Controllers\ClearDBController;
use App\Http\Controllers\FileLoadAndExtractController;
use App\Http\Controllers\UsersCoursesController;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Default route
//Route::any('*','TestController@test');

//
Route::post('/login', [AuthController::class, 'login']);

// Публичные v1-маршруты авторизации: /api/v1/login, /api/v1/register
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('v1.login');
    Route::post('/register', [AuthController::class, 'register'])->name('v1.register');
});

// API v1 routes — единая защищённая группа (все остальные /api/v1/* требуют токен Sanctum)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
            'error' => null,
            'meta' => null,
        ]);
    });

    // Versioned CRUD routes
    Route::apiResource('categories', CategoryController::class);
    Route::get('/users', [AuthController::class, 'index']);
    Route::post('/users', [AuthController::class, 'store']);
    Route::put('/users/{user}', [AuthController::class, 'update']);
    Route::patch('/users/{user}', [AuthController::class, 'update']);
    Route::delete('/users/{user}', [AuthController::class, 'destroy']);
    Route::get('/users/{user}', [AuthController::class, 'show']);
    Route::apiResource('courses', CourseController::class)->parameters(['courses' => 'course']);
});
//Route::post('login', ['before' => 'throttle:2,5', 'uses' => 'AuthController@login']);
// Категории без версионирования: только чтение (используется фронтендом)
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::post('/register', [AuthController::class, 'register']);
//
// блок пользователей
//
Route::post('/user/list', [AuthController::class, 'getUserList']); // вывод всех пользователей
Route::get('/user/list/{id}', [AuthController::class, 'getUser']); // вывод конкретного пользователя
Route::get('user/{id}/edit', [AuthController::class, 'editData']);   // !!!!проверить
Route::put('user/chpass/{id}', [AuthController::class, 'chpass']);   // смена пароля
Route::delete('user/{id}', [AuthController::class, 'destroy']); //->middleware('permission:manage-users');
Route::patch('user/{id}', [AuthController::class, 'update']);
//Route::put('user/chroll/{id}', [AuthController::class, 'chroll']);
Route::put('user/chperm/{id}', [AuthController::class, 'chperm']);

Route::post('group/learning/', [AuthController::class, 'group2learning']); // запись группы пользователей на курс
Route::apiResource('learning', Group2learningController::class);
Route::get('lessons/', [LessonsController::class, 'lessons']); // занятия  в иерархической структуре
Route::apiResource('aukstructure', AukstructureController::class);
Route::apiResource('role', RoleController::class);

//
//-------------------------------------------------------------------------------
//

// Healthcheck
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
// Versioned healthcheck: /api/v1/health (protected like the rest of v1)
Route::prefix('v1')->middleware('auth:sanctum')->get('/health', function () {
    return response()->json(['status' => 'ok'], 200, ['X-Health-Check' => 'v1']);
})->name('v1.health');

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/files/add', [FilesController::class, 'upload']);
});
//
// блок курсов старый
//
Route::middleware('auth:sanctum')->get('/courses/', [CoursesListController::class, 'getCourses'])->name('courses.list');    // вывод всех курсов
Route::get('/courses/cat/', [CategoryListController::class, 'getCatCourses']);
Route::get('/courses/cat/{id}/', [CategoryListController::class, 'getCatCoursesId']);  // вывод курсов для конкретной категории, с флагом видимости
// блок курсов и категорий новый
//
Route::apiResource('course', CourseController::class)->middleware('auth:sanctum');
Route::get('/coursemanifest/{id}', [CourseController::class, 'showmanifest']);
Route::get('/getlink/{id}', [CourseController::class, 'getlink']);
Route::get('/getfirstauk/{id}', [CourseController::class, 'get_first_auk']);
//-------------------- классы -----------------
Route::get('/classes/', [AircraftController::class, 'indexclasses']);
Route::get('/classesfs/', [AircraftController::class, 'showclassesfs']);
Route::get('/classess/{air}', [CourseController::class, 'showauks']);
Route::post('/classes/', [AircraftController::class, 'storeclasses']);



//
Route::apiResource('permissions', PermissionController::class);
//
//--------------------БД--------------------------------
Route::post('/clear-database', [ClearDBController::class, 'clear']);
//----------------загрузка распаковка архива курсов----------------------------
Route::post('/upload', [FileLoadAndExtractController::class, 'upload']);
Route::post('/extract', [FileLoadAndExtractController::class, 'extract']);
//----------------загрузка распаковка архива курсов---------пока отключен-------

//Route::post('/group/list', [GroupController::class,'index'] );
//Route::post('/group/list/{id}', [GroupController::class,'getCurGroup'] );
//Route::post('/group/reg', [GroupController::class,'store'] );    // добавить группу
//  ////Route::get('/group/{id}/edit', [GroupController::class, 'store']);
//Route::delete('/group/{id}', [GroupController::class, 'destroy']);
//Route::put('/group/{id}', [GroupController::class, 'update']);

//
// Список городов (может не нужен будет). Справочник.
//
Route::post('/city', [CityController::class, 'index']);
//
// блок групп
//
//Route::apiResource('groups',GroupController::class)->middleware(['auth:sanctum','permission: create-tasks']);
Route::apiResource('groups', GroupController::class)->middleware(['auth:sanctum']);
//
// блок // //
//   
Route::get('/private/{aircraft}/{auk}/imsmanifest.xml', [PrivateManiController::class, 'xmles00']); //->middleware('auth:sanctum');
//Route::get('/test', [PrivateManiController::class, 'test']);//->middleware('auth:sanctum');

Route::get('/private/{aircraft}/{auk}/index.html', [PrivateController::class, 'htmles00']); //->middleware('auth:sanctum');
Route::get('/private/{aircraft}/{auk}/{html}', [PrivateController::class, 'htmles']);
Route::get('/private/{aircraft}/{auk}/{html}/{html2}', [PrivateController::class, 'htmles2']);
Route::get('/private/{aircraft}/{auk}/{html}/{html2}/{html3}', [PrivateController::class, 'htmles3']); //->middleware('auth');
Route::get('/private/{aircraft}/{auk}/{html}/{html2}/{html3}/{html4}', [PrivateController::class, 'htmles4']); //->middleware('auth');
Route::get('/private/{aircraft}/{auk}/{html}/{html2}/{html3}/{html4}/{html5}', [PrivateController::class, 'htmles5']); //->middleware('auth');
Route::get('/private/{aircraft}/{auk}/{html}/{html2}/{html3}/{html4}/{html5}/{html6}', [PrivateController::class, 'htmles6']); //->middleware('auth');
Route::get('/private/{aircraft}/{auk}/{html}/{html2}/{html3}/{html4}/{html5}/{html6}/{html7}', [PrivateController::class, 'htmles7']); //->middleware('auth');
Route::get('/private/{aircraft}/{auk}/{html}/{html2}/{html3}/{html4}/{html5}/{html6}/{html7}/{html8}', [PrivateController::class, 'htmles8']); //->middleware('auth');


Route::get('/userauks', [UsersCoursesController::class, 'index']);
//---------------------блок работы со статическими файлами контента ---------------
Route::post('/search-files/', [SearchController::class, 'search']);
Route::post('/get-content/', [SearchController::class, 'get_file_content']); // возможно удалим
//-------------------------------------------------------------

//---------------------блок работы с избранным---------------
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/favorites/', [FavoriteController::class, 'index']);
    Route::post('/favorites/add', [FavoriteController::class, 'fav_add']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'remove']);
});

//---------------------блок работы с вопросами ---------------
//Route::post('/upload-gift/', 'GiftController@search');
Route::apiResource('gift', GiftController::class);
Route::delete('/gift-clear', [GiftController::class, 'truncate']);
Route::apiResource('questions', QuestionsController::class);

//---------------------блок настроек и вспомогательных таблиц ---------------

Route::apiResource('grade-boundary', GradeBoundaryController::class);
Route::apiResource('settings', SettingsController::class);
//Route::post('grade-boundary', 'GradeBoundaryController@store');


//-------------------------------------------------------------


// отлов всего не вошедшего в маршруты
//Route::any('{anything}','CatchAllController')->where('anything','*');

// Route::fallback(function() {
//     return 'Hm, why did you land here somehow?';
// });

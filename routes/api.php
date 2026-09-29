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
Route::post('/api/v1/login', [AuthController::class, 'login'])->name('api.v1.login');

// Categories CRUD for the existing (unversioned) frontend, which calls /api/categories.
// The v1-prefixed group below serves /api/v1/categories for the versioned clients.
Route::apiResource('categories', CategoryController::class)
    ->middleware(['auth:sanctum'])
    ->whereNumber('category');
// Запись категорий — по праву каталога (чтение остаётся для всех авторизованных).
Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.manage,courses.manage');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.manage,courses.manage')->whereNumber('category');
Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.manage,courses.manage')->whereNumber('category');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.manage,courses.manage')->whereNumber('category');

// API v1 routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // Актуальные права текущего пользователя (синхронизация state с БД)
    Route::get('/me', [AuthController::class, 'me'])->name('api.v1.me');
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
            'error' => null,
            'meta' => null,
        ]);
    });
    
    // Versioned CRUD routes
    // Чтение — для всех авторизованных; запись — по правам каталога (LMS-практика:
    // view отделён от manage).
    Route::apiResource('categories', CategoryController::class)
        ->whereNumber('category')
        ->middleware('permission:categories.manage,courses.manage')
        ->except(['index', 'show']);

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [AuthController::class, 'index']);
        Route::get('/users/{user}', [AuthController::class, 'show'])->whereNumber('user');
    });
    Route::post('/users', [AuthController::class, 'store'])
        ->middleware('permission:users.create');
    Route::put('/users/{user}', [AuthController::class, 'update'])
        ->middleware('permission:users.update')->whereNumber('user');
    Route::delete('/users/{user}', [AuthController::class, 'destroy'])
        ->middleware('permission:users.delete')->whereNumber('user');

    Route::apiResource('courses', CourseController::class)
        ->whereNumber('course')
        ->middleware('permission:courses.manage,content.manage')
        ->except(['index', 'show']);
});
//Route::post('login', ['before' => 'throttle:2,5', 'uses' => 'AuthController@login']);
Route::post('/register', [AuthController::class, 'register']);
//
// блок пользователей
//
Route::post('/user/list', [AuthController::class, 'getUserList'])->middleware(['auth:sanctum','permission:users.view,users.create,users.update']); // вывод всех пользователей
Route::get('/user/list/{id}', [AuthController::class, 'getUser'])->middleware(['auth:sanctum','permission:users.view,users.update'])->whereNumber('id'); // вывод конкретного пользователя
Route::get('user/{id}/edit', [AuthController::class, 'editData'])->middleware(['auth:sanctum','permission:users.view,users.update'])->whereNumber('id');
// Свой пароль может менять любой авторизованный; чужой — только users.update (проверка в контроллере)
Route::put('user/chpass/{id}', [AuthController::class, 'chpass'])->middleware('auth:sanctum')->whereNumber('id');
Route::delete('user/{id}', [AuthController::class, 'destroy'])->middleware('permission:users.delete')->whereNumber('id');
Route::patch('user/{id}', [AuthController::class, 'update'])->middleware('permission:users.update')->whereNumber('id');
//Route::put('user/chroll/{id}', [AuthController::class, 'chroll']);
Route::put('user/chperm/{id}', [AuthController::class, 'chperm'])->middleware('permission:users.permissions')->whereNumber('id');

Route::post('group/learning/', [AuthController::class, 'group2learning'])->middleware('permission:users.courses,create-tasks'); // запись группы пользователей на курс
Route::apiResource('learning', Group2learningController::class)->whereNumber('learning');
Route::get('lessons/', [LessonsController::class, 'lessons'])->middleware('auth:sanctum'); // занятия  в иерархической структуре
Route::apiResource('aukstructure', AukstructureController::class)
    ->middleware('auth:sanctum')
    ->whereNumber('aukstructure');
Route::post('/aukstructure', [AukstructureController::class, 'store'])->middleware('permission:content.manage');
Route::put('/aukstructure/{aukstructure}', [AukstructureController::class, 'update'])->middleware('permission:content.manage')->whereNumber('aukstructure');
Route::patch('/aukstructure/{aukstructure}', [AukstructureController::class, 'update'])->middleware('permission:content.manage')->whereNumber('aukstructure');
Route::delete('/aukstructure/{aukstructure}', [AukstructureController::class, 'destroy'])->middleware('permission:content.manage')->whereNumber('aukstructure');
// Управление ролями — только администраторам (users.permissions); чтение — авторизованным.
Route::apiResource('role', RoleController::class)->middleware('auth:sanctum')->whereNumber('role');
Route::post('/role', [RoleController::class, 'store'])->middleware('permission:users.permissions');
Route::put('/role/{role}', [RoleController::class, 'update'])->middleware('permission:users.permissions')->whereNumber('role');
Route::patch('/role/{role}', [RoleController::class, 'update'])->middleware('permission:users.permissions')->whereNumber('role');
Route::delete('/role/{role}', [RoleController::class, 'destroy'])->middleware('permission:users.permissions')->whereNumber('role');

//
//-------------------------------------------------------------------------------
//

// Healthcheck
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
// Explicit versioned healthcheck to support /api/v1/health in test environments
Route::get('/v1/health', function () {
    return response()->json(['status' => 'ok'], 200, ['X-Health-Check' => 'v1']);
})->name('v1.health');

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/files/add', [FilesController::class, 'upload']);
});
//
// блок курсов старый
//
Route::get('/courses/', [CoursesListController::class, 'getCourses'])->middleware('auth:sanctum');    // вывод всех курсов
Route::get('/courses/cat/', [CategoryListController::class, 'getCatCourses']);
Route::get('/courses/cat/{id}/', [CategoryListController::class, 'getCatCoursesId'])->whereNumber('id');  // вывод курсов для конкретной категории, с флагом видимости
// блок курсов и категорий новый
//
Route::apiResource('course', CourseController::class)->middleware('auth:sanctum')->whereNumber('course');
Route::get('/coursemanifest/{id}', [CourseController::class, 'showmanifest'])->whereNumber('id');
Route::get('/getlink/{id}', [CourseController::class, 'getlink'])->whereNumber('id');
Route::get('/getfirstauk/{id}', [CourseController::class, 'get_first_auk'])->whereNumber('id');
//-------------------- классы -----------------
Route::get('/classes/', [AircraftController::class, 'indexclasses'])->middleware('auth:sanctum');
Route::get('/classesfs/', [AircraftController::class, 'showclassesfs'])->middleware('auth:sanctum');
Route::get('/classess/{air}', [CourseController::class, 'showauks'])->middleware('auth:sanctum');
// Создание классов (самолётов) — по праву content.manage или courses.manage
Route::post('/classes/', [AircraftController::class, 'storeclasses'])->middleware(['auth:sanctum','permission:content.manage,courses.manage']);



//
// Справочник прав доступен авторизованным (экран назначения прав),
// изменение каталога прав — только администраторам (users.permissions).
Route::apiResource('permissions', PermissionController::class)
    ->middleware('auth:sanctum')
    ->whereNumber('permission');
Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:users.permissions');
Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:users.permissions')->whereNumber('permission');
Route::patch('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:users.permissions')->whereNumber('permission');
Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:users.permissions')->whereNumber('permission');
//
//--------------------БД--------------------------------
Route::post('/clear-database', [ClearDBController::class, 'clear'])->middleware(['auth:sanctum','permission:system.maintenance']);
//----------------загрузка распаковка архива курсов----------------------------
Route::post('/upload', [FileLoadAndExtractController::class, 'upload'])->middleware(['auth:sanctum','permission:files.upload,courses.manage']);
Route::post('/extract', [FileLoadAndExtractController::class, 'extract'])->middleware(['auth:sanctum','permission:courses.manage,content.manage']);
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
Route::post('/city', [CityController::class, 'index'])->middleware('auth:sanctum');
//
// блок групп
//
//Route::apiResource('groups',GroupController::class)->middleware(['auth:sanctum','permission: create-tasks']);
Route::apiResource('groups', GroupController::class)->middleware(['auth:sanctum'])->whereNumber('group');
// Модификация групп — по праву groups.manage (чтение остаётся для всех авторизованных)
Route::post('/groups', [GroupController::class, 'store'])->middleware('permission:groups.manage,create-tasks');
Route::put('/groups/{group}', [GroupController::class, 'update'])->middleware('permission:groups.manage,create-tasks')->whereNumber('group');
Route::patch('/groups/{group}', [GroupController::class, 'update'])->middleware('permission:groups.manage,create-tasks')->whereNumber('group');
Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->middleware('permission:groups.manage')->whereNumber('group');
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


Route::get('/userauks', [UsersCoursesController::class, 'index'])->middleware('auth:sanctum');
//---------------------блок работы со статическими файлами контента ---------------
Route::post('/search-files/', [SearchController::class, 'search'])->middleware('auth:sanctum');
Route::post('/get-content/', [SearchController::class, 'get_file_content'])->middleware('auth:sanctum'); // возможно удалим
//-------------------------------------------------------------

//---------------------блок работы с избранным---------------
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/favorites/', [FavoriteController::class, 'index']);
    Route::post('/favorites/add', [FavoriteController::class, 'fav_add']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'remove'])->whereNumber('id');
});

//---------------------блок работы с вопросами ---------------
//Route::post('/upload-gift/', 'GiftController@search');
Route::apiResource('gift', GiftController::class)->middleware('auth:sanctum')->whereNumber('gift');
Route::post('/gift', [GiftController::class, 'store'])->middleware('permission:questions.manage');
Route::put('/gift/{gift}', [GiftController::class, 'update'])->middleware('permission:questions.manage')->whereNumber('gift');
Route::patch('/gift/{gift}', [GiftController::class, 'update'])->middleware('permission:questions.manage')->whereNumber('gift');
Route::delete('/gift/{gift}', [GiftController::class, 'destroy'])->middleware('permission:questions.manage')->whereNumber('gift');
Route::delete('/gift-clear', [GiftController::class, 'truncate'])->middleware(['auth:sanctum','permission:system.maintenance']);
Route::apiResource('questions', QuestionsController::class)->middleware('auth:sanctum')->whereNumber('question');
Route::post('/questions', [QuestionsController::class, 'store'])->middleware('permission:questions.manage');
Route::put('/questions/{question}', [QuestionsController::class, 'update'])->middleware('permission:questions.manage')->whereNumber('question');
Route::patch('/questions/{question}', [QuestionsController::class, 'update'])->middleware('permission:questions.manage')->whereNumber('question');
Route::delete('/questions/{question}', [QuestionsController::class, 'destroy'])->middleware('permission:questions.manage')->whereNumber('question');

//---------------------блок настроек и вспомогательных таблиц ---------------

Route::apiResource('grade-boundary', GradeBoundaryController::class)->middleware('auth:sanctum')->whereNumber('grade-boundary');
Route::post('/grade-boundary', [GradeBoundaryController::class, 'store'])->middleware('permission:settings.manage');
Route::put('/grade-boundary/{grade-boundary}', [GradeBoundaryController::class, 'update'])->middleware('permission:settings.manage');
Route::patch('/grade-boundary/{grade-boundary}', [GradeBoundaryController::class, 'update'])->middleware('permission:settings.manage');
Route::delete('/grade-boundary/{grade-boundary}', [GradeBoundaryController::class, 'destroy'])->middleware('permission:settings.manage');
Route::apiResource('settings', SettingsController::class)->middleware('auth:sanctum')->whereNumber('setting');
Route::post('/settings', [SettingsController::class, 'store'])->middleware('permission:settings.manage');
Route::put('/settings/{setting}', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->whereNumber('setting');
Route::patch('/settings/{setting}', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->whereNumber('setting');
Route::delete('/settings/{setting}', [SettingsController::class, 'destroy'])->middleware('permission:settings.manage')->whereNumber('setting');
//Route::post('grade-boundary', 'GradeBoundaryController@store');


//-------------------------------------------------------------


// отлов всего не вошедшего в маршруты
//Route::any('{anything}','CatchAllController')->where('anything','*');

// Route::fallback(function() {
//     return 'Hm, why did you land here somehow?';
// });

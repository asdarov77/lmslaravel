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
// Версионированный алиас логина. Путь указан БЕЗ префикса /api: файл уже
// смонтирован под 'api', и раньше '/api/v1/login' давал '/api/api/v1/login'.
// Алиас сохранён, чтобы не сломать клиентов, которые зовут /api/v1/login.
Route::post('/v1/login', [AuthController::class, 'login'])->name('api.v1.login');

// Categories CRUD for the existing (unversioned) frontend, which calls /api/categories.
// The v1-prefixed group below serves /api/v1/categories for the versioned clients.
// ВАЖНО: apiResource ниже объявляет ТОЛЬКО чтение (only).
// Если оставить здесь store/update/destroy, последующие явные Route::post('/categories', ...)
// перезапишут маршрут Laravel'а (RouteCollection ключуется по method+uri) и потеряют
// auth:sanctum — тогда CheckUserPermission увидит Auth::user() === null и вернёт 401
// даже суперадминистратору. Поэтому запись объявляется отдельной группой ниже.
Route::apiResource('categories', CategoryController::class)
    ->only(['index', 'show'])
    ->middleware(['auth:sanctum'])
    ->whereNumber('category');
// Запись категорий — по праву каталога (чтение остаётся для всех авторизованных).
Route::middleware(['auth:sanctum', 'permission:categories.manage,courses.manage'])->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->whereNumber('category');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->whereNumber('category');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category');
});

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
    // Чтение объявляется ЯВНО: раньше /api/v1/categories/{id} существовал
    // только побочно — из-за второго mount этого же файла под префиксом
    // 'api/v1' в RouteServiceProvider. После удаления дубля чтение нужно
    // объявить здесь, иначе версионированный GET отдаёт 405.
    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'show'])
        ->whereNumber('category')
        ->middleware('permission:categories.manage,courses.view,courses.manage');

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

    // Чтение курса — по courses.view (его также видят те, кому выдано content.manage).
    Route::apiResource('courses', CourseController::class)
        ->only(['index', 'show'])
        ->whereNumber('course')
        ->middleware('permission:courses.view,content.manage');

    // Запись курса — по courses.manage или content.manage.
    Route::middleware('permission:courses.manage,content.manage')->group(function () {
        Route::apiResource('courses', CourseController::class)
            ->only(['store', 'update', 'destroy'])
            ->whereNumber('course');
    });
});
//Route::post('login', ['before' => 'throttle:2,5', 'uses' => 'AuthController@login']);
Route::post('/register', [AuthController::class, 'register']);
//
// блок пользователей
//
// Список пользователей доступен любому авторизованному: AuthController::getUserList
// сам ограничивает выдачу группой (администратор видит всех, остальные — только
// свою группу), а middleware users.view делал эту ветку мёртвым кодом —
// обучаемый не мог посмотреть состав своей группы. Авторизация здесь по области
// видимости, а не по глобальному праву каталога.
Route::post('/user/list', [AuthController::class, 'getUserList'])->middleware('auth:sanctum'); // вывод всех пользователей
Route::get('/user/list/{id}', [AuthController::class, 'getUser'])->middleware(['auth:sanctum','permission:users.view,users.update'])->whereNumber('id'); // вывод конкретного пользователя
Route::get('user/{id}/edit', [AuthController::class, 'editData'])->middleware(['auth:sanctum','permission:users.view,users.update'])->whereNumber('id');
// Свой пароль может менять любой авторизованный; чужой — только users.update (проверка в контроллере)
Route::put('user/chpass/{id}', [AuthController::class, 'chpass'])->middleware('auth:sanctum')->whereNumber('id');
Route::delete('user/{id}', [AuthController::class, 'destroy'])->middleware(['auth:sanctum', 'permission:users.delete'])->whereNumber('id');
Route::patch('user/{id}', [AuthController::class, 'update'])->middleware(['auth:sanctum', 'permission:users.update'])->whereNumber('id');
//Route::put('user/chroll/{id}', [AuthController::class, 'chroll']);
// Управление правами. На маршруте — «можно ли вообще открыть управление
// правами»: администратор (users.permissions) либо инструктор
// (users.view). Кому именно и какие права можно назначить решает
// PermissionScope в контроллере: инструктор не дотянется до прав,
// которых нет у него самого, и до администраторов.
Route::put('user/chperm/{id}', [AuthController::class, 'chperm'])
    ->middleware(['auth:sanctum', 'permission:users.permissions,users.view'])->whereNumber('id');

// Отдельная страница управления правами: кому актор вправе менять права.
Route::get('user/manageable', [AuthController::class, 'manageableUsers'])
    ->middleware(['auth:sanctum', 'permission:users.permissions,users.view']);

Route::post('group/learning/', [AuthController::class, 'group2learning'])->middleware(['auth:sanctum', 'permission:users.courses,create-tasks']); // запись группы пользователей на курс
// Ранее learning был вообще без middleware — любой неавторизованный мог писать в таблицу.
Route::apiResource('learning', Group2learningController::class)
    ->only(['index', 'show'])
    ->middleware('auth:sanctum')
    ->whereNumber('learning');
Route::middleware(['auth:sanctum', 'permission:users.courses,create-tasks'])->group(function () {
    Route::post('/learning', [Group2learningController::class, 'store']);
    Route::put('/learning/{learning}', [Group2learningController::class, 'update'])->whereNumber('learning');
    Route::patch('/learning/{learning}', [Group2learningController::class, 'update'])->whereNumber('learning');
    Route::delete('/learning/{learning}', [Group2learningController::class, 'destroy'])->whereNumber('learning');
});
Route::get('lessons/', [LessonsController::class, 'lessons'])->middleware('auth:sanctum'); // занятия  в иерархической структуре
Route::apiResource('aukstructure', AukstructureController::class)
    ->only(['index', 'show'])
    ->middleware('auth:sanctum')
    ->whereNumber('aukstructure');
Route::middleware(['auth:sanctum', 'permission:content.manage'])->group(function () {
    Route::post('/aukstructure', [AukstructureController::class, 'store']);
    Route::put('/aukstructure/{aukstructure}', [AukstructureController::class, 'update'])->whereNumber('aukstructure');
    Route::patch('/aukstructure/{aukstructure}', [AukstructureController::class, 'update'])->whereNumber('aukstructure');
    Route::delete('/aukstructure/{aukstructure}', [AukstructureController::class, 'destroy'])->whereNumber('aukstructure');
});
// Управление ролями — только администраторам (users.permissions); чтение — авторизованным.
Route::apiResource('role', RoleController::class)->only(['index', 'show'])->middleware('auth:sanctum')->whereNumber('role');
Route::middleware(['auth:sanctum', 'permission:users.permissions'])->group(function () {
    Route::post('/role', [RoleController::class, 'store']);
    Route::put('/role/{role}', [RoleController::class, 'update'])->whereNumber('role');
    Route::patch('/role/{role}', [RoleController::class, 'update'])->whereNumber('role');
    Route::delete('/role/{role}', [RoleController::class, 'destroy'])->whereNumber('role');
});

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
Route::apiResource('course', CourseController::class)
    ->only(['index', 'show'])
    ->middleware('auth:sanctum')
    ->whereNumber('course');
// Ранее любой авторизованный мог создавать/удалять курсы: чтение открыто,
// изменение — только по courses.manage или content.manage.
Route::middleware(['auth:sanctum', 'permission:courses.manage,content.manage'])->group(function () {
    Route::post('/course', [CourseController::class, 'store']);
    Route::put('/course/{course}', [CourseController::class, 'update'])->whereNumber('course');
    Route::patch('/course/{course}', [CourseController::class, 'update'])->whereNumber('course');
    Route::delete('/course/{course}', [CourseController::class, 'destroy'])->whereNumber('course');
});
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
    ->only(['index', 'show'])
    ->middleware('auth:sanctum')
    ->whereNumber('permission');

// Каталог прав, сгруппированный по разделам. Группировка берётся из
// config/permissions.php, поэтому не расходится с бэкендом.
Route::get('/permissions/catalog', [PermissionController::class, 'catalog'])
    ->middleware(['auth:sanctum', 'permission:users.permissions,users.view']);
Route::middleware(['auth:sanctum', 'permission:users.permissions'])->group(function () {
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->whereNumber('permission');
    Route::patch('/permissions/{permission}', [PermissionController::class, 'update'])->whereNumber('permission');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->whereNumber('permission');
});
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
Route::apiResource('groups', GroupController::class)->only(['index', 'show'])->middleware(['auth:sanctum'])->whereNumber('group');
// Модификация групп — по праву groups.manage (чтение остаётся для всех авторизованных)
Route::post('/groups', [GroupController::class, 'store'])->middleware(['auth:sanctum', 'permission:groups.manage,create-tasks']);
Route::put('/groups/{group}', [GroupController::class, 'update'])->middleware(['auth:sanctum', 'permission:groups.manage,create-tasks'])->whereNumber('group');
Route::patch('/groups/{group}', [GroupController::class, 'update'])->middleware(['auth:sanctum', 'permission:groups.manage,create-tasks'])->whereNumber('group');
Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->middleware(['auth:sanctum', 'permission:groups.manage'])->whereNumber('group');
//
// блок // //
//   
// Подписанный URL базового каталога курса. Требует авторизации: без входа
// получить подпись нельзя. Фронтенд подставляет его в <base href>, и все
// относительные ресурсы наследуют expires/signature из префикса пути.
Route::get('/private/signed-url', [PrivateController::class, 'signedUrl'])->middleware('auth:sanctum');

// Приватный контент курсов. Доступ — по подписи (ValidatePrivateContentSignature),
// а не по auth:sanctum: вложенные ресурсы (CSS/JS/картинки) браузер запрашивает
// напрямую, без заголовка Authorization. Подпись встроена в путь:
// api/private/{aircraft}/{auk}/{expires}/{signature}/{file} — так она
// наследуется всеми относительными ссылками внутри документа.
Route::get('/private/{aircraft}/{auk}/{path}', [PrivateController::class, 'htmlesPath'])
    ->where('path', '.*')
    ->middleware('private.content.signature');
//Route::get('/test', [PrivateManiController::class, 'test']);//->middleware('auth:sanctum');


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
Route::apiResource('gift', GiftController::class)->only(['index', 'show'])->middleware('auth:sanctum')->whereNumber('gift');
Route::middleware(['auth:sanctum', 'permission:questions.manage'])->group(function () {
    Route::post('/gift', [GiftController::class, 'store']);
    Route::put('/gift/{gift}', [GiftController::class, 'update'])->whereNumber('gift');
    Route::patch('/gift/{gift}', [GiftController::class, 'update'])->whereNumber('gift');
    Route::delete('/gift/{gift}', [GiftController::class, 'destroy'])->whereNumber('gift');
});
Route::delete('/gift-clear', [GiftController::class, 'truncate'])->middleware(['auth:sanctum','permission:system.maintenance']);
Route::apiResource('questions', QuestionsController::class)->only(['index', 'show'])->middleware('auth:sanctum')->whereNumber('question');
Route::middleware(['auth:sanctum', 'permission:questions.manage'])->group(function () {
    Route::post('/questions', [QuestionsController::class, 'store']);
    Route::put('/questions/{question}', [QuestionsController::class, 'update'])->whereNumber('question');
    Route::patch('/questions/{question}', [QuestionsController::class, 'update'])->whereNumber('question');
    Route::delete('/questions/{question}', [QuestionsController::class, 'destroy'])->whereNumber('question');
});

//---------------------блок настроек и вспомогательных таблиц ---------------

Route::apiResource('grade-boundary', GradeBoundaryController::class)->only(['index', 'show'])->middleware('auth:sanctum')->whereNumber('grade-boundary');
Route::post('/grade-boundary', [GradeBoundaryController::class, 'store'])->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::put('/grade-boundary/{grade-boundary}', [GradeBoundaryController::class, 'update'])->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::patch('/grade-boundary/{grade-boundary}', [GradeBoundaryController::class, 'update'])->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::delete('/grade-boundary/{grade-boundary}', [GradeBoundaryController::class, 'destroy'])->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::apiResource('settings', SettingsController::class)->only(['index', 'show'])->middleware('auth:sanctum')->whereNumber('setting');
Route::post('/settings', [SettingsController::class, 'store'])->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::put('/settings/{setting}', [SettingsController::class, 'update'])->middleware(['auth:sanctum', 'permission:settings.manage'])->whereNumber('setting');
Route::patch('/settings/{setting}', [SettingsController::class, 'update'])->middleware(['auth:sanctum', 'permission:settings.manage'])->whereNumber('setting');
Route::delete('/settings/{setting}', [SettingsController::class, 'destroy'])->middleware(['auth:sanctum', 'permission:settings.manage'])->whereNumber('setting');
//Route::post('grade-boundary', 'GradeBoundaryController@store');


//-------------------------------------------------------------


// отлов всего не вошедшего в маршруты
//Route::any('{anything}','CatchAllController')->where('anything','*');

// Route::fallback(function() {
//     return 'Hm, why did you land here somehow?';
// });

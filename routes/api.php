<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\PrivateController;
use App\Http\Controllers\TutorController;
use App\Http\Controllers\PrivateManiController;
use App\Http\Controllers\Group2learningController;
use App\Http\Controllers\LessonsController;
use App\Http\Controllers\AukstructureController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\ChunkUploadController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\QuestionsController;
use App\Http\Controllers\GradeBoundaryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AircraftController;
use App\Http\Controllers\CoursesListController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\MyLearningController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ExamController;
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
// Версионированный алиас логина. Путь указан БЕЗ префикса /api: файл уже
// смонтирован под 'api', и раньше '/api/v1/login' давал '/api/api/v1/login'.
// Алиас сохранён, чтобы не сломать клиентов, которые зовут /api/v1/login.
Route::post('/v1/login', [AuthController::class, 'login'])->name('api.v1.login')->middleware('throttle:login');

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
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
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
// Назначение ролей. Маршрут был закомментирован, хотя страница
// UserChrole.vue продолжала вызывать PUT /api/user/chroll/{id} — то есть
// роль нельзя было назначить ни через UI, ни через API, и все назначения
// в боевой базе шли через свободную строку users.role, которую писал
// PATCH /api/user/{id} без всякой проверки.
//
// Право — users.permissions, то же, что у chperm: назначение роли не
// мельче назначения прав, а инструктор, у которого есть users.view,
// до этой страницы не дотягивается.
Route::put('user/chroll/{id}', [AuthController::class, 'chroll'])
    ->middleware(['auth:sanctum', 'permission:users.permissions'])->whereNumber('id');
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
// Личный кабинет обучаемого: учебный план и дашборд.
//
// Требования к этим данным минимальны — любой авторизованный имеет право
// видеть СВОЙ план. Ограничение не в правах, а в области данных: контроллер
// читает записи только своей группы, поэтому отдельное право (например
// content.view) не нужно и не должно быть условием показа.
//
// До этого пункт меню «Учебный план» вёл на админскую форму записи групп
// (/group/learning, право users.courses) и отдавал обучаемому 403.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my/learning', [MyLearningController::class, 'plan']);
    Route::get('/my/dashboard', [MyLearningController::class, 'dashboard']);

    // Прогресс по урокам курса. Раньше он жил в localStorage и терялся
    // при смене устройства, поэтому «продолжить обучение» было видно
    // только в том браузере, где урок уже открывали.
    Route::get('/my/progress/{course}', [LessonProgressController::class, 'show'])
        ->whereNumber('course');
    Route::post('/my/progress', [LessonProgressController::class, 'store']);

    // Сертификаты об окончании курса. Печатная HTML-страница, а не
    // PDF-файл: генератора PDF в проекте нет, а печать браузером даёт
    // тот же документ с тем же проверочным кодом.
    Route::get('/my/certificates', [CertificateController::class, 'index']);
    Route::get('/my/certificates/{course}', [CertificateController::class, 'show'])
        ->whereNumber('course');
    Route::delete('/my/progress/{lesson}', [LessonProgressController::class, 'destroy'])
        ->whereNumber('lesson');

    // Сводка для администратора и инструктора. Отдельная от
    // /my/dashboard: там «моё обучение», а здесь «что происходит в
    // системе» — по области видимости актора.
    Route::get('/dashboard/summary', ManagerDashboardController::class);
});

// Календарь учебного процесса: лента периодов обучения из
// group2learnings. Область видимости задаёт сам контроллер (своя группа
// для обучаемого, все группы для методиста), поэтому доменное право здесь
// не требуется — фильтровать список изнутри дешевле и безопаснее, чем
// запрещать доступ целиком.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index']);
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
    // Загрузка файлов требовала только auth:sanctum — право files.upload
    // из каталога на этом маршруте не проверялось, то есть загрузить
    // мог любой вошедший, включая обучаемого.
    Route::post('/files/add', [FilesController::class, 'upload'])
        ->middleware('permission:files.upload,content.manage,courses.manage');
});

//
// Файловый менеджер.
//
// Старый POST /files/add остаётся: он используется компонентом
// FileLoadSimple.vue, который подключают страницы курсов. Менеджер его
// не заменяет — у него другая модель (папки, перенос, докачка), и
// ломать существующий сценарий незачем.
//
// Право одно на весь блок, а не на каждый маршрут: перечисление
// прав в десяти строках разъезжается сам собой — добавили маршрут и
// забыли middleware, и он стал доступен всем, кто вошёл. Здесь забыть
// нельзя: маршрут без прав попал бы внутрь группы без них.
//
// Чтение закрыто тем же правом, что и запись. Файлы менеджера — личные
// файлы пользователя, а не учебный контент: право content.view
// («смотреть АУК») не даёт оснований видеть личные загрузки.
//
Route::middleware(['auth:sanctum', 'permission:files.upload,content.manage,courses.manage'])
    ->prefix('filemanager')
    ->group(function () {
        // Содержимое каталога: папки, файлы, хлебные крошки, пределы.
        Route::get('/', [FileManagerController::class, 'index'])->name('api.filemanager.index');

        // Папки.
        Route::get('/folders', [FileManagerController::class, 'folders'])->name('api.filemanager.folders.index');
        Route::post('/folders', [FileManagerController::class, 'storeFolder'])->name('api.filemanager.folders.store');
        Route::patch('/folders/{folder}', [FileManagerController::class, 'updateFolder'])
            ->whereNumber('folder')->name('api.filemanager.folders.update');
        Route::post('/folders/{folder}/move', [FileManagerController::class, 'moveFolder'])
            ->whereNumber('folder')->name('api.filemanager.folders.move');
        Route::delete('/folders/{folder}', [FileManagerController::class, 'destroyFolder'])
            ->whereNumber('folder')->name('api.filemanager.folders.destroy');

        // Файлы.
        //
        // Статические сегменты move/delete объявлены ПЕРЕД параметром
        // {file}. whereNumber('file') их и не пустил бы, но порядок
        // объявления в этом файле значим (см. заметку в начале), и
        // полагаться только на whereNumber — значит оставить правило
        // работать при первой же правке маршрута.
        Route::post('/files/move', [FileManagerController::class, 'moveFiles'])->name('api.filemanager.files.move');
        Route::post('/files/delete', [FileManagerController::class, 'destroyFiles'])->name('api.filemanager.files.destroy');
        Route::patch('/files/{file}', [FileManagerController::class, 'updateFile'])
            ->whereNumber('file')->name('api.filemanager.files.update');
        Route::delete('/files/{file}', [FileManagerController::class, 'destroyFile'])
            ->whereNumber('file')->name('api.filemanager.files.destroy');
        Route::get('/files/{file}/download', [FileManagerController::class, 'download'])
            ->whereNumber('file')->name('api.filemanager.download');

        // Загрузка по частям: init / chunk / complete / abort.
        Route::post('/uploads/init', [ChunkUploadController::class, 'init'])->name('api.filemanager.uploads.init');
        Route::post('/uploads/{upload}/chunk', [ChunkUploadController::class, 'chunk'])
            ->where('upload', '[a-f0-9]{40}')->name('api.filemanager.uploads.chunk');
        Route::post('/uploads/{upload}/complete', [ChunkUploadController::class, 'complete'])
            ->where('upload', '[a-f0-9]{40}')->name('api.filemanager.uploads.complete');
        Route::delete('/uploads/{upload}', [ChunkUploadController::class, 'destroy'])
            ->where('upload', '[a-f0-9]{40}')->name('api.filemanager.uploads.destroy');
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
// Витрина курсов и самостоятельная запись. Отдельна от /api/courses:
// там учебный план (только записанное), здесь каталог с отметкой
// «записан/не записан».
Route::get('/catalog', [CatalogController::class, 'index'])->middleware('auth:sanctum');
Route::post('/catalog/{course}/enroll', [CatalogController::class, 'enroll'])
    ->middleware('auth:sanctum')->whereNumber('course');
Route::delete('/catalog/{course}/enroll', [CatalogController::class, 'unenroll'])
    ->middleware('auth:sanctum')->whereNumber('course');

// Материал курса. Раньше эти три маршрута висели без middleware, и
// любой вошедший читал манифест и ссылки любого курса по идентификатору.
// С появлением витрины тот же обход позволял открыть незаписанный курс.
Route::get('/coursemanifest/{id}', [CourseController::class, 'showmanifest'])
    ->middleware('auth:sanctum')->whereNumber('id');
Route::get('/getlink/{id}', [CourseController::class, 'getlink'])
    ->middleware('auth:sanctum')->whereNumber('id');
Route::get('/getfirstauk/{id}', [CourseController::class, 'get_first_auk'])
    ->middleware('auth:sanctum')->whereNumber('id');
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
// Поиск по содержимому приватных файлов курса — методическая операция.
// Раньше маршрут висел только на auth:sanctum, и любой вошедший читал
// фрагменты учебного материала чужого курса, указав aircraft и path
// в теле запроса.
Route::post('/search-files/', [SearchController::class, 'search'])
    ->middleware(['auth:sanctum', 'permission:content.manage,courses.manage']);

// Глобальный поиск по LMS: курсы, темы, специальности и — по правам —
// группы, люди и банк вопросов. Область видимости — по CourseVisibility,
// то есть так же, как у каталога курсов.
Route::get('/search', [GlobalSearchController::class, 'index'])->middleware('auth:sanctum');
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
// Экзамены: назначение, выдача вопросов, приём попыток.
//
// Чтение — любой авторизованный, но КАЖДЫЙ видит только назначенное ему
// (область видимости задаёт контроллер). Запись — по exams.manage.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my/exams', [ExamController::class, 'mine']);
    Route::get('/exam-attempts', [ExamController::class, 'attempts']);
    // Вопросы экзамена и приём попытки — тоже любой авторизованный:
    // право «управлять экзаменами» не нужно тому, кто их сдаёт.
    Route::get('/exams/{exam}/questions', [ExamController::class, 'questions'])->whereNumber('exam');
    Route::post('/exams/{exam}/attempts', [ExamController::class, 'submit'])->whereNumber('exam');
});
Route::middleware(['auth:sanctum', 'permission:exams.manage'])->group(function () {
    Route::apiResource('exams', ExamController::class)->only(['index', 'store', 'update', 'destroy'])->whereNumber('exam');
});

// Банк вопросов больше НЕ открыт всем авторизованным.
//
// Здесь отдавались ответы вместе с is_correct, поэтому любой вошедший —
// включая обучаемого — мог вычитать правильные ответы на любой вопрос и
// «сдать» экзамен не отвечая. Экзамен теперь берёт вопросы через
// /exams/{exam}/questions, где is_correct нет, а проверка идёт на
// сервере. Админские инструменты (ExamineMain, QuestionEdit, QuestionNew)
// работают по-прежнему: им нужно видеть правильный вариант, и у них
// есть questions.view / questions.manage.
Route::apiResource('questions', QuestionsController::class)
    ->only(['index', 'show'])
    // auth:sanctum идёт первым: иначе авторизованный получал 401 вместо
    // 403, и фронт не мог отличить «нет прав» от «истёк токен».
    ->middleware(['auth:sanctum', 'permission:questions.view,questions.manage'])
    ->whereNumber('question');
Route::middleware(['auth:sanctum', 'permission:questions.manage'])->group(function () {
    // Сводка банка: счётчики по специальностям/темам и нарушения
    // целостности (вопросы без ответов или без верного варианта).
    Route::get('/questions/statistics', [QuestionsController::class, 'statistics']);
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
// Переключатель способа раздачи приватного контента (php | nginx).
//
// Регистрируется ДО apiResource('settings'), иначе PUT /settings/content-delivery
// перехватил бы маршрут /settings/{setting} с setting='content-delivery' и
// правило settings.manage не сработало бы. Ниже whereNumber('setting') стоит
// только на apiResource, поэтому конфликта с порядком регистрации нет.
// ---------------------------------------------------------------- ТРЕНАЖЁР
//
// Локальная генерация вопросов по материалам курсов.
//
// Права проверяются в контроллере (tutor.use / tutor.manage), а не
// middleware: у тренажёра два разных права на два разных действия,
// и право методиста на индексацию не должно открывать ему подготовку
// сессий, а право обучаемого — индексацию.
//
// Порядок регистрации важен: '/tutor/sessions/{session}/next-question'
// объявлен ДО '/tutor/sessions/{session}', иначе статический сегмент
// ушёл бы в параметр и маршрут перестал бы совпадать.
Route::prefix('v1/tutor')->middleware('auth:sanctum')->group(function () {
    Route::get('/health', [TutorController::class, 'health']);

    Route::get('/materials', [TutorController::class, 'materials']);
    Route::post('/sessions', [TutorController::class, 'startSession']);

    // Статический сегмент раньше параметра.
    Route::get('/sessions/{session}/next-question', [TutorController::class, 'nextQuestion']);
    Route::get('/sessions/{session}', [TutorController::class, 'sessionShow']);
    Route::post('/sessions/{session}/finish', [TutorController::class, 'finishSession']);

    Route::post('/items/{item}/answer', [TutorController::class, 'answer']);

    Route::get('/stats', [TutorController::class, 'stats']);

    // Методистские методы. Право проверяется в контроллере.
    Route::prefix('admin')->group(function () {
        Route::get('/materials', [TutorController::class, 'adminMaterials']);
        Route::post('/materials/index', [TutorController::class, 'indexMaterial']);
    });
});

// Объявления. Чтение ленты — любому вошедшему (видимость решает
// Announcement::visibilityFor), публикация — по announcements.manage.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/audiences', [AnnouncementController::class, 'audiences']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])
        ->whereNumber('announcement');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
        ->whereNumber('announcement');
});

// Форум. Чтение и участие — по доступу к курсу (CourseAccess),
// модерация — по forum.moderate.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/forum/topics', [ForumController::class, 'index']);
    Route::post('/forum/topics', [ForumController::class, 'storeTopic']);
    Route::get('/forum/topics/{topic}', [ForumController::class, 'showTopic'])->whereNumber('topic');
    Route::patch('/forum/topics/{topic}', [ForumController::class, 'updateTopic'])->whereNumber('topic');
    Route::delete('/forum/topics/{topic}', [ForumController::class, 'destroyTopic'])->whereNumber('topic');
    Route::post('/forum/topics/{topic}/posts', [ForumController::class, 'storePost'])->whereNumber('topic');
    Route::delete('/forum/posts/{post}', [ForumController::class, 'destroyPost'])->whereNumber('post');
});

// Грейдбук преподавателя. Право grading.manage проверяется в
// контроллере: журнал содержит персональные данные группы.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gradebook', [GradebookController::class, 'index']);
    Route::put('/gradebook/cell', [GradebookController::class, 'updateCell']);
    Route::get('/gradebook/export', [GradebookController::class, 'export']);
});

// Проверка кода сертификата — без авторизации: код и есть подтверждение.
// Без этого «проверка» была бы недоступна тому, кому сертификат выдан:
// у получателя нет аккаунта в системе.
Route::get('/certificates/verify/{code}', [CertificateController::class, 'verify'])
    ->where('code', '[A-Za-z0-9]{18}');

// Настройки тренажёра: вкл/выкл и диагностика движка.
// Регистрируются до /settings/{setting}, иначе перехватятся параметром.
// Уведомления пользователя (колокольчик в шапке).
Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])
    ->middleware('auth:sanctum');
Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])
    ->middleware('auth:sanctum');
// Аватар пользователя.
Route::post('/user/avatar', [\App\Http\Controllers\AuthController::class, 'updateAvatar'])
    ->middleware('auth:sanctum');

Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])
    ->middleware('auth:sanctum');

Route::get('/settings/tutor', [SettingsController::class, 'tutor'])
    ->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::put('/settings/tutor', [SettingsController::class, 'updateTutor'])
    ->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::get('/settings/tutor/probe', [SettingsController::class, 'tutorProbe'])
    ->middleware(['auth:sanctum', 'permission:settings.manage']);

Route::get('/settings/content-delivery', [SettingsController::class, 'contentDelivery'])
    ->middleware(['auth:sanctum', 'permission:settings.manage']);
Route::put('/settings/content-delivery', [SettingsController::class, 'updateContentDelivery'])
    ->middleware(['auth:sanctum', 'permission:settings.manage']);

Route::apiResource('settings', SettingsController::class)->only(['index', 'show'])->middleware(['auth:sanctum', 'permission:settings.manage'])->whereNumber('setting');
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

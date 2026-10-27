<?php

namespace App\Http\Controllers;

use App\Http\Filters\CourseFilter;
use App\Http\Filters\AukstructureFilter;
use App\Http\Requests\Course\FilterRequest;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Support\CourseAccess;
use App\Models\Aukstructure;
use App\Models\Link;
use App\Models\Category;
use App\Models\Group;
use App\Models\Group2learning;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;

/**
 * CRUD курсов (/api/course, /api/v1/courses) и точки входа материала.
 *
 * Здесь сталкиваются три разных вопроса, и их важно не путать:
 *  - список курсов ограничен CourseVisibility (обучаемый видит подписки
 *    своей группы);
 *  - открытие материала решает CourseAccess — записан ли пользователь;
 *  - сами байты файла отдаёт PrivateController по подписанному пути.
 *
 * index: CourseFilter + CourseVisibility::restrictToEnrolled + пагинация.
 * show: CourseAccess::authorizeOpen. destroy удаляет каталог по path_hash.
 */
class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //public function index($show)

    // вывод курсов. пока костыль. администратор видит все курсы,остальные только те на которые подписан пользователь
    // public function index()
    // {
    //     //$course = Course::where('visible',$show)->get();        
    //     if (Auth::user()->role == "Администратор")
    //     {
    //         $cat = Category::all();
    //         foreach($cat as $item)
    //             $item->courses;
    //     }
    //     else 
    //     {
    //         $temp = Auth::user()->categories;
    //         foreach ($temp as $_temp)
    //             $_temp->courses;
    //         return $temp;
    //     }        
    //     return $cat;

    // }

    public function index(FilterRequest $request)
    {
        $data = $request->validated();
        $perPage = (int)($data['per_page'] ?? 15);
        $page = (int)($data['page'] ?? 1);

        $courseFilter = app()->make(CourseFilter::class, ['queryParams' => array_filter($data)]);
        $query = Course::filter($courseFilter)->orderByDesc('id');

        // Область видимости: обучаемый получает только курсы своей группы.
        // Методисту и администратору (courses.manage) остаётся полный
        // каталог, иначе нельзя было бы увидеть и отредактировать курс,
        // который ещё никому не назначен.
        \App\Support\CourseVisibility::restrictToEnrolled($query, $request->user());

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $meta = [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];

        return response()->json([
            'data' => $paginator->items(),
            'meta' => $meta,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Правила валидации для создания/обновления курса.
     * `name` — алиас `title` для API v1, оба варианта равнозначны.
     *
     * @return array<string, mixed>
     */
    private function courseRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required_without';

        return [
            'name' => $partial ? 'sometimes|nullable|string' : $required . ':title|nullable|string',
            'title' => $partial ? 'sometimes|nullable|string' : $required . ':name|nullable|string',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'path' => 'nullable|string',
            'duration' => 'nullable|integer|min:0',
            'status' => 'nullable|string|max:50',
            'visible' => 'nullable|boolean',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
            'category_id' => 'nullable|integer|exists:categories,id',
        ];
    }

    /**
     * Значения, которые реально присутствуют в запросе.
     * Это нужно, чтобы частичный PATCH не затирал незаданные колонки.
     *
     * @return array<string, mixed>
     */
    private function courseAttributes(Request $request): array
    {
        $attributes = [];

        if ($request->filled('name') || $request->filled('title')) {
            $attributes['title'] = $request->filled('name')
                ? $request->input('name')
                : $request->input('title');
        }

        foreach (['description', 'path', 'status', 'duration', 'category_id', 'aircraft_id'] as $key) {
            if ($request->has($key)) {
                $attributes[$key] = $request->input($key);
            }
        }

        if ($request->has('short_description')) {
            $attributes['short_description'] = $request->input('short_description');
        }

        if ($request->has('long_description')) {
            $attributes['long_description'] = $request->input('long_description');
        }

        if ($request->has('visible')) {
            $attributes['visible'] = $request->boolean('visible');
        }

        foreach (['opens_at', 'closes_at'] as $key) {
            if ($request->has($key)) {
                $attributes[$key] = $request->input($key);
            }
        }

        return $attributes;
    }

    public function store(Request $request)
    {
        $request->validate($this->courseRules());

        $course = new Course();
        $course->fill($this->courseAttributes($request));
        $course->visible = $course->visible ?? true;
        $course->status = $course->status ?? 'active';
        $course->save();

        if ($request->filled('category_id')) {
            $course->categories()->sync([$request->input('category_id')]);
        }

        return response()->json($course->fresh(), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */



    //-----------------------------рабочий вариант---------------------------------------------
    public function show(Request $request, $id)
    {
        $course = Course::with(['categories','aircraft','aukstructures.links'])->findOrFail($id);

        // Материал курса открыт записанному или управляющему. Раньше
        // здесь стоял только auth:sanctum, и любой вошедший читал курс
        // по идентификатору: идентификаторы идут подряд, перебор
        // занимает секунды. С появлением витрины тот же обход
        // открывал бы незаписанный курс.
        CourseAccess::authorizeOpen($request->user(), $course);

        // склейка пути        
        //         $courses_path = Config::get('app.courses_path'); // usr/local/share
        //         $aircraft_path = trim($course->aircraft->path); // Ил-76
        //         $auk_path=trim($course->path);
        //         $full_path = $courses_path . '/' . $aircraft_path . '/' . $auk_path . '/index.html';
        // //        $file = readfile($full_path, $use_include_path = true);
        //         //$file = readfile($full_path);
        //         $content = file_get_contents($full_path ); // читаем содержимое файла в строку, readfile читает в буфер           
        // // $content вставим функцию отправки файлов пунктов меню


        //         $response = [
        //               'content' => $content,
        //               'course' => $course,
        //               //'full_path' => $full_path, 
        //           ];
        //          return response($response, 201);

        return response()->json($course);
    }

    
    //----------------------------------------------------


    /**
     * GET /api/course-player/{course} — единый endpoint открытия курса.
     *
     * Возвращает одним ответом всё, что плеер тянет в mounted() шестью
     * запросами: курс, дерево разделов, избранное и entry (составляющие
     * подписанного пути к первому материалу). Дерево — только поля,
     * нужные UI, разрезанное по категории через ?category_id=; entry
     * считается сервером, поэтому кадр открывается без цепочки RTT.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $courseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function player(Request $request, $courseId)
    {
        $course = Course::with('aircraft')->find($courseId);

        if (! $course || ! $course->aircraft) {
            abort(404, 'Курс не найден');
        }

        CourseAccess::authorizeOpen($request->user(), $course);

        // Разрез по специальности: тот же код, что применял фронтенд,
        // теперь единственный источник правды — сервер.
        $code = null;
        if ($request->filled('category_id')) {
            $code = Category::where('id', (int) $request->query('category_id'))->value('code');
        }

        $query = Aukstructure::where('course_id', $course->id)
            ->select('id', 'parent_id', 'type', 'title', 'categories', 'position')
            ->orderByRaw('coalesce(position, id)');

        /*
         * P0.1: серверный прогресс — единственный источник истины.
         * Читаем прогресс пользователя по курсу из lesson_progress.
         * visited — множество просмотренных lesson_id,
         * percents — карта percent по lesson_id,
         * last_lesson_id / last_file — точка отклика чтения.
         */
        $userId = $request->user()?->id;
        $progress = [];
        if ($userId) {
            $progressRows = DB::table('lesson_progress')
                ->where('user_id', $userId)
                ->where('course_id', $course->id)
                ->get(['lesson_id', 'percent', 'last_file', 'last_anchor'])
                ->keyBy('lesson_id');
            foreach ($progressRows as $row) {
                $progress[(int) $row->lesson_id] = [
                    'percent' => (int) $row->percent,
                    'last_file' => $row->last_file,
                    'last_anchor' => $row->last_anchor,
                ];
            }
        }

        $stamp = DB::table('aukstructures')
            ->where('course_id', $course->id)
            ->max('updated_at');
        $tree = collect(Cache::remember(
            'player:tree:' . $course->id . ':' . $stamp,
            now()->addMinutes(10),
            fn () => $query->get()->all())
        );

        $visible = $code !== null
            ? $tree->filter(fn ($auk) => $auk->categories !== null
                && in_array(trim((string) $code), array_map('trim', explode(',', (string) $auk->categories)), true))
            : $tree;

        // entry: первый материал после разреза. Файла может не быть —
        // тогда entry равен null и фронтенд показывает пустой кадр, как раньше.
        $entry = null;
        $firstId = $visible->firstWhere('type', 3)?->id;

        if ($firstId) {
            $link = Link::where('aukstructure_id', $firstId)->value('link');
            $airpath = trim($course->aircraft->path);
            $aukspath = trim($course->path);

            $entry = [
                'aukstructure_id' => (int) $firstId,
                'aircraft' => $airpath,
                'auk' => $aukspath,
                'file' => (string) $link,
                'signed_base' => \App\Support\PrivateContentSigner::signedPath($airpath, $aukspath),
            ];
        }

        $favorites = DB::table('favorites')
            ->where('user_id', $request->user()?->id)
            ->get(['id', 'course_id', 'title']);

        // Собираем прогресс по видимым узлам дерева
        $progressByNode = [];
        foreach ($visible as $node) {
            $nodeId = (int) $node->id;
            if (isset($progress[$nodeId])) {
                $progressByNode[$nodeId] = $progress[$nodeId];
            } else {
                $progressByNode[$nodeId] = [
                    'percent' => 0,
                    'last_file' => null,
                    'last_anchor' => null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'path' => $course->path,
                    'aircraft_id' => $course->aircraft_id,
                    'aircraft_title' => $course->aircraft->title,
                ],
                'tree' => $visible->values()->map(fn ($auk) => [
                    'id' => $auk->id,
                    'parent_id' => $auk->parent_id,
                    'type' => $auk->type,
                    'title' => $auk->title,
                    'categories' => $auk->categories,
                    'position' => $auk->position,
                    // Поля прогресса — новые, читаются с сервера
                    'progress' => $progressByNode[$auk->id] ?? [
                        'percent' => 0,
                        'last_file' => null,
                        'last_anchor' => null,
                    ],
                ]),
                'favorites' => $favorites,
                'entry' => $entry,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    public function getlink(Request $request, $id)    {
        // Ссылка ведёт на файл материала, поэтому доступ к ней
        // ограничен так же, как к манифесту курса.
        $auk = Aukstructure::with(['course.aircraft'])->find($id);

        if (! $auk || ! $auk->course) {
            abort(404, 'Тема не найдена');
        }

        CourseAccess::authorizeOpen($request->user(), $auk->course);

        /*
         * Запись может относиться не к курсу, а к отдельным материалам
         * внутри него: группа отмечается галочками по узлам дерева, и
         * каждая отметка пишется своей строкой с parent_id.
         *
         * Обе проверки — и по материалу, и по специальности — живут в
         * CourseAccess. Спецразделение раньше проверялось отдельным
         * блоком прямо здесь, и две проверки доступа в двух местах
         * разъезжались: новая точка проверки могла забыть про одну из них.
         */
        CourseAccess::authorizeOpenModule($request->user(), $auk->course, (int) $id);

        $link = Link::where('aukstructure_id', $id)
            ->value('link');

        $course = $auk->course;   // уже загружен через with()
        $aircraft = $course->aircraft; // уже загружен через with()

        if (! $course || ! $aircraft) {
            return response()->json([
                'message' => 'Курс для Aukstructure не найден',
            ], 404);
        }

        $airpath = trim($aircraft->path);
        $aukspath = trim($course->path);

        /*
         * P1.2: fallback «у модуля без файла отдать файл первого
         * материала» убран — он делал полный выбор type=3 ради одного id
         * и подсовывал чужой файл. Файл — из Link узла или ничего:
         * фронтенд на пустой file показывает «файл не найден», а первый
         * модуль открывает entry из course-player.
         */
        $link = Link::where('aukstructure_id', $id)->value('link');

        return [
            'aircraft' => $airpath,
            'auk'      => $aukspath,
            'file'     => (string) $link,
        ];
    }

    public function get_first_auk(Request $request, $auk_id)
    {
        // Находка: find() возвращает null, обращение ->course_id давало 500.
        $cur_auk = Aukstructure::with('course')->find($auk_id);
        if (!$cur_auk) {
            return response()->json([
                'message' => 'Aukstructure не найден',
            ], 404);
        }
        // Отдаёт структуру курса, поэтому доступ такой же, как к
        // манифесту: записанному или управляющему.
        if ($cur_auk->course) {
            CourseAccess::authorizeOpen($request->user(), $cur_auk->course);
        }

        $cur_course_id = $cur_auk->course_id;
        $firstAukId = Aukstructure::where([
            ['course_id', '=', $cur_course_id],
            ['type', '=', 3],
            ['id', '>=', $auk_id]
        ])->orderBy('id')->first();
    
        return $firstAukId;
    }

    //-------------------------------------------------------
    
    
    // public function getlink($id)
    // {

    //     $link = (Link::where('aukstructure_id', $id)
    //         ->value('link')
    //         //->pluck('id')
    //         //->all()

    //     );
    //     $curCourse = Aukstructure::find($id)->where('type', 3)->firstOrFail();
    //     $curCourseId = $curCourse->id;

    //     $first = (Link::where('aukstructure_id', $curCourseId)
    //         ->value('link')
    //     );

    //     $aukstruct = Aukstructure::find($id);
    //     //$auks = Course::find($aukstruct->course_id);
    //     $auks = Course::where('id',  $aukstruct->course_id)
    //         ->first();
    //     //->value('path');
    //     $aircraft = Aircraft::find($auks->aircraft_id);
        
    //     $airpath = $aircraft->path;
    //     $aukspath = trim($auks->path);
    //     //dd($airpath);
        
    //     //$auks->path;
    //     // return json_encode($auks + $link);
    //     $url = Config::get('app.private_path') . $airpath . "/" . $aukspath . "/" . $link;
    //     $urlfirst = Config::get('app.private_path').$airpath ."/" .$aukspath."/".$first;
    //     //dd($aukspath,$airpath,$link,$urlfirst);
    //     // $response = [
    //     //     'url' => $url,
    //     //     'urlfirst' => $urlfirst,
    //     // ];
    //     //return $response;
    //     if($link!=null) 
    //     return $url;
    //     else
    //     return $urlfirst;
    // }

    public function showmanifest(Request $request, $id)
    {
        $course = Course::with(['categories','aircraft'])->findOrFail($id);

        // Манифест — это уже содержимое курса (путь к материалам и
        // структура тем), а не описание в каталоге.
        CourseAccess::authorizeOpen($request->user(), $course);
        // склейка пути        
        //         $courses_path = Config::get('app.courses_path'); // usr/local/share
        //         $aircraft_path = trim($course->aircraft->path); // Ил-76
        //         $auk_path=trim($course->path);
        //         $full_path = $courses_path . '/' . $aircraft_path . '/' . $auk_path . '/index.html';
        // //        $file = readfile($full_path, $use_include_path = true);
        //         //$file = readfile($full_path);
        //         $content = file_get_contents($full_path ); // читаем содержимое файла в строку, readfile читает в буфер           
        // // $content вставим функцию отправки файлов пунктов меню


        //         $response = [
        //               'content' => $content,
        //               'course' => $course,
        //               //'full_path' => $full_path, 
        //           ];
        //          return response($response, 201);

        return response()->json($course);
    }
    //-----------------------------рабочий вариант---------------------------------------------





    //public  function showsublink (Request $request) 
    // public  function showsublink () 
    //{
    //$course = request('file');
    //$coursesub = "/usr/local/share/courses/Ми-38/АУК-01/{$course}";

    //$content = file_get_contents($coursesub);
    //return $content;     
    //return $course;


    // }




    // public function show11($id)
    // {
    //     $courses_path = '/usr/local/share/courses';
    //     $class_path = 'Ил-76';
    //     $courses = Course::find($id);
    //     $courses->categories;
    //     $auk_path = $courses_path . '/' . $class_path . '/' . trim($courses->path);
    //     //$temp = $auk_path . '/index.html';
    //     //return $temp;
    //     $file = readfile($auk_path . '/index.html');
    //     $parse = $this->parser(file_get_contents($auk_path . '/index.html'));

    //     $response = [
    //         'file' => $file,
    //         'parse' => $parse,
    //         'type' => gettype($file)
    //     ];
    //     return response($response, 201);
    //     //return readfile($auk_path . '/index.html');
    // }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */


    public function update(Request $request, $id)
    {
        $request->validate($this->courseRules(partial: true));

        $courses = Course::findOrFail($id);
        $courses->fill($this->courseAttributes($request));
        $courses->save();

        if ($request->has('category_id')) {
            $courses->categories()->sync(
                $request->input('category_id') === null ? [] : [$request->input('category_id')]
            );
        }

        return response()->json($courses->fresh(), 200);
    }



    // public function update(Request $request, $id)
    // {        
    //     $courses = Course::find($id);

    //     $h_course = $courses->path_hash; 
    //     $o_course = $courses->path; 

    //     $courses->title = request('title');
    //     $courses->short_description = request('short_description');
    //     $courses->long_description = request('long_description');
    //     $courses->path = request('path');
    //     if($h_course == null)
    //     {
    //         //$hash = microtime();            
    //         $h_course = $this->get_hash_course();
    //         $this->copy_and_hash_him($o_course,$h_course);
    //     }
    //     //$courses->visible = $request->visible;
    //     $courses->save();
    //     $courses->categories()->sync($request->category_id);
    //     //return response($courses,201);        
    //     return response($courses, 201);
    //     //return "update ok, give my id";
    // }

    //---------------------рабочий-------------------------------------------

    // public function update(Request $request, $id)
    // {
    //     $courses = Course::find($id);

    //     $h_course = $courses->path_hash;
    //     $o_course = $courses->path;
    //     // вариант с папкой,лежащей в файловой системе уровнями выше
    //     //  $o_course_folder = Config::get('app.courses_path_orig') . '/' . $o_course;
    //     // меняем  Config::get('app.courses_path_orig') на '../../../курсы/courses_data'
    //     // к которому конкатенируем '/' и затем название папки из БД поля 'path'
    //     // $o_course_folder_  с отрезанием первого символа у пути делать не надо
    //     // !! для копирования файлов силами PHP ( не laravel) путь должен иметь вид 'abc/xyz' вместо не '/abc/xyz'


    //     $o_course_folder = Config::get('app.courses_path_orig') . '/' . $o_course;
    //     $hash = uniqid();
    //     $h_course_folder = Config::get('app.courses_path_hashed') . '/' . $hash;
    //     $o_course_folder_ = trim(substr($o_course_folder, 1));
    //     $h_course_folder_ = trim(substr($h_course_folder, 1));

    //     $courses->title = request('title');
    //     $courses->short_description = request('short_description');
    //     $courses->long_description = request('long_description');
    //     $courses->path = request('path');
    //     //if ($h_course_folder_ == null) {
    //     if ($h_course == null && !is_dir($h_course_folder_)) {
    //         //$hash = microtime();            
    //         //$ha_course = $this->get_hash_course();
    //         $this->recurseCopy($o_course_folder_, $h_course_folder_);
    //         $courses->path_hash = $hash;
    //         //$this->copy_and_hash_him($o_course,$ha_course);
    //     }
    //     //$courses->visible = $request->visible;
    //     $courses->save();
    //     $courses->categories()->sync($request->category_id);
    //     return response($courses, 201);
    // }
    //---------------------рабочий-------------------------------------------

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        // Путь хранилища строим только когда хеш реально задан: иначе
        // конфиг courses_path_hashed пуст и путь схлопывается в корень.
        $h_course = $course->path_hash;
        if (filled($h_course)) {
            $h_course_folder_ = trim(substr(
                Config::get('app.courses_path_hashed') . '/' . $h_course, 1
            ));
            $course->path_hash = null;
            if ($h_course_folder_ !== '' && is_dir($h_course_folder_)) {
                $this->recursiveRemoveDir($h_course_folder_);
            }
        }

        $course->delete();
        return response()->json(null, 200);
    }
    //----------------------------------------------------------
}

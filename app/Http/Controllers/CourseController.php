<?php

namespace App\Http\Controllers;

use App\Http\Filters\CourseFilter;
use App\Http\Filters\AukstructureFilter;
use App\Http\Requests\Course\FilterRequest;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Link;
use App\Models\Category;
use App\Models\Group;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required_without:name|string|max:255',
            'name' => 'required_without:title|string|max:255',
            'short_description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'description' => 'nullable|string',
            'path' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
            'status' => "nullable|in:draft,active,archived",
            'visible' => 'nullable|boolean',
        ]);

        // алиасы: name -> title, description -> long_description
        if (isset($validated['name']) && !isset($validated['title'])) {
            $validated['title'] = $validated['name'];
        }
        unset($validated['name']);
        if (isset($validated['description']) && empty($validated['long_description'])) {
            $validated['long_description'] = $validated['description'];
        }
        unset($validated['description']);

        $categoryId = $validated['category_id'] ?? null;

        $course = Course::create($validated);

        if ($categoryId) {
            // дублируем в pivot для совместимости со старыми связями
            $course->categories()->sync([$categoryId]);
        }

        return response()->json([
            'success' => true,
            'data' => $course->load('categories'),
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */



    //-----------------------------рабочий вариант---------------------------------------------
    public function show($id)
    {
        $course = Course::with(['categories','aircraft','aukstructures.links'])->findOrFail($id);

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


    public function getlink($id)
    {

        $link = (Link::where('aukstructure_id', $id)
            ->value('link')
        );
        // $curCourse = Aukstructure::find($id)->where('type', 3)->firstOrFail();
        // $curCourseId = $curCourse->id;
        $curCourse=Aukstructure::find($id);
        $curCourseId = $curCourse->course_id;

        //$aukstruct = Aukstructure::find($id);
        //$auks = Course::find($aukstruct->course_id);
        $auks = Course::where('id',  $curCourse->course_id)
            ->first();        
        $aircraft = Aircraft::find($auks->aircraft_id);        
        $airpath = $aircraft->path;
        $aukspath = trim($auks->path);
        //dd($airpath);
         $firstId = 
            (Aukstructure::where
            ([            
               ['course_id','=',$curCourseId],
               ['type','=',3] 
            ])
              //  ->value('id')        
              ->get()
              ->pluck('id')
              ->first()
            );

    $firstLink= (Link::where('aukstructure_id', $firstId)
    ->value('link')
);

        $url = Config::get('app.private_path') . $airpath . "/" . $aukspath . "/" . $link;
        $urlfirst = Config::get('app.private_path'). $airpath ."/" .$aukspath. "/" . $firstLink;
        //dd($id,$aukspath,$airpath,$link,$urlfirst,$firstLink,$curCourseId);
        // $response = [
        //     'url' => $url,
        //     'urlfirst' => $urlfirst,
        // ];
        //return $response;
        if($link!=null) 
        return $url;
        else
        return $urlfirst;
    }

    public function get_first_auk($auk_id)
    {
        $cur_course_id = Aukstructure::find($auk_id)->course_id;
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

    public function showmanifest($id)
    {
        $course = Course::find($id);
        $course->categories;
        $course->aircraft;
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
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'short_description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'description' => 'nullable|string',
            'path' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
            'status' => 'nullable|in:draft,active,archived',
            'visible' => 'nullable|boolean',
        ]);

        if (isset($validated['name']) && !isset($validated['title'])) {
            $validated['title'] = $validated['name'];
        }
        unset($validated['name']);
        if (array_key_exists('description', $validated)) {
            $validated['long_description'] = $validated['description'];
            unset($validated['description']);
        }

        $courses = Course::findOrFail($id);
        $categoryId = $validated['category_id'] ?? null;
        unset($validated['duration']);
        $courses->fill($validated);
        $courses->save();
        if ($categoryId !== null || $request->has('category_id')) {
            $courses->categories()->sync($request->input('category_id') ? [$request->input('category_id')] : []);
        }

        return response()->json([
            'success' => true,
            'data' => $courses->fresh(),
            'error' => null,
            'meta' => null,
        ], 200);
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

        $h_course = $course->path_hash;
        $h_course_folder = Config::get('app.courses_path_hashed') . '/' . $h_course;
        $h_course_folder_ = trim(substr($h_course_folder, 1));
        $course->path_hash = null;
        if (is_dir($h_course_folder_))
            $this->recursiveRemoveDir($h_course_folder_);
        $course->delete();
        return response()->json([
            'success' => true,
            'data' => null,
            'error' => null,
            'meta' => null,
        ], 200);
    }
    //----------------------------------------------------------
}

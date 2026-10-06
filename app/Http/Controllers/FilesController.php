<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
use App\Models\User;
use GrahamCampbell\ResultType\Success;
use Illuminate\Support\Facades\Log;

/**
 * Загрузка пользовательских файлов: POST /api/files/add.
 *
 * Валидирует массив image (до 20 файлов по 20 МБ), сохраняет в uploads/{name}
 * и пишет запись в таблицу files с типом по расширению. Остальные методы
 * на маршрутах не смонтированы.
 */
class FilesController extends Controller
{

  private $image_ext = ['jpg', 'jpeg', 'png', 'gif'];
  private $audio_ext = ['mp3', 'ogg', 'mpga'];
  private $video_ext = ['mp4', 'mpeg'];
  private $document_ext = ['doc', 'docx', 'pdf', 'odt'];

  /**
   * Constructor
   */
  //     public function __construct()
  // {
  //   $this->middleware('auth');
  // }
  //----------------------------------------------------------------------------------
  // старый
  public function show()
  {
    //return view('fileload');
  }

  public function upload(Request $request)
  {
    // Валидация добавлена вместе с проверкой прав на маршруте. Раньше
    // здесь не проверялось ничего: при отсутствии поля image цикл
    // foreach по null давал 500, а размер и тип файла не ограничивались.
    $validated = $request->validate([
        'image' => ['required', 'array', 'min:1', 'max:20'],
        'image.*' => ['required', 'file', 'max:20480'],
    ], [], ['image' => 'файл']);

    $model = new File();

    $files = $validated['image'];
    $id = auth()->user()->id; // id авторизованного пользователя
    $username = auth()->user()-> name; // name авторизованного пользователя
    
    $stored = [];

    foreach ($files as $file) {
       $filename = $file->getClientOriginalName(); //получаем оригинальное имя файла
       $file->storeAs('/uploads/'. $username, $filename); // загружаем файл в файловую систему с оригинальным именем в папку с именем пользователя
     
       $ext = $file->getClientOriginalExtension();
       $type = $this->getType($ext);    
       //$name = $file . time() . '.' . $file->extension();      
        

       $stored[] = File::create([
         'name' => $filename,
         'type' => $type,
         'extension' => $ext,
         'user_id' => Auth::id()
       ]);
     }

    // $filesArr = [
    //   [
    //     'name' => 'kjhksjd',
    //     'type' => 'ews',
    //     'extension' => 'kljh',
    //     'user_id' => 1,          
    //   ],
    //   [
    //     'name' => 'sdlfkj',
    //     'type' => 'jwk',
    //     'extension' => 'wiuy4',
    //     'user_id' => 2,          
    //   ],
    // ];
    // foreach ($filesArr as $item) {
    //   File::create($item);  
    // }

    
    // Ответ приходил голой строкой 'success', минуя конверт
    // ApiResponseEnvelope: unwrapResponse на фронте получал строку вместо
    // объекта и не мог разобрать результат.
    return response()->json([
        'success' => true,
        'data' => $stored,
        'error' => null,
        'meta' => null,
    ], 201);
  }



  //---------------------- для одиночных -------------------------------
  // public function upload(Request $request)
  // {
  //   $path = $request -> file('image') -> store ('/');        
  //     return view ('default', compact('path'));    
  // }
  // public function uploadMulti(Request $request)
  // {
    // foreach ($request->file('image') as $file) {
    //   $file->store('/');   
  // }
  //---------------------------------------------------------------------







  // конец старый 
  //----------------------------------------------------------------------------------
  // новые контроллеры для файлов
  public function index($type, $id = null)
  {
    $model = new File();

    if (!is_null($id)) {
      $response = $model::findOrFail($id);
    } else {
      $records_per_page = ($type == 'video') ? 6 : 15;

      $files = $model::where('type', $type)
        ->where('user_id', Auth::id())
        ->orderBy('id', 'desc')->paginate($records_per_page);

      $response = [
        'pagination' => [
          'total' => $files->total(),
          'per_page' => $files->perPage(),
          'current_page' => $files->currentPage(),
          'last_page' => $files->lastPage(),
          'from' => $files->firstItem(),
          'to' => $files->lastItem()
        ],
        'data' => $files
      ];
    }

    return response()->json($response);
  }

  /**
   * Upload new file and store it
   * @param Request $request Request with form data: filename and file info
   * @return boolean     True if success, otherwise - false
   */
  public function store(Request $request)
  {
    // $max_size = (int)ini_get('upload_max_filesize') * 1000;
    // $all_ext = implode(',', $this->allExtensions());

    // $this->validate($request, [
    //   'name' => 'required|unique:files',
    //   'file' => 'required|file|mimes:' . $all_ext . '|max:' . $max_size
    // ]);

    $model = new File();

    $file = $request->file('file');
    $ext = $request->$file->getClientOriginalExtension();
    $type = $this->getType($ext);

    if (Storage::putFileAs('/public/' . $this->getUserDir() . '/' . $type . '/', $file, $request['name'] . '.' . $ext)) {
      return $model::create([
        'name' => $request['name'],
        'type' => $type,
        'extension' => $ext,
        'user_id' => Auth::id()
      ]);
    }

    return response()->json(false);
  }

  /**
   * Edit specific file
   * @param integer $id   File Id
   * @param Request $request Request with form data: filename
   * @return boolean     True if success, otherwise - false
   */
  public function edit($id, Request $request)
  {
    $file = File::where('id', $id)->where('user_id', Auth::id())->first();

    if ($file->name == $request['name']) {
      return response()->json(false);
    }

    $this->validate($request, [
      'name' => 'required|unique:files'
    ]);

    $old_filename = '/public/' . $this->getUserDir() . '/' . $file->type . '/' . $file->name . '.' . $file->extension;
    $new_filename = '/public/' . $this->getUserDir() . '/' . $request['type'] . '/' . $request['name'] . '.' . $request['extension'];

    if (Storage::disk('local')->exists($old_filename)) {
      if (Storage::disk('local')->move($old_filename, $new_filename)) {
        $file->name = $request['name'];
        return response()->json($file->save());
      }
    }

    return response()->json(false);
  }


  /**
   * Delete file from disk and database
   * @param integer $id File Id
   * @return boolean   True if success, otherwise - false
   */
  public function destroy($id)
  {
    $file = File::findOrFail($id);

    if (Storage::disk('local')->exists('/public/' . $this->getUserDir() . '/' . $file->type . '/' . $file->name . '.' . $file->extension)) {
      if (Storage::disk('local')->delete('/public/' . $this->getUserDir() . '/' . $file->type . '/' . $file->name . '.' . $file->extension)) {
        return response()->json($file->delete());
      }
    }

    return response()->json(false);
  }


  /**
   * Get type by extension
   * @param string $ext Specific extension
   * @return string   Type
   */
  private function getType($ext)
  {
    if (in_array($ext, $this->image_ext)) {
      return 'image';
    }

    if (in_array($ext, $this->audio_ext)) {
      return 'audio';
    }

    if (in_array($ext, $this->video_ext)) {
      return 'video';
    }

    if (in_array($ext, $this->document_ext)) {
      return 'document';
    }
  }

  /**
   * Get all extensions
   * @return array Extensions of all file types
   */
  private function allExtensions()
  {
    return array_merge($this->image_ext, $this->audio_ext, $this->video_ext, $this->document_ext);
  }

  /**
   * Get directory for the specific user
   * @return string Specific user directory
   */
  private function getUserDir()
  {
    return Auth::user()->name . '_' . Auth::id();
  }

// удалить !!!!!!!!!!!
  public function test(Request $request)
  {      
      $user = Auth::user();
      //return 'thats ok test';
      return $user;
  }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

use App\Models\Role;
use App\Support\PrivateContent;
use App\Support\PrivateContentSigner;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
//use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Http\Controllers\SearchController;

class PrivateController extends Controller
{
  //  public function __construct()
  //  {         
  //      $this->middleware("auth:sanctum");//->except(['abc']);        
  //  }

  // protected function setUp()
  // {
  //     parent::setUp();
  //     $this->withoutMiddleware(
  //         ThrottleRequests::class
  //     );
  // }
 

  public function htmles00($aircraft, $auk)
  {
    $path = PrivateContent::safePath($aircraft, $auk, 'index.html');
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
    }
    abort(404);
  }

  /**
   * Отдаёт любой файл курса по подписанному пути.
   *
   * Маршрут: /private/{aircraft}/{auk}/{path}, где path =
   * "{expires}/{signature}/{файл}". Первые два сегмента — подпись,
   * их проверяет middleware ValidatePrivateContentSignature; остальное —
   * путь к файлу внутри каталога курса.
   *
   * Зачем единый метод вместо девяти htmles* с фиксированным числом
   * сегментов: глубина вложенности файлов у курсов разная, а подпись
   * в пути должна быть перед именем файла независимо от её длины.
   */
  public function htmlesPath($aircraft, $auk, $path)
  {
    $segments = explode('/', (string) $path);

    // Минимум: expires, signature, имя файла.
    if (count($segments) < 3) {
      return response("File not found", 404);
    }

    // Отбрасываем подпись — её уже проверил middleware.
    array_shift($segments);
    array_shift($segments);

    $file = implode('/', $segments);

    if ($file === '' || $file === 'index.html') {
      // index.html отдаём целиком, как раньше делал htmles00.
      $fullPath = PrivateContent::safePath($aircraft, $auk, 'index.html');
      $ext = pathinfo($fullPath)['extension'];
      $header_type = $this->get_mime_type($ext);

      if (Storage::disk('private')->exists($fullPath)) {
        $contents = Storage::disk('private')->get($fullPath);
        return response($contents, 200)->header("Content-Type", $header_type);
      }

      abort(404);
    }

    $fullPath = PrivateContent::safePath($aircraft, $auk, ...$segments);
    $ext = pathinfo($fullPath)['extension'];
    $header_type = $this->get_mime_type($ext);

    if (Storage::disk('private')->exists($fullPath)) {
      // ВАЖНО: readStream вызывается на диске 'private'. Вызов через
      // фасад Storage::readStream() брал диск по умолчанию (local),
      // файл не находился, возвращался null, и feof(null) ронял
      // ответ с TypeError 500.
      $handle = Storage::disk('private')->readStream($fullPath);

      return response()->stream(function () use ($handle) {
          while (!feof($handle)) {
              $buffer = fread($handle, 8192);
              ob_start();
              echo $buffer;
              ob_end_flush();
          }

          fclose($handle);
      }, 200, [
          "Content-Type" => $header_type
      ]);
    }

    return response("File not found", 404);
  }

// в этом контроллере пропускаем через фильтр статические файлы курсов,
// через них проходит и getlink()

//----------------------- оригинал--------------------

  // public function htmles($aircraft, $auk, $html)
  // {
  //   //$searchController = new SearchController();  

  //   if($html=='index.html') return;
  //   $path = PrivateContent::safePath($aircraft, $auk, $html);
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);
    
    
    
  //     if (Storage::disk('private')->exists($path)) {
  //     $contents = Storage::disk('private')->get($path);    
    
    
  //     // делаем подсветку
  //     //$searchTerm = "размеры";
      
  //     //$contents = $searchController->highlightWords($contents, $searchTerm);
  //     return response($contents, 200)->header("Content-Type", $header_type);

  //     //return Auth::user()->role;
  //     //$contents = Storage::disk('private')->get($path);    
  //     //return $contents;   

  //     //return View::make('courses', ['contents' => "$contents"]); 
  //     //return view('courses', $contents);
  //     //return View::make('test', ['cont' => $contents]);
  //     //return view('test', ['url_data' => $contents]);
  //     //return View::make('greeting', ['name' => 'James']);

  //   }
  //   abort(404);
  // }

  //----------------------- оригинал--------------------

  //----------------------- рабочий более менее--------------------


  public function htmles($aircraft, $auk, $html)
  {
      if ($html == 'index.html') {
          return;
      }
      
      $path = PrivateContent::safePath($aircraft, $auk, $html);
      $ext = pathinfo($path)['extension'];
      $header_type = $this->get_mime_type($ext);
  
      if (Storage::disk('private')->exists($path)) {
          // ВАЖНО: readStream вызывается на диске 'private'. Вызов через
          // фасад Storage::readStream() брал диск по умолчанию (local),
          // файл не находился, возвращался null, и feof(null) ронял
          // ответ с TypeError 500.
          $handle = Storage::disk('private')->readStream($path);
  
          return response()->stream(function () use ($handle) {
              while (!feof($handle)) {
                  $buffer = fread($handle, 8192);
                  ob_start();
                  echo $buffer;
                  ob_end_flush();
              }
  
              fclose($handle);
          }, 200, [
              "Content-Type" => $header_type
          ]);
      } else {
          return response("File not found", 404);
      }
  }
//----------------------- рабочий более менее--------------------


  // public function htmles($aircraft, $auk, $html)
  // {//// func 1 - передано 9.44, 4.8, 3.97, 6.04, 5.68      -8192
  //   //// func 1 - передано 9.54,       -4096
  //     if ($html == 'index.html') {
  //         return;
  //     }
  
  //     $path = PrivateContent::safePath($aircraft, $auk, $html);
  //     $ext = pathinfo($path)['extension'];
  //     $header_type = $this->get_mime_type($ext);
  
  //     if (!Storage::disk('private')->exists($path)) {
  //         return response("File not found", 404);
  //     }
  
  //     $stream = Storage::readStream($path);
  
  //     return response()->stream(function () use ($stream) {
  //         while (!feof($stream)) {
  //             echo fread($stream, 8192);
  //             flush();
  //         }
  //         fclose($stream);
  //     }, 200, [
  //         "Content-Type" => $header_type,
  //         "Content-Length" => Storage::size($path)
  //     ]);
  // }


//   public function htmles($aircraft, $auk, $html)
// {
//     if ($html == 'index.html') {
//         return;
//     }

//     $path = PrivateContent::safePath($aircraft, $auk, $html);
//     $ext = pathinfo($path)['extension'];
//     $header_type = $this->get_mime_type($ext);

//     if (!Storage::disk('private')->exists($path)) {
//         return response("File not found", 404);
//     }

//     $stream = Storage::readStream($path);

//     $file_size = Storage::size($path);
//    // $use_readfile = ($ext == 'svg' || $ext == 'png') && $file_size < (1024 * 1024); // Использовать readfile() для небольших файлов    
//    // $use_fpassthru = !$use_readfile && function_exists('fpassthru'); // Использовать fpassthru() если readfile() не подходит
//    $use_readfile = ($ext == 'js' || $ext == 'css') && $file_size < (1024 * 1024); // Использовать readfile() для небольших файлов    
//    $use_fpassthru = !$use_readfile && function_exists('fpassthru'); // Использовать fpassthru() если readfile() не подходит
//     $use_fread = !$use_readfile && !$use_fpassthru; // Использовать fread() если ни readfile(), ни fpassthru() не подходят

//     $headers = [
//         "Content-Type" => $header_type,
//         "Content-Length" => $file_size
//     ];

//     if ($use_readfile) {
//         return response()->file($path, $headers);
//     } elseif ($use_fpassthru) {
//         return response()->stream(function () use ($stream) {
//             fpassthru($stream);
//             fclose($stream);
//         }, 200, $headers);
//     } else {
//         return response()->stream(function () use ($stream) {
//             while (!feof($stream)) {
//                 echo fread($stream, 4096);
//                 flush();
//             }
//             fclose($stream);
//         }, 200, $headers);
//     }
// }


  public function htmles2($aircraft, $auk, $html, $html2)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);

    $opts = array(
      'http' => array(
        'method' => "GET",
        //"header" => "Content-Type: " . $header_type,
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
      //return $contents;                
    }
    abort(404);
  }

  public function htmles3($aircraft, $auk, $html, $html2, $html3)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2, $html3);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);


    $opts = array(
      'http' => array(
        'method' => "GET",
        // "header" => "Content-Type: " . $header_type,
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
      // $contents-> header('Content-Type', $header_type)   ;
      //return $contents;                
    }
    abort(404);
  }
  public function htmles4($aircraft, $auk, $html, $html2, $html3, $html4)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2, $html3, $html4);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    $opts = array(
      'http' => array(
        'method' => "GET",
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      //return $contents;                
      return response($contents, 200)->header("Content-Type", $header_type);
    }
    abort(404);
  }

  public function htmles5($aircraft, $auk, $html, $html2, $html3, $html4, $html5)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2, $html3, $html4, $html5);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    $opts = array(
      'http' => array(
        'method' => "GET",
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
      // return $contents;                
    }
    abort(404);
  }

  public function htmles6($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    $opts = array(
      'http' => array(
        'method' => "GET",
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
      // return $contents;                
    }
    abort(404);
  }


  public function htmles7($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6, $html7)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6, $html7);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    $opts = array(
      'http' => array(
        'method' => "GET",
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
      // return $contents;                
    }
    abort(404);
  }


  public function htmles8($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6, $html7, $html8)
  {
    $path = PrivateContent::safePath($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6, $html7, $html8);
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    $opts = array(
      'http' => array(
        'method' => "GET",
        'header' => "Accept-language: en\r\n" .
          "Cookie: foo=bar\r\n"
      )
    );
    $context = stream_context_create($opts);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      return response($contents, 200)->header("Content-Type", $header_type);
      // return $contents;                
    }
    abort(404);
  }

  /**
   * Выдаёт подписанный URL базового каталога курса.
   *
   * Фронтенд подставляет его в <base href>, и все относительные ресурсы
   * (CSS, JS, картинки) наследуют токен. Требует авторизации — иначе
   * подписанный URL можно было бы получить без входа в систему.
   */
  public function signedUrl(Request $request)
  {
    $aircraft = PrivateContent::sanitizeSegment((string) $request->query('aircraft', ''));
    $auk = PrivateContent::sanitizeSegment((string) $request->query('auk', ''));

    if ($aircraft === null || $auk === null) {
      return response()->json([
        'success' => false,
        'data'    => null,
        'error'   => 'Некорректный путь к курсу',
        'meta'    => null,
      ], 422);
    }

    // Отдаём подписанный префикс пути: фронтенд подставляет его в <base href>
    // и дописывает имя файла. Подпись в пути наследуется всеми относительными
    // ресурсами документа (CSS, JS, картинками).
    return response()->json([
        'success' => true,
        'data'    => [
            'base' => PrivateContentSigner::signedPath($aircraft, $auk),
        ],
        'error'   => null,
        'meta'    => null,
    ]);
  }

  public function images(Request $request, $html)
  {

    $path = PrivateContent::safePath($html);


    //return $contents;
    if (Storage::disk('private')->exists($path)) {
      $contents = Storage::disk('private')->get($path);
      //return $contents;
      return $contents;



      //return view::make('test', ['url_data' => $contents]);;
    }
    return 'no exist';
    //     abort (404);
    //$content = file_get_contents($path);
    // return $content;
    //   /return $path;


  }
  public function get_mime_type($ext)
  {

    $header_type = 'text/html';
    if ($ext == 'css') {
      $header_type = 'text/css';
    }
    if ($ext == 'js') {
      $header_type = 'text/javascript';
    }
    if ($ext == 'jpg') {
      $header_type = 'image/jpeg';
    }
    if ($ext == 'jpeg') {
      $header_type = 'image/jpeg';
    }
    if ($ext == 'png') {
      $header_type = 'image/png';
    }
    if ($ext == 'woff') {
      $header_type = 'font/woff';
    }
    if ($ext == 'ttf') {
      $header_type = 'font/ttf';
    }
    if ($ext == 'svg') {
      $header_type = 'image/svg+xml';
    }
    if ($ext == 'xml') {
      //$header_type = 'application/xml';
      return;
    }
    return  $header_type;
  }
}

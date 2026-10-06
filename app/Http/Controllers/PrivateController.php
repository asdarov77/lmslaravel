<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

use App\Models\Role;
use App\Support\PrivateContent;
use App\Support\ContentDelivery;
use App\Support\PrivateContentSigner;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
//use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Http\Controllers\SearchController;

/**
 * Отдача приватных файлов курса.
 *
 * htmlesPath обслуживает маршрут /api/private/{aircraft}/{auk}/{path} и
 * выбирает способ выдачи:
 *  - X-Accel-Redirect, если режим nginx И запрос пришёл через nginx
 *    (заголовок-метка из APP_KEY), см. ContentDelivery;
 *  - полный ответ для index.html;
 *  - поток через response()->stream() для вложенных ресурсов.
 *
 * Подпись проверяет middleware ValidatePrivateContentSignature, здесь она
 * снята с сегментов пути. signedUrl выдаёт подписанный префикс авторизованному
 * пользователю; получить его без входа нельзя.
 *
 * Методы htmles/htmles2..8/images/get_mime_type на маршрутах не смонтированы.
 */
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
    /*
     * Сегменты декодируются ДО обращения к диску.
     *
     * Реальные имена файлов курсов содержат пробелы и кириллицу
     * («1.1.10 Проверка датчика ПВД.html»), поэтому в подписанном URL
     * они приходят percent-encoded. Параметр маршрута доставался
     * закодированным, и поиск по диску искал файл с именем
     * «%D0%9F...» — такого нет, и любой материал с пробелом в имени
     * отдавал 404. На простых ASCII-именах этого не видно.
     */
    $segments = array_map(
        fn (string $segment): string => $this->decodeSegment($segment),
        explode('/', (string) $path)
    );

    // Минимум: expires, signature, имя файла.
    if (count($segments) < 3) {
      return response("File not found", 404);
    }

    // Отбрасываем подпись — её уже проверил middleware.
    array_shift($segments);
    array_shift($segments);

    $file = implode('/', $segments);

    // index.html идёт первым экземпляром документа, остальное — вложенные
    // ресурсы (стили, скрипты, картинки). Различие только в способе
    // чтения, путь строим одинаково.
    $relative = $file === '' || $file === 'index.html'
      ? PrivateContent::relativePath($aircraft, $auk, 'index.html')
      : PrivateContent::relativePath($aircraft, $auk, ...$segments);

    if ($relative === null) {
      // Недопустимый сегмент пути. 404, а не 500: путь пришёл от клиента,
      // и его неправильность — не ошибка приложения.
      return response("File not found", 404);
    }

    $fullPath = PrivateContent::PREFIX.'/'.$relative;

    if (! Storage::disk('private')->exists($fullPath)) {
      return response("File not found", 404);
    }

    $ext = pathinfo($fullPath)['extension'];
    $header_type = $this->get_mime_type($ext);

    if (ContentDelivery::isAccel(request())) {
      return $this->accelResponse($relative, $header_type, $fullPath);
    }

    if ($file === '' || $file === 'index.html') {
      // index.html отдаём целиком: он маленький, а поток для него —
      // лишняя сложность.
      return response(Storage::disk('private')->get($fullPath), 200)
        ->header("Content-Type", $header_type);
    }

    // ВАЖНО: readStream вызывается на диске 'private'. Вызов через
    // фасад Storage::readStream() брал диск по умолчанию (local),
    // файл не находился, возвращался null, и feof(null) ронял
    // ответ с TypeError 500.
    $handle = Storage::disk('private')->readStream($fullPath);

    return response()->stream(function () use ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);
    }, 200, [
        "Content-Type" => $header_type
    ]);
  }

  /**
   * Декодирует сегмент URL, только если он действительно закодирован.
   *
   * Различать нужно потому, что в имени файла может быть настоящий
   * символ процента («Отчёт 100%.html»): безусловный rawurldecode
   * испортил бы его.
   */
  protected function decodeSegment(string $segment): string
  {
    return preg_match('/%[0-9A-Fa-f]{2}/', $segment) === 1
      ? rawurldecode($segment)
      : $segment;
  }

  /**
   * Ответ с X-Accel-Redirect: тело пустое, файл отдаёт nginx.
   *
   * Вызывается ТОЛЬКО после проверки подписи (middleware
   * ValidatePrivateContentSignature) и только когда файл существует.
   * Значит, отдать файл мимо проверки через этот заголовок нельзя:
   * его получает лишь тот, кому подпись уже разрешила доступ.
   *
   * Тело намеренно пустое — nginx подменяет ответ целиком, вместе с
   * Content-Length и собственными заголовками сжатия. Content-Length
   * здесь ставить нельзя: при размере файла в приложении и на диске
   * разойдётся, и ответ зависнет на клиенте.
   */
  private function accelResponse(string $relative, string $mimeType, string $fullPath): BaseResponse
  {
    $segments = explode('/', $relative);
    $uri = PrivateContent::accelUri(...$segments);

    if ($uri === null) {
      // Путь не прошёл проверки безопасности: отдавать нечего.
      return response("File not found", 404);
    }

    $max = (int) config('private_content.accel_max_bytes', 0);
    $size = Storage::disk('private')->size($fullPath);

    if ($max > 0 && $size > $max) {
      // Файл крупнее лимита: X-Accel-Redirect не ограничивает отдачу, и
      // очень большой файл через internal-location упирается в sendfile
      // и таймауты. Отдаём потоком — пусть медленно, но верно.
      $handle = Storage::disk('private')->readStream($fullPath);

      return response()->stream(function () use ($handle) {
        while (!feof($handle)) {
          echo fread($handle, 8192);
        }

        fclose($handle);
      }, 200, ["Content-Type" => $mimeType]);
    }

    return response('', 200, [
      'X-Accel-Redirect' => $uri,
      'Content-Type' => $mimeType,
      // private обязателен: подпись одна на всех записанных на курс,
      // и общий кэш (CDN, Varnish) отдал бы файл тому, кто её не получал.
      'Cache-Control' => (string) config('private_content.cache_control', 'private, max-age=600'),
      'X-Content-Type-Options' => 'nosniff',
    ]);
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

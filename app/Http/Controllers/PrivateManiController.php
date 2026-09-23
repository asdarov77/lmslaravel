<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Role;
use App\Models\Link;
use App\Models\Category;
use App\Models\Course;
use App\Models\Aukstructure;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;


class PrivateManiController extends Controller
{

  public function RecurseXML($xml, $auktype, $parent_id, $course_id, $resources, $attrs_ident = '', $attrs_cat = '')
  {

    $child_count = 0;

    foreach ($xml as $key => $value) {

      if ($value && $value->attributes()['categories'] && $value->attributes()['identifierref']) {
        $attrs_ident = ((string)$value->attributes()['identifierref']);
        $attrs_cat = (string)($value->attributes()['categories']);
      }
      $child_count++;

      if ($this->RecurseXML($value, $auktype, $parent_id, $course_id, $resources, $attrs_ident, $attrs_cat) == 0) // не осталось детей

      {
        $description = ['0' => 'название', '1' => 'тема', '2' => 'раздел', '3' => 'модуль'];
        $el = Aukstructure::updateOrCreate(
          [
            'title' => (string)$value,
            'parent_id' => (int)$parent_id,
            'course_id' => (int)$course_id,
          ],
          [
            'type' => $auktype,
            'description' => $description[$auktype],
            'categories' => $attrs_cat,
            'identifier' => $attrs_ident           
          ]
        );
        $parent_id = $el->id;

        if ($auktype == 3 && $resources[$attrs_ident]) //для данного xml где 3-х уровневая и линки только на 3-м уровне
        {
          foreach ($resources[$attrs_ident] as $_links) {
            Link::updateOrCreate(
              [
                'link' => (string)$_links,
                'aukstructure_id' => (int)$parent_id
              ],
            );
          }
        }


        if ($attrs_cat) {
          $curCat = explode(",", $attrs_cat);

          foreach ($curCat as $_curCat) {

            $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
              ->get()
              ->pluck('id')
              ->all()
            );

            DB::table('aukstructure_category')->updateOrInsert(
              [
                'category_id' => (int)implode('', $curCatId),
                'aukstructure_id' => (int)$parent_id,
              ],
            );
          }
        }

        $auktype++;
      }
    }
    return $child_count;
  }



  public function xmles00($aircraft, $auk)
  {
    $path = "private/{$aircraft}/{$auk}/imsmanifest.xml";
    $ext = pathinfo($path)['extension'];
    $header_type = $this->get_mime_type($ext);
    if (Storage::exists($path)) {
      $contents = Storage::get($path);
      // сюда вставляем функцию парсинга xml файла, $contents - string      
      $menuxmlcontent = $this->parsemanifest($contents);
      return response(json_encode($menuxmlcontent, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200)->header("Content-Type", $header_type);
    }
    abort(404);
  }


  // public function parsemanifest($contents)
  // {
  //   $xml = simplexml_load_string($contents);
  //   $categories = []; // пишем категории в БД
  //   $resources = []; // вспомогательный массив ресурсы,для поиска и последующей записи файлов от модуля    
  //   $t = [];

  //   //--------------------------------------заполнение ресурсов-------
  //   foreach ($xml->resources->resource as $key => $c) {
  //     $temp = [];
  //     foreach ($c->file as $cc) {
  //       $filename = (string)$cc->attributes()['href'];
  //       $ext = substr($filename, -4);
  //       if ($ext == 'html') array_push($temp, (string)$cc->attributes()['href']);
  //     }
  //     $k = (string)$c->attributes()['identifier'];
  //     $v = $temp;
  //     $t = [];
  //     $t[$k] = $v;
  //     $resources += $t;
  //   }
  //   //return $resources;
  //   //--------------------------------------конец заполнения ресурсов-------

  //   // заполнение категорий в БД
  //   foreach ($xml->course->category as $key => $c) {
  //     $_t = [];
  //     $_t['name'] = (string)$c->attributes()['name'];
  //     $_t['shortname'] = (string)$c->attributes()['shortname'];
  //     // $_t['theme_categories'] = (string)$c->attributes()['categories'];
  //     Category::updateOrCreate(
  //       ['title' => (string)$c->attributes()['name']],
  //       [
  //         'code' => (string)$c->attributes()['shortname'],
  //         'description' => 'test'
  //       ]
  //     );
  //     array_push($categories, $_t);
  //   }
  //   //--------------------------------------конец заполнения категорий-------
  //   $curAuk  = Course::updateOrCreate(
  //     [
  //       'title' => $xml->organizations->organization->title,
  //       'aircraft_id' => 1 // потом сюда будем передавать реальный aircraft_id
  //     ],
  //     [
  //       'short_description' => 'тестовый short_description',
  //       'long_description' => 'тестовый long_description'
  //     ]
  //   );
  //   $startnode = $xml->organizations->organization;
  //   $auktype = 0;
  //   $parent_id = 0;
  //   $course_id = 17; // пока временно

  //   $this->RecurseXML($startnode, $auktype, $parent_id, $course_id, $resources);





    // foreach ($xml->organizations->organization->item as $key => $c) {
    //   $_t = [];

    //   $curThemeAuk  = Aukstructure::updateOrCreate(
    //     ['title' => (string)$c->title],
    //     [
    //       'parent_id' => (int)0,
    //       'course_id' => (int)$curAuk->id,
    //       'type' => (int)1,
    //       'description' => 'тема'
    //     ]
    //   );
    //   $curCatString = (string)$c->attributes()['categories'];
    //   $curCat = explode(",", $curCatString);

    //   foreach ($curCat as $_curCat) {
    //     $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
    //       ->get()
    //       ->pluck('id')
    //       ->all());

    //     DB::table('aukstructure_category')->updateOrInsert(
    //       [
    //         'category_id' => (int)implode('', $curCatId),
    //         'aukstructure_id' => $curThemeAuk->id,
    //       ],
    //     );
    //   }


    //   //==================
    //   //------------------
    //   foreach ($c->item as $key => $cc) {

    //     $curRazdelAuk = Aukstructure::updateOrCreate(
    //       ['title' => (string)$cc->title],
    //       [
    //         'course_id' => (int)$curAuk->id,
    //         'parent_id' => (int)$curThemeAuk->id,
    //         'type' => (int)2,
    //         'description' => 'раздел'
    //       ]
    //     );

    //     //-----------//-----------//-----------
    //     $curCatString = (string)$cc->attributes()['categories'];
    //     $curCat = explode(",", $curCatString);
    //     foreach ($curCat as $_curCat) {

    //       $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
    //         ->get()
    //         ->pluck('id')
    //         ->all());

    //       DB::table('aukstructure_category')->updateOrInsert(
    //         [
    //           'category_id' => (int)implode('', $curCatId),
    //           'aukstructure_id' => $curRazdelAuk->id,
    //         ],
    //       );
    //     }
    //     //-----------//-----------//-----------

    //     //-----------------------
    //     $ttt = (int)$curRazdelAuk->id;
    //     foreach ($cc->item as $key => $ccc) {
    //       $ident = (string)$ccc->attributes()['identifierref'];

    //       $curModuleAuk = Aukstructure::updateOrCreate(
    //         [
    //           'title' => (string)$ccc->title,
    //           'parent_id' => (int)$ttt
    //         ],
    //         [
    //           'course_id' => (int)$curAuk->id,
    //           'type' => (int)3,
    //           'description' => 'модуль'
    //         ]
    //       );

    //       //-----------//-----------//-----------
    //       $curCatString = (string)$ccc->attributes()['categories'];
    //       $curCat = explode(",", $curCatString);
    //       $te=[];$m=[];
    //       foreach ($curCat as $_curCat) {

    //         $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
    //           ->get()
    //           ->pluck('id')
    //           ->all());
    //           $m['aukstructure_id']=$curModuleAuk->id;              
    //           $m['category_id']= (int)implode('', $curCatId);
    //         $te +=$m;
    //         DB::table('aukstructure_category')->updateOrInsert(
    //           [
    //             'category_id' => (int)implode('', $curCatId),
    //             'aukstructure_id' => $curModuleAuk->id,
    //           ],
    //         );print_r($te);
    //       }
    //       //-----------//-----------//-----------


    //       foreach ($resources[$ident] as $_ident) {
    //         Link::updateOrCreate(
    //           [
    //             'link' => (string)$_ident,
    //             'aukstructure_id' => (int)$curModuleAuk->id
    //           ],
    //         );
    //       }
    //     }
    //   }
    // }
  //}




  //---------------------------------------------------------------------




  // public function htmles($aircraft, $auk, $html)
  // {
  //   if($html=='index.html') return;
  //   $path = "private/{$aircraft}/{$auk}/{$html}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);

  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);    
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     //return Auth::user()->role;
  //     //$contents = Storage::get($path);    
  //     //return $contents;   

  //     //return View::make('courses', ['contents' => "$contents"]); 
  //     //return view('courses', $contents);
  //     //return View::make('test', ['cont' => $contents]);
  //     //return view('test', ['url_data' => $contents]);
  //     //return View::make('greeting', ['name' => 'James']);

  //   }
  //   abort(404);
  // }


  // public function htmles2($aircraft, $auk, $html, $html2)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);

  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       //"header" => "Content-Type: " . $header_type,
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     //return $contents;                
  //   }
  //   abort(404);
  // }

  // public function htmles3($aircraft, $auk, $html, $html2, $html3)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}/{$html3}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);


  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       // "header" => "Content-Type: " . $header_type,
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     // $contents-> header('Content-Type', $header_type)   ;
  //     //return $contents;                
  //   }
  //   abort(404);
  // }
  // public function htmles4($aircraft, $auk, $html, $html2, $html3, $html4)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}/{$html3}/{$html4}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);
  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     //return $contents;                
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //   }
  //   abort(404);
  // }

  // public function htmles5($aircraft, $auk, $html, $html2, $html3, $html4, $html5)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}/{$html3}/{$html4}/{$html5}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);
  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     // return $contents;                
  //   }
  //   abort(404);
  // }

  // public function htmles6($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}/{$html3}/{$html4}/{$html5}/{$html6}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);
  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     // return $contents;                
  //   }
  //   abort(404);
  // }


  // public function htmles7($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6, $html7)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}/{$html3}/{$html4}/{$html5}/{$html6}/{$html7}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);
  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     // return $contents;                
  //   }
  //   abort(404);
  // }


  // public function htmles8($aircraft, $auk, $html, $html2, $html3, $html4, $html5, $html6, $html7, $html8)
  // {
  //   $path = "private/{$aircraft}/{$auk}/{$html}/{$html2}/{$html3}/{$html4}/{$html5}/{$html6}/{$html7}/{$html8}";
  //   $ext = pathinfo($path)['extension'];
  //   $header_type = $this->get_mime_type($ext);
  //   $opts = array(
  //     'http' => array(
  //       'method' => "GET",
  //       'header' => "Accept-language: en\r\n" .
  //         "Cookie: foo=bar\r\n"
  //     )
  //   );
  //   $context = stream_context_create($opts);


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     return response($contents, 200)->header("Content-Type", $header_type);
  //     // return $contents;                
  //   }
  //   abort(404);
  // }

  // public function images(Request $request, $html)
  // {

  //   $path = "private/{$html}";


  //   //return $contents;
  //   if (Storage::exists($path)) {
  //     $contents = Storage::get($path);
  //     //return $contents;
  //     return $contents;



  //     //return view::make('test', ['url_data' => $contents]);;
  //   }
  //   return 'no exist';
  //   //     abort (404);
  //   //$content = file_get_contents($path);
  //   // return $content;
  //   //   /return $path;


  // }
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
      $header_type = 'application/xml';
      //return;
    }
    return  $header_type;
  }
}





 

//----------------------------------------------------------------------------

//---------------------------------------без рекурсии-------------------------------

// public function parsemanifest($contents)
// {
//   $xml = simplexml_load_string($contents);
//   $categories = []; // пишем категории в БД
//   $resources = []; // вспомогательный массив ресурсы,для поиска и последующей записи файлов от модуля    
//   $t = [];

//   //--------------------------------------заполнение ресурсов-------
//   foreach ($xml->resources->resource as $key => $c) {
//     $temp = [];
//     foreach ($c->file as $cc) {
//       array_push($temp, (string)$cc->attributes()['href']);
//     }
//     $k = (string)$c->attributes()['identifier'];
//     $v = $temp;
//     $t = [];
//     $t[$k] = $v;
//     $resources += $t;
//   }
//   //return $resources;
//   //--------------------------------------конец заполнения ресурсов-------

//   // заполнение категорий в БД
//   foreach ($xml->course->category as $key => $c) {
//     $_t = [];
//     $_t['name'] = (string)$c->attributes()['name'];
//     $_t['shortname'] = (string)$c->attributes()['shortname'];
//     // $_t['theme_categories'] = (string)$c->attributes()['categories'];
//     Category::updateOrCreate(
//       ['title' => (string)$c->attributes()['name']],
//       [
//         'code' => (string)$c->attributes()['shortname'],
//         'description' => 'test'
//       ]
//     );
//     array_push($categories, $_t);
//   }
//   //--------------------------------------конец заполнения категорий-------
//   $curAuk  = Course::updateOrCreate(
//     [
//       'title' => $xml->organizations->organization->title,
//       'aircraft_id' => 1 // потом сюда будем передавать реальный aircraft_id
//     ],
//     [
//       'short_description' => 'тестовый short_description',
//       'long_description' => 'тестовый long_description'
//     ]
//   );
//   foreach ($xml->organizations->organization->item as $key => $c) {
//     $_t = [];

//     $curThemeAuk  = Aukstructure::updateOrCreate(
//       ['title' => (string)$c->title],
//       [
//         'parent_id' => (int)0,
//         'course_id' => (int)$curAuk->id,
//         'type' => (int)1,
//         'description' => 'тема'
//       ]
//     );
//     $curCatString = (string)$c->attributes()['categories'];
//     $curCat = explode(",", $curCatString);

//     foreach ($curCat as $_curCat) {
//       $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
//         ->get()
//         ->pluck('id')
//         ->all());

//       DB::table('aukstructure_category')->updateOrInsert(
//         [
//           'category_id' => (int)implode('', $curCatId),
//           'aukstructure_id' => $curThemeAuk->id,
//         ],
//       );
//     }
    

//     //==================
//     //------------------
//     foreach ($c->item as $key => $cc) {

//       $curRazdelAuk = Aukstructure::updateOrCreate(
//         ['title' => (string)$cc->title],
//         [
//           'course_id' => (int)$curAuk->id,
//           'parent_id' => (int)$curThemeAuk->id,
//           'type' => (int)2,
//           'description' => 'раздел'
//         ]
//       );

//       //-----------//-----------//-----------
//       $curCatString = (string)$cc->attributes()['categories'];
//       $curCat = explode(",", $curCatString);
//       foreach ($curCat as $_curCat) {

//         $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
//           ->get()
//           ->pluck('id')
//           ->all());

//         DB::table('aukstructure_category')->updateOrInsert(
//           [
//             'category_id' => (int)implode('', $curCatId),
//             'aukstructure_id' => $curRazdelAuk->id,
//           ],
//         );
//       }
//       //-----------//-----------//-----------

//       //-----------------------
//       $ttt = (int)$curRazdelAuk->id;
//       foreach ($cc->item as $key => $ccc) {
//         $ident = (string)$ccc->attributes()['identifierref'];

//         $curModuleAuk = Aukstructure::updateOrCreate(
//           [
//             'title' => (string)$ccc->title,
//             'parent_id' => (int)$ttt
//           ],
//           [
//             'course_id' => (int)$curAuk->id,
//             'type' => (int)3,
//             'description' => 'модуль'
//           ]
//         );

//         //-----------//-----------//-----------
//         $curCatString = (string)$ccc->attributes()['categories'];
//         $curCat = explode(",", $curCatString);
//         $te=[];$m=[];
//         foreach ($curCat as $_curCat) {

//           $curCatId = (Category::where('code', 'like', '%' . $_curCat . '%')
//             ->get()
//             ->pluck('id')
//             ->all());
//             $m['aukstructure_id']=$curModuleAuk->id;              
//             $m['category_id']= (int)implode('', $curCatId);
//           $te +=$m;
//           DB::table('aukstructure_category')->updateOrInsert(
//             [
//               'category_id' => (int)implode('', $curCatId),
//               'aukstructure_id' => $curModuleAuk->id,
//             ],
//           );print_r($te);
//         }
//         //-----------//-----------//-----------


//         foreach ($resources[$ident] as $_ident) {
//           Link::updateOrCreate(
//             [
//               'link' => (string)$_ident,
//               'aukstructure_id' => (int)$curModuleAuk->id
//             ],
//           );
//         }
//       }
//     }
//   }
// }
<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use App\Models\Course;
use App\Models\Link;


class AircraftController extends Controller
{
  // делаем ссылку на другой контроллер (GiftController)
  protected $giftController;

  public function __construct(GiftController $giftController)
  {
    $this->giftController = $giftController;
  }
  // -----end-----------------------------

  public function showclassesfs()
  {

    $courses_path = Config::get('app.courses_path');
    //$courses_path = Config::get('app.private_path');     
    $classes = array();
    if (file_exists($courses_path)) {
      $items = array_diff(scandir($courses_path), array('..', '.'));
     // $classes = array_diff(scandir($courses_path), array('..', '.'));
      // берем только папки,отсекаем файлы
      foreach ($items as $item) {
        if (is_dir($courses_path . '/' . $item)) {
          $classes[] = $item;
        }
      }
    }   
    return array_values($classes);
  }

  public function showauks(string $air)
  {
    $courses_path = Config::get('app.courses_path');
    $full_path = $courses_path . '/' . $air;
    $auks = array();
    if (file_exists($full_path)) {
      $auks = array_diff(scandir($full_path), array('..', '.'));
    }
    return $auks;
  }

  public function indexclasses()
  {
    $aircrafts = Aircraft::orderBy('id')->get();
    foreach ($aircrafts as $_aircrafts)
      $_aircrafts->courses;
    //$aircrafts = DB::table('aircrafts')->orderBy('id')->get('title');        
    return $aircrafts;
  }



  //
  // дообавление классов (Ил-76,Ми-38, etc...)
  //


  public function storeclasses(Request $request)
  {
    if (DB::table('aircrafts')->where('path', (string)request('path'))->exists()) return; // выбрасываем если такой класс уже существует                          

    $aircraft = new Aircraft();
    $aircraft->title = request('title');
    $aircraft->path = request('path');
    $aircraft->save();
    $aircraft_id = $aircraft->id;

    $coursesArr = array_values($this->showauks($aircraft->path));

    // Добавление записей
    $_coursesArr = [];
    foreach ($coursesArr as $item) { // $interests array contains input data
      $auk = new Course();
      //$auk->path = $aircraft->path . '/' . $item; // запись вида Ми-38/АУК-01
      $auk->path =  $item; // запись вида АУК-01
      $full_path_manifest = Config::get('app.courses_path') . '/' . $aircraft->path . '/' . $item . '/' . 'imsmanifest.xml';
      //$full_path_manifest = Config::get('app.private_path') . '/' .$aircraft->path.'/'. $item.'/' . 'imsmanifest.xml';

      //--------------privatemanicontroller insert data from manifest new---------------
      // пока оставляем как есть, для фасада storage пути другие !
      $pathmanifest = "private/{$aircraft->path}/{$item}/imsmanifest.xml";
      //dd($pathmanifest);
      if (Storage::exists($pathmanifest)) {
        $contents = Storage::get($pathmanifest);
        // сюда вставляем функцию парсинга xml файла, $contents - string      
        $menuxmlcontent = $this->parsemanifest($contents, $aircraft_id, $item); // объект                            
        //dd($menuxmlcontent);
      }
      //--------------end privatemanicontroller insert data from manifest new---------------


      //                        
      //---------------------------------ЗАГРУЗКА ВОПРОСОВ-----------------------------
      //

      //получаем список файлов, по умолчанию лежат внутри АУК в папке GIFT

      $full_path_gifts = Config::get('app.courses_path') . '/' . $aircraft->path . '/' . $item . '/' . 'GIFT';

      $gifts = array();
      if (file_exists($full_path_gifts)) {
        $gifts = array_diff(scandir($full_path_gifts), array('..', '.'));
      }

      if ($gifts) {
        foreach ($gifts as $gift) {
          $this->giftController->store(Config::get('app.courses_path') . $aircraft->path . '/' . $item . '/' . 'GIFT/' . $gift);
        }
      }

      //                        
      //---------------------------------end ЗАГРУЗКА ВОПРОСОВ-----------------------------
      //
    }


    return $aircraft;
  }


  public function parsemanifest($contents, $aircraft_id, $auk)
  {
    $xml = simplexml_load_string($contents);
    $categories = []; // пишем категории в БД
    $resources = []; // вспомогательный массив ресурсы,для поиска и последующей записи файлов от модуля    
    $t = [];
    //--------------------------------------заполнение ресурсов-------
    foreach ($xml->resources->resource as $key => $c) {
      $temp = [];
      foreach ($c->file as $cc) {
        $filename = (string)$cc->attributes()['href'];
        $ext = substr($filename, -4);
        if ($ext == 'html') array_push($temp, (string)$cc->attributes()['href']);
      }
      $k = (string)$c->attributes()['identifier'];
      $v = $temp;
      $t = [];
      $t[$k] = $v;
      $resources += $t;
    }
    //return $resources;
    //--------------------------------------конец заполнения ресурсов-------

    // заполнение категорий в БД
    foreach ($xml->course->category as $key => $c) {
      $_t = [];
      $_t['name'] = (string)$c->attributes()['name'];
      $_t['shortname'] = (string)$c->attributes()['shortname'];

      Category::updateOrCreate(
        [
          'title' => (string)$c->attributes()['name'],
          'aircraft_id' => $aircraft_id
        ],
        [
          'code' => (string)$c->attributes()['shortname'],
          'description' => 'test',
        ]
      );
      array_push($categories, $_t);
    }

    //--------------------------------------конец заполнения категорий-------
    $curAuk  = Course::updateOrCreate(
      [
        'title' => $xml->organizations->organization->title,
        'aircraft_id' => $aircraft_id // потом сюда будем передавать реальный aircraft_id
      ],
      [
        'short_description' => 'тестовый short_description',
        'long_description' => 'тестовый long_description',
        'path' => $auk
      ]
    );

    $startnode = $xml->organizations->organization;
    $auktype = 0;
    $parent_id = 0;
    $course_id = $curAuk->id;
    $this->RecurseXML($startnode, $auktype, $parent_id, $course_id, $resources, $categories, $aircraft_id);
  }

  public function RecurseXML($xml, $auktype, $parent_id, $course_id, $resources, $categories, $aircraft_id, $attrs_ident = '', $attrs_cat = '')
  {

    $child_count = 0;

    foreach ($xml as $key => $value) {

      if ($value && $value->attributes()['categories'] && $value->attributes()['identifierref']) {
        $attrs_ident = ((string)$value->attributes()['identifierref']);
        $attrs_cat = (string)($value->attributes()['categories']);
      }
      $child_count++;

      if ($this->RecurseXML($value, $auktype, $parent_id, $course_id, $resources, $categories, $aircraft_id, $attrs_ident, $attrs_cat) == 0) // не осталось детей

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
            $curCatId = (Category::where([
              ['code', '=', $_curCat],
              ['aircraft_id', '=', $aircraft_id]
            ])
              //$curCatId = (Category::where('code',$_curCat) //where('aircraft_id', $aircraft_id)
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

            DB::table('category_course')->updateOrInsert(
              [
                'category_id' => (int)implode('', $curCatId),
                'course_id' => (int)$course_id,
              ],
            );
          }
        }

        $auktype++;
      }
    }
    return $child_count;
  }
}

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
use App\Support\PrivateContent;
use Illuminate\Support\Str;


class AircraftController extends Controller
{
  /**
   * Каталоги внутри контента, которые НЕ являются самолётами или АУК:
   * вопросы GIFT, исходники (orig), статика SCORM (app) и т.п.
   */
  private const SERVICE_DIRECTORIES = ['GIFT', 'orig', 'app', 'eDoc', 'imscp', 'imsmanifest.xml', 'js', 'css', 'models'];

  // делаем ссылку на другой контроллер (GiftController)
  protected $giftController;

  public function __construct(GiftController $giftController)
  {
    $this->giftController = $giftController;
  }
  // -----end-----------------------------

  /**
   * Список папок-классов (самолётов) в каталоге контента.
   *
   * Раньше в результат попадали и файлы, и служебные папки: импортёр
   * принимал их за самолёты и создавал лишние записи в БД.
   */
  public function showclassesfs()
  {
    $courses_path = rtrim((string) Config::get('app.courses_path'), '/');
    $classes = array();

    if (is_dir($courses_path)) {
      foreach (scandir($courses_path) as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
          continue;
        }
        if (! is_dir($courses_path . '/' . $item)) {
          continue;
        }
        if (in_array($item, self::SERVICE_DIRECTORIES, true)) {
          continue;
        }
        $classes[] = $item;
      }
    }

    sort($classes);

    return array_values($classes);
  }

  /**
   * Список АУК (папок-курсов) внутри самолёта.
   *
   * Возвращаются ТОЛЬКО подкаталоги. Раньше возвращался любой элемент
   * scandir(), поэтому для КЛЕН в список попадала папка GIFT и импортёр
   * пытался создать фиктивный курс «GIFT».
   */
  public function showauks(string $air)
  {
    $air = PrivateContent::sanitizeSegment($air);
    $courses_path = rtrim((string) Config::get('app.courses_path'), '/');
    $full_path = $air === null ? '' : $courses_path . '/' . $air;
    $auks = array();

    if ($full_path !== '' && is_dir($full_path)) {
      foreach (scandir($full_path) as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
          continue;
        }
        if (! is_dir($full_path . '/' . $item)) {
          continue;
        }
        if (in_array($item, self::SERVICE_DIRECTORIES, true)) {
          continue;
        }
        $auks[] = $item;
      }
    }

    sort($auks);

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


  /**
   * Приводит ввод к строке.
   *
   * Нужен для комбобоксов Vuetify: выбранный элемент может прийти объектом
   * ({ text, value }, { title, name }) или массивом, а не строкой. Без
   * нормализации валидация 'string' отклоняла запрос с 422, хотя значение
   * было выбрано в интерфейсе.
   */
  private function normalizeStringInput(mixed $value): ?string
  {
    if (is_string($value)) {
      return trim($value) === '' ? null : trim($value);
    }

    if (is_array($value)) {
      // Массив вида ['БПЛА'] или ['text' => ..., 'value' => ...].
      $value = $value[0] ?? $value['value'] ?? $value['text'] ?? $value['title'] ?? $value['name'] ?? null;

      return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    if (is_object($value)) {
      $value = $value->value ?? $value->text ?? $value->title ?? $value->name ?? null;

      return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    return null;
  }

  /**
   * Импортирует самолёт и все его АУК из каталога контента в БД.
   *
   * Источник: config('app.courses_path') (= storage/app/public/private).
   * Для каждой папки-АУК читается imsmanifest.xml, из которого создаются
   * категории, курс, структура АУК и ссылки на материалы; затем
   * подтягиваются вопросы из папки GIFT.
   *
   * Раньше здесь:
   *  - объект Course создавался (`new Course()`), но никогда не
   *    сохранялся — мёртвый код, из-за которого казалось, что курс создан;
   *  - импорт шёл без транзакции, и падение на середине оставляло в БД
   *    самолёт с half-загруженными АУК;
   *  - повторный импорт молча возвращал пустой ответ без объяснения;
   *  - пути склеивались конкатенацией и разваливались, если courses_path
   *    задан без завершающего слэша.
   */
  public function storeclasses(Request $request)
  {
    // Нормализуем ввод ДО валидации. Vuetify-комбобокс может прислать
    // выбранный элемент объектом ({ text, value } или { title }), а не
    // строкой — тогда валидация 'string' падала с 422 «The path must be
    // a string», хотя пользователь явно выбрал класс в списке.
    $title = $this->normalizeStringInput($request->input('title'));
    $path = $this->normalizeStringInput($request->input('path'));

    $validated = validator([
      'title' => $title,
      'path' => $path,
    ], [
      'title' => 'required|string|max:255',
      'path'  => 'required|string|max:255',
    ])->validate();

    $path = PrivateContent::sanitizeSegment($validated['path']);

    if ($path === null) {
      return response()->json([
        'success' => false,
        'data'    => null,
        'error'   => 'Некорректное имя класса (каталога)',
        'meta'    => null,
      ], 422);
    }

    if (DB::table('aircrafts')->where('path', $path)->exists()) {
      // Раньше здесь был голый `return;` — клиент получал пустой 200
      // и не понимал, импортировано ли что-то. Теперь понятная ошибка.
      return response()->json([
        'success' => false,
        'data'    => null,
        'error'   => "Класс «{$validated['title']}» уже импортирован",
        'meta'    => null,
      ], 409);
    }

    $coursesPath = rtrim((string) Config::get('app.courses_path'), '/');
    $aircraftDir = $coursesPath . '/' . $path;

    if (! is_dir($aircraftDir)) {
      return response()->json([
        'success' => false,
        'data'    => null,
        'error'   => "Каталог «{$path}» не найден в {$coursesPath}",
        'meta'    => null,
      ], 422);
    }

    $auks = $this->showauks($path);
    $summary = ['auk' => [], 'gift_files_parsed' => 0, 'skipped' => []];

    // Транзакция: не оставляем в БД половину импортированного самолёта.
    DB::transaction(function () use ($validated, $path, $auks, $coursesPath, &$summary) {
      $aircraft = Aircraft::create([
        'title' => $validated['title'],
        'path'  => $path,
      ]);

      $aircraftId = $aircraft->id;

      foreach ($auks as $item) {
        $manifestPath = PrivateContent::buildPath([$path, $item, 'imsmanifest.xml']);

        if ($manifestPath === null || ! Storage::disk('private')->exists($manifestPath)) {
          $summary['skipped'][] = "{$path}/{$item} (нет imsmanifest.xml)";
          continue;
        }

        $contents = Storage::disk('private')->get($manifestPath);
        $this->parsemanifest($contents, $aircraftId, $item);
        $summary['auk'][] = $item;

        // Вопросы лежат в папке GIFT внутри АУК.
        // ВАЖНО: GiftController::store() только разбирает GIFT в HTML и
        // ВОЗВРАЩАЕТ результат — в таблицу questions ничего не пишет
        // (вопросы заводятся через POST /api/questions). Поэтому здесь
        // только проверяем, что файлы читаются, и считаем их разобранными.
        $giftsDir = $coursesPath . '/' . $path . '/' . $item . '/GIFT';

        if (! is_dir($giftsDir)) {
          continue;
        }

        foreach (scandir($giftsDir) as $gift) {
          if ($gift === '.' || $gift === '..') {
            continue;
          }
          $giftFile = $giftsDir . '/' . $gift;

          if (! is_file($giftFile)) {
            continue;
          }

          $this->giftController->store($giftFile);
          $summary['gift_files_parsed']++;
        }
      }
    });

    return response()->json([
      'success' => true,
      'data'    => Aircraft::with('courses')->find(
        Aircraft::where('path', $path)->value('id')
      ),
      'error'   => null,
      'meta'    => $summary,
    ], 201);
  }


  public function parsemanifest($contents, $aircraft_id, $auk)
  {
    // Битый/невалидный XML раньше приводил к ErrorException из
    // simplexml_load_string() и 500. Теперь это понятная ошибка уровня 422.
    $xml = @simplexml_load_string($contents);

    if ($xml === false) {
      throw new \RuntimeException("АУК «{$auk}»: не удалось разобрать imsmanifest.xml");
    }

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
      $attrs = $c->attributes();

      $name = trim((string) ($attrs['name'] ?? ''));

      if ($name === '') {
        continue;
      }

      // shortname есть не во всех манифестах (у КЛЕН его нет вообще).
      // Раньше кодом становилась пустая строка, все категории самолёта
      // получали code = '' и больше не различались — см. фикс связывания
      // категорий в RecurseXML().
      $shortname = trim((string) ($attrs['shortname'] ?? ''));

      if ($shortname === '') {
        // Стабильный код из названия: устраняет коллизии и остаётся
        // предсказуемым при повторном импорте.
        $shortname = PrivateContent::sanitizeSegment(
          Str::ascii($name)
        );
        $shortname = $shortname === null ? null : substr(preg_replace('/\s+/', '_', $shortname), 0, 64);
      }

      Category::updateOrCreate(
        [
          'title' => $name,
          'aircraft_id' => $aircraft_id,
        ],
        [
          'code' => $shortname,
          'description' => 'test',
        ]
      );

      array_push($categories, ['name' => $name, 'shortname' => (string) $shortname]);
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
            $_curCat = trim((string) $_curCat);

            if ($_curCat === '') {
              continue;
            }

            $categoryIds = Category::where('code', $_curCat)
              ->where('aircraft_id', '=', $aircraft_id)
              ->pluck('id')
              ->all();

            // Раньше: (int)implode('', $curCatId). При нескольких найденных
            // категориях implode СКЛЕИВАЛ их id в одно число
            // (например [21, 26] => 2126), и INSERT в aukstructure_category
            // падал с нарушением внешнего ключа. При отсутствии совпадений
            // получался category_id = 0 — тоже нарушение FK.
            // Теперь связь создаётся по КАЖДОМУ найденному id, а при
            // отсутствии совпадений просто пропускается.
            if ($categoryIds === []) {
              continue;
            }

            foreach ($categoryIds as $categoryId) {
              DB::table('aukstructure_category')->updateOrInsert(
                [
                  'category_id' => (int) $categoryId,
                  'aukstructure_id' => (int) $parent_id,
                ],
              );

              DB::table('category_course')->updateOrInsert(
                [
                  'category_id' => (int) $categoryId,
                  'course_id' => (int) $course_id,
                ],
              );
            }
          }
        }

        $auktype++;
      }
    }
    return $child_count;
  }
}

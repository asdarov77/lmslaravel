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


/**
 * Папки-классы и импорт самолёта.
 *
 * Списки папок читаются из config('app.courses_path') с отсевом служебных
 * каталогов (GIFT, orig, app…). Импорт строит Aircraft, Course, Aukstructure,
 * Link, Category и pivot-таблицы из imsmanifest.xml В ОДНОЙ ТРАНЗАКЦИИ —
 * иначе половина импортированного самолёта осталась бы без модулей.
 *
 * Манифест может быть выгружен в двух вариантах схемы (SCORM 1.2 / 2004):
 * категории лежат либо внутри <course>, либо прямыми детьми <manifest> —
 * см. parsemanifest().
 */
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
   *
   * Формат ответа — массив строк (имена папок): его читают v-for на
   * фронте и тесты, поэтому расширять его объектами нельзя. Полезная
   * мета (число АУК, наличие манифеста, размер) отдаётся отдельным
   * эндпоинтом classesfsmeta(): страница /classes показывает по ней
   * карточки-галерею, а не голый combobox «в пустоту».
   */
  public function showclassesfs()
  {
    return array_values(array_column($this->classesFsDetail(), 'name'));
  }

  /**
   * Расширенная сводка по папкам-классам для карточек страницы /classes.
   *
   * Для каждой папки: name — имя (идентификатор импорта), auk_count —
   * число потенциальных АУК (подкаталоги без служебных), manifests —
   * сколько из них содержат imsmanifest.xml (импортируются), size_bytes —
   * суммарный размер содержимого (для порядка карточек и подписи),
   * imported — есть ли уже запись в aircrafts.
   *
   * Обход только на два уровня вглубь: полный обход боевого каталога
   * (5+ ГБ, тысячи файлов) был бы слишком дорог для обычного GET.
   */
  public function classesFsDetail(): array
  {
    $courses_path = rtrim((string) Config::get('app.courses_path'), '/');
    $detail = array();

    if (! is_dir($courses_path)) {
      return $detail;
    }

    $importedPaths = DB::table('aircrafts')->pluck('path')->flip()->all();

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

      $aukCount = 0;
      $withManifest = 0;
      $sizeBytes = 0;

      foreach (scandir($courses_path . '/' . $item) as $sub) {
        if ($sub === '.' || $sub === '..' || str_starts_with($sub, '.')) {
          continue;
        }

        $subPath = $courses_path . '/' . $item . '/' . $sub;

        if (is_file($subPath)) {
          $sizeBytes += (int) @filesize($subPath);
          continue;
        }

        if (! is_dir($subPath) || in_array($sub, self::SERVICE_DIRECTORIES, true)) {
          continue;
        }

        $aukCount++;

        if (is_file($subPath . '/imsmanifest.xml')) {
          $withManifest++;
        }

        // Оценка размера АУК: только файлы первого уровня. Полный
        // рекурсивный обход многогигабайтных каталогов контента не нужен.
        foreach (scandir($subPath) as $file) {
          $filePath = $subPath . '/' . $file;

          if ($file === '.' || $file === '..' || ! is_file($filePath)) {
            continue;
          }

          $sizeBytes += (int) @filesize($filePath);
        }
      }

      $detail[] = [
        'name' => $item,
        'auk_count' => $aukCount,
        'manifests' => $withManifest,
        'size_bytes' => $sizeBytes,
        'imported' => isset($importedPaths[$item]),
      ];
    }

    usort($detail, static function (array $a, array $b): int {
      return strcasecmp($a['name'], $b['name']);
    });

    return $detail;
  }

  /**
   * GET /api/classesfsmeta — сводка по папкам-классам для карточек
   * страницы /classes.
   *
   * Ответ проходит через middleware ApiResponseEnvelope, поэтому здесь
   * возвращается «голые» данные: конверт { success, data, error, meta }
   * формирует middleware, у базового Controller нет метода
   * successResponse().
   */
  public function showclassesfsmeta(): array
  {
    return $this->classesFsDetail();
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
   * Источник: config('app.courses_path')
   * (по умолчанию storage/app/courses/private — за пределами public/, иначе
   * материал открывается через symlink storage:link мимо проверки подписи).
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
        // Диск 'private' корнем имеет РОДИТЕЛЯ courses_path (см.
        // config/filesystems.php), поэтому пути к его файлам всегда
        // строятся через PrivateContent::buildPath() — с префиксом
        // 'private/'. Раньше здесь использовалась «сырая» склейка
        // "$path/$item/imsmanifest.xml" без префикса: exists() смотрела в
        // несуществующий <courses>/../<класс>/... и каждый АУК уходил в
        // skipped — импорт завершался успешно, но курсов в БД не
        // появлялось («нажимаю и ничего не происходит»).
        $manifestPath = PrivateContent::buildPath([$path, $item, 'imsmanifest.xml']);

        if ($manifestPath === null || ! Storage::disk('private')->exists($manifestPath)) {
          $summary['skipped'][] = "{$path}/{$item} (нет imsmanifest.xml)";
          continue;
        }

        $contents = Storage::disk('private')->get($manifestPath);

        try {
          $this->parsemanifest($contents, $aircraftId, $item);
        } catch (\RuntimeException $e) {
          // Битый манифест одного АУК не должен обрывать импорт всего
          // класса: раньше RuntimeException выходил из замыкания,
          // транзакция откатывалась целиком, а Laravel превращал его в
          // 500 (в логах — пустой ответ и «ничего не произошло»).
          // Теперь АУК помечается пропущенным с текстом ошибки.
          $summary['skipped'][] = "{$path}/{$item}: {$e->getMessage()}";
          continue;
        }

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
    // LIBXML_NOWLOAD: манифесты ссылаются на XSD-схемы
    // (xsi:schemaLocation="… imscp_v1p1.xsd …"), которые при импорте
    // недоступны — не пытаемся их скачать. Предупреждения libxml глушим
    // внутренним буфером, но настоящий сбой разбора ($xml === false)
    // превращается в RuntimeException ниже.
    $previous = libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOWLOAD | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

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
    // <category> ищем по всему документу: в части манифестов они лежат
    // внутри <course>, в других (SCORM 2004 3rd Edition) — прямыми детьми
    // <manifest>. xpath одинаково находит оба варианта.
    foreach ($xml->xpath('//category') ?: [] as $key => $c) {
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

    // импорт перестроил дерево и ссылки — кэш дерева плеера неактуален
    \App\Services\CoursePlayerService::invalidateTreeCache((int) $course_id);
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

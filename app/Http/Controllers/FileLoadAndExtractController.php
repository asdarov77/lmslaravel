<?php
// Контроллер для загрузки и распаковки архивного файла,используется вместе с компонентом
// FileUploader.vue,который вызывается в AddClass.vue.
// пока не используется. Возможно будет другой вариант загрузки курсов
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Загрузка zip курса и его распаковка.
 *
 * Архив кладётся на диск private, затем ZipArchive распаковывает его в
 * config('app.courses_path') — тот же путь, который читают AircraftController
 * (списки папок) и nginx (internal-location). Отсюда правило: место хранения
 * меняется одной настройкой, а не в трёх местах кода.
 */
class FileLoadAndExtractController extends Controller
{
    public function upload(Request $request)
{
    $file = $request->file('file');
    $filePath = $file->store('private');

    return response()->json([
        'filePath' => $filePath
    ]);
}

    public function extract(Request $request)
{
    $filePath = $request->input('filePath');

    // Архив лежит на диске 'private' по пути, который вернул upload(),
    // поэтому абсолютный путь собирается из корня ЭТОГО диска, а не
    // через storage_path(). Иначе после переноса контента за пределы
    // public/ распаковка писала бы мимо courses_path.
    $archive = Storage::disk('private')->path($filePath);

    if (! is_file($archive)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Архив не найден на диске'
        ], 404);
    }

    $zip = new ZipArchive;

    if ($zip->open($archive) !== true) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to extract file'
        ], 500);
    }

    // Каталог назначения — строго из конфига. Раньше здесь стоял
    // storage_path('app/public/private/') мимо config('app.courses_path'),
    // и после переноса материала распаковка молча создавала вторую
    // копию курсов там, откуда их уже никто не читает.
    $target = rtrim((string) config('app.courses_path'), '/');

    if (! is_dir($target) && ! @mkdir($target, 0775, true) && ! is_dir($target)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Не удалось создать каталог для материала: ' . $target
        ], 500);
    }

    $zip->extractTo($target);
    $zip->close();

    return response()->json([
        'status' => 'success',
        'path' => $target
    ]);
}

}

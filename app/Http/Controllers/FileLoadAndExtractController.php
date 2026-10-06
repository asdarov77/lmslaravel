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
    //$filePath ='TqFYliSLXKsq6mZ62x44mMqNgTuv6eJiNLbvjMZS.zip';
        $path = storage_path('app/public/'. $filePath);
        echo($path);
    $dir = base_path();
    echo($dir);
    // Extract the contents of the ZIP archive
     $zip = new ZipArchive;
    if ($zip->open($path) === TRUE) {
        //$zip->extractTo($dir);
        $zip->extractTo(storage_path('app/public/private/'));
        $zip->close();
    } else {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to extract file'
        ], 500);
    }

    return response()->json([
        'status' => 'success'
    ]);
}

}

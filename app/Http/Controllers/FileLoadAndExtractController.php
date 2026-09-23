<?php
// Контроллер для загрузки и распаковки архивного файла,используется вместе с компонентом
// FileUploader.vue,который вызывается в AddClass.vue.
// пока не используется. Возможно будет другой вариант загрузки курсов
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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

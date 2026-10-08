<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */
    // выставил в .env FYLESYSTEM_DRIVER = public,или по умолчанию будет брать default из значения выше
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
// Диск с контентом курсов (АУК).
// КРИТИЧНО: root схлопывался в storage_path('app/'.env('PRIVATE_PATH')), а
// переменной PRIVATE_PATH в .env нет — env() возвращал null, и root был
// равен storage/app/. Контент же лежит по courses_path (storage/app/courses/private/),
// поэтому root и есть его родитель.
// В итоге Storage::exists('private/<самолёт>/<АУК>/imsmanifest.xml') и
// отдача index.html всегда смотрели в несуществующий storage/app/private/
// и возвращали 404: ни импорт курсов в БД, ни просмотр материалов не работали.
        'private' => [
            'driver' => 'local',
            // root = родитель app.courses_path, потому что все пути в коде
            // начинаются с 'private/...' (private/<самолёт>/<АУК>/<файл>).
            // Источник истины — config('app.courses_path'), чтобы не было
            // двух независимых определений одного пути.
            'root' => rtrim(dirname(rtrim((string) config('app.courses_path'), '/')), '/'),
            // visibility private, а не public: файлы контента пишутся
            // файлами, которые веб-сервер не должен отдавать напрямую.
            // Доступ к материалу — только через подписанный путь
            // /api/private/... с проверкой HMAC в middleware.
            //
            // url здесь был '/storage/private'. Он не использовался кодом,
            // но вводил в заблуждение: каталог контента лежит внутри
            // storage/app/public, поэтому команда `php artisan storage:link`
            // (обычный шаг деплоя) создаёт public/storage -> storage/app/public
            // и открывает весь материал по адресу /storage/private/<...>
            // БЕЗ проверки подписи. То есть одна команда деплоя отключала
            // всю защиту. url убран: для этого диска его быть не должно.
            'visibility' => 'private',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        //public_path('../../docs/lc/storage') => storage_path('app/public'),
    ],

];

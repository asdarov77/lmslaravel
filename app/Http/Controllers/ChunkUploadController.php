<?php

namespace App\Http\Controllers;

use App\Support\FileManager\ChunkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Загрузка больших файлов по частям.
 *
 * Отдельный контроллер от FileManagerController намеренно: у него
 * другая форма запроса (тело запроса — это данные, а не JSON), другой
 * код ответа и другая природа отказов. Смешанные в одном методе
 * проверки «это JSON или это тело?» читались бы хуже, чем два файла.
 *
 * Тело части принимается как есть: Content-Type application/octet-stream
 * и ничего больше. multipart для частей не нужен — у него своя
 * разметка, а частей на файл сотни, и разметка съела бы заметную долю
 * трафика при загрузке крупного файла по плохому каналу.
 *
 * Права — на маршрутах. Как и в FileManagerController, всё сводится к
 * user_id: чужой upload_id выглядит как несуществующий.
 */
class ChunkUploadController extends Controller
{
    public function __construct(private readonly ChunkUploadService $uploads)
    {
    }

    /**
     * Начать или продолжить загрузку.
     *
     * POST /api/filemanager/uploads/init
     *   { filename, size, mime?, last_modified?, folder_id? }
     */
    public function init(Request $request): JsonResponse
    {
        $this->validate($request, [
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:0'],
            'mime' => ['nullable', 'string', 'max:120'],
            'last_modified' => ['nullable', 'integer', 'min:0'],
            'folder_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->uploads->init($request->user(), $request->input())
        );
    }

    /**
     * Принять одну часть.
     *
     * POST /api/filemanager/uploads/{upload}/chunk?index=N
     *   тело: application/octet-stream
     *
     * Проверка длины части живёт в сервисе: она знает chunk_bytes и
     * объявленный размер, а контроллер знает только то, что пришло.
     */
    public function chunk(Request $request, string $upload): JsonResponse
    {
        $index = $request->query('index');

        $this->validate($request, [
            'index' => ['required', 'integer', 'min:0'],
        ]);

        $body = $request->getContent();

        // Пустое тело при непустой части означает не «пользователь
        // ничего не прислал», а то, что тело не дошло: его отсёк
        // post_max_size в PHP или client_max_body_size в nginx. Разница
        // в сообщении важна, потому что лечится это настройкой сервера,
        // а не повтором нажатия.
        if ($body === '' || $body === false) {
            $body = $this->readFromInputStream();
        }

        if ($body === '' || $body === false) {
            return response()->json([
                'message' => 'Тело запроса не дошло до приложения. Проверьте post_max_size в PHP и client_max_body_size в nginx.',
            ], 413);
        }

        return response()->json($this->uploads->chunk(
            $request->user(),
            $upload,
            (int) $index,
            $body
        ));
    }

    /**
     * Собрать файл из частей.
     *
     * POST /api/filemanager/uploads/{upload}/complete
     */
    public function complete(Request $request, string $upload): JsonResponse
    {
        return response()->json(
            $this->uploads->complete($request->user(), $upload)->toManagerArray(),
            201
        );
    }

    /**
     * Отменить загрузку.
     *
     * DELETE /api/filemanager/uploads/{upload}
     *
     * Ответ 204 даже если такой загрузки нет: клиенту не важно, была
     * ли она, его задача — освободить очередь. Ошибкой это делать
     * нельзя: после перезагрузки страницы сессия могла уже истечь, и
     * интерфейс показывал бы «ошибка отмены» при вполне успешной
     * отмене.
     */
    public function destroy(Request $request, string $upload): \Symfony\Component\HttpFoundation\Response
    {
        $this->uploads->abort($request->user(), $upload);

        return response()->noContent();
    }

    /**
     * Тело из php://input.
     *
     * Запасной путь на случай, когда Symfony уже прочитал поток (например
     * при подключённых отладочных middleware). Основной путь —
     * Request::getContent().
     */
    private function readFromInputStream(): string|false
    {
        $handle = @fopen('php://input', 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }
}

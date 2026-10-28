<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Support\ContentDelivery;
use App\Support\CourseAccess;
use App\Support\PrivateContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Отдача содержимого материала курса для плеера (iframe).
 *
 * Закрывает дефекты:
 *  Д1 — ответ всегда «no-cache»: прокси/браузер не хранят документ, в котором
 *       живёт <base href=".../{expires}/{signature}/">. Подпись пересобирается
 *       при каждой выдаче, поэтому «голый/битый» кадр после истечения TTL
 *       больше невозможен; фронт кэширует только метаданные, не html.
 *  Д5 — ?highlight=term подсвечивается на сервере (тот же DOM, что отдаётся),
 *       а не XPath-мутацией на фронте после sanitize — подсветка и счётчик
 *       ▲▼ больше не разъезжаются.
 *  Безопасность — приватные файлы courses теперь требуют авторизацию
 *       (раньше /api/private/* был полностью открыт).
 */
class CourseContentController extends Controller
{
    /** Класс span'а подсветки — совпадает с CSS фронтенда (.cp-hl). */
    public const HIGHLIGHT_CLASS = 'cp-hl';

    public function __construct()
    {
        // Контракт как у CoursePlayerController: sanctum-токен либо web-сессия.
        $this->middleware(function ($request, $next) {
            if (!$this->authenticated($request)) {
                abort(401, 'Требуется авторизация');
            }

            return $next($request);
        });
    }

    /**
     * Авторизация запроса кадра. iframe не может отправить заголовок
     * Authorization, поэтому допускается токен в query ?t= (sanctum).
     */
    private function authenticated(Request $request): bool
    {
        if (Auth::guard('sanctum')->check() || Auth::check()) {
            return true;
        }

        $token = (string) $request->query('t', '');
        if ($token === '') {
            return false;
        }

        // сверяем хэш предъявленного токена с сохранённым (id|secret формат Sanctum)
        $tokenId = explode('|', $token)[0] ?? null;
        if (!$tokenId || !ctype_digit($tokenId)) {
            return false;
        }

        $row = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        if (!$row || $row->revoked_at !== null) {
            return false;
        }
        if ($row->expires_at !== null && strtotime($row->expires_at) < time()) {
            return false;
        }

        return hash_equals($row->token, hash('sha256', $token));
    }

    /**
     * GET /api/course/{courseId}/content/{itemId}?highlight=term
     *
     * Возвращает HTML материала по id узла АУК (а не по сырому пути —
     * путь строится сервером, обход каталогов невозможен).
     */
    public function show(Request $request, int $courseId, int $itemId)
    {
        $item = Aukstructure::query()
            ->where('id', $itemId)
            ->where('course_id', $courseId)
            ->with('links')
            ->firstOrFail();

        $link = optional($item->links->first())->link;
        if (!$link) {
            abort(404, 'Материал не найден');
        }

        $course = Course::with('aircraft')->findOrFail($courseId);

        // Д12: авторизация доступа к СОДЕРЖИМОМУ. Раньше endpoint проверял
        // только факт входа, а CourseAccess (запись на курс / специальность /
        // отмеченные модули) жил лишь в CourseController::getlink. Любой
        // вошедший, знающий id, читал чужой курс — ровно тот обход, который
        // закрыли при появлении витрины. Теперь правило одно для всех точек.
        // Порядок важен: сначала доступ (403 для незаписанных — контракт
        // CourseContentAccessTest), затем существование файла (404).
        $user = $request->user() ?? Auth::user();
        CourseAccess::authorizeOpenModule($user, $course, $item->id);

        // Д11 (главная причина «плеер работает некорректно»): контент АУК лежит
        // на диске 'private' (root = courses_path, см. config/filesystems.php).
        // Disk 'public' указывает в storage/app/public — там этих файлов никогда
        // не было, и любой материал отдавал 404 («битый кадр»). Все чтения контента
        // идут только через диск 'private'. Путь строится через PrivateContent:
        // единый источник формата private/<самолёт>/<курс>/<файл> с валидацией
        // сегментов (traversal/NUL отсекаются внутри buildPath → null).
        $relative = PrivateContent::relativePath(
            optional($course->aircraft)->path,
            $course->path,
            $link
        );

        if ($relative === null) {
            abort(403, 'Некорректная ссылка материала');
        }

        $disk = Storage::disk('private');
        if (!$disk->exists($relative) || !$disk->isFile($relative)) {
            abort(404, 'Файл материала отсутствует');
        }

        $contents = $disk->get($relative);
        // sanitizация: приватный контент РЭТ не должен тянуть за собой JS
        $contents = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $contents);
        $contents = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $contents);

        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if ($ext === 'html' || $ext === 'xhtml' || $ext === 'htm') {
            $term = trim((string) $request->input('highlight', ''));
            if (mb_strlen($term) >= 3) {
                $contents = $this->highlightInHtml($contents, $term);
            }
            // Д1 + base-URL: подставляем <base href> так, чтобы относительные
            // ресурсы документа (картинки сканов, css) резолвились через тот же
            // авторизованный endpoint. Блокирующий запрос за base делает только
            // сам браузер внутри iframe — PHP-FPM не участвует (P0.4), а
            // Cache-Control: no-store гарантирует отсутствие протухших подписей (Д1).
            $base = $this->baseHref($courseId, $link);
            $contents = $this->injectBase($contents, $base);
            // Д1: документы с подписанными <base href> не кэшируются нигде,
            // кроме текущей сессии просмотра.
            return response($contents, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, max-age=0',
            ]);
        }

        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        // P1 (производительность): бинарный материал (pdf/сканы) — самый
        // тяжёлый трафик плеера. В режиме nginx файл отдаёт сам nginx через
        // X-Accel-Redirect, PHP-FPM воркер освобождается мгновенно; здесь
        // авторизация уже выполнена выше, поэтому internal-location безопасен.
        return $this->accelOrStream($request, $disk, $relative, $mime);
    } // end show()

    /**
     * GET /api/course/{courseId}/raw/{path} — раздача вложенных ресурсов
     * документа (css/js/изображения), на которые указывает <base href>.
     * Путь собираем из приватного каталога курса; traversal отсекаем.
     */
    public function raw(Request $request, int $courseId, string $path)
    {
        $course = Course::with('aircraft')->findOrFail($courseId);

        // Д12: raw-ресурсы — часть содержимого курса. Раньше endpoint только
        // проверял вход, и любой пользователь, угадавший путь, читал css/сканы
        // чужого курса (обход CourseAccess через <base href>).
        $user = $request->user() ?? Auth::user();
        CourseAccess::authorizeOpen($user, $course);

        // Путь собираем через PrivateContent — тот же формат и та же валидация
        // сегментов, что у /api/private (traversal/NUL/незваные символы → null).
        $relative = PrivateContent::relativePath(
            optional($course->aircraft)->path,
            $course->path,
            ...explode('/', str_replace('\\', '/', $path))
        );

        if ($relative === null) {
            abort(403, 'Некорректный путь ресурса');
        }

        // Контент АУК лежит на диске 'private' (root = courses_path).
        // 'public' здесь давал 404 на всех вложенных ресурсах (css/картинки сканов).
        $disk = Storage::disk('private');
        abort_unless($disk->exists($relative) && $disk->isFile($relative), 404);

        // Страховка от symlink-выхода за root курса: реальный путь обязан
        // оставаться внутри courses_path.
        $full = $disk->path($relative);
        $root = realpath(dirname($disk->path(''))) ?: $disk->path('');
        $real = realpath($full);
        if ($real === false || !str_starts_with($real, rtrim($root, '/') . '/')) {
            abort(403);
        }

        // P1: вложенные ресурсы скана (картинки) — десятки запросов на кадр.
        // В режиме nginx их отдаёт nginx; PHP остаётся только проверка доступа.
        return $this->accelOrStream($request, $disk, $relative, null);
    }

    /**
     * Выдача бинарного файла: X-Accel-Redirect (nginx) либо поток (PHP).
     *
     * Тот же алгоритм, что у PrivateController::accelResponse, но без
     * HMAC-подписи: доступ здесь уже проверен CourseAccess по авторизации
     * sanctum/web, поэтому internal-location получает только путь после 403/404.
     * accelUri дополнительно сверяет realpath с корнем контента (symlink-страховка).
     */
    private function accelOrStream(Request $request, \Illuminate\Contracts\Filesystem\Filesystem $disk, string $relative, ?string $mime)
    {
        if (ContentDelivery::isAccel($request)) {
            $uri = PrivateContent::accelUri(...explode('/', $relative));

            if ($uri !== null) {
                $max = (int) config('private_content.accel_max_bytes', 0);
                $size = $max > 0 ? $disk->size($relative) : 0;

                if ($max === 0 || $size <= $max) {
                    $headers = [
                        'X-Accel-Redirect' => $uri,
                        // private: подписанный ответ одного обучаемого не должен
                        // попасть в общий кэш (CDN/Varnish) тому, кто права не имеет.
                        'Cache-Control' => 'private, max-age=600',
                        'X-Content-Type-Options' => 'nosniff',
                    ];
                    if ($mime !== null) {
                        $headers['Content-Type'] = $mime;
                    }

                    return response('', 200, $headers);
                }
                // Крупнее лимита — fallback на поток ниже: X-Accel не ограничивает
                // отдачу и большой файл через internal-location упирается в таймауты.
            }
        }

        $response = $disk->response($relative, basename($relative), [
            'Cache-Control' => 'no-store',
        ]);
        if ($mime !== null) {
            $response->headers->set('Content-Type', $mime);
        }

        return $response;
    }

    /**
     * Абсолютный base-href для вложенных ресурсов материала.
     */
    private function baseHref(int $courseId, string $link): string
    {
        $dir = trim(str_replace('\\', '/', dirname($link)), '/.');
        $suffix = ($dir !== '' && $dir !== '.') ? $dir . '/' : '';

        return url("api/course/{$courseId}/raw/") . '/' . $suffix;
    }

    /**
     * Вставка <base href> сразу после <head> (или в начало документа).
     */
    private function injectBase(string $html, string $base): string
    {
        // уже есть base у автора АУК — заменяем на наш, чтобы ресурсы шли через auth-endpoint
        if (preg_match('/<base\b[^>]*>/iu', $html)) {
            return preg_replace('/<base\b[^>]*>/iu', '<base href="' . e($base) . '">' . "\n", $html, 1);
        }
        if (preg_match('/<head\b[^>]*>/iu', $html)) {
            return preg_replace('/(<head\b[^>]*>)/iu', '$1' . "\n" . '<base href="' . e($base) . '">', $html, 1);
        }

        return '<base href="' . e($base) . '">' . "\n" . $html;
    }

    /**
     * Серверная подсветка: оборачивает вхождения термина в <span class="cp-hl">,
     * не трогая теги, скрипты и атрибуты (простейший текстовый сканер).
     */
    private function highlightInHtml(string $html, string $term): string
    {
        $count = 0;
        $pattern = '/(' . preg_quote($term, '/') . ')/iu';

        // разбиваем по тегам, чтобы не ломать разметку и не подсвечивать внутри атрибутов
        $parts = preg_split('/(<[^>]*>)/iu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($part === '' || str_starts_with($part, '<')) {
                continue; // это тег — не трогаем
            }
            $parts[$i] = preg_replace($pattern, '<span class="' . self::HIGHLIGHT_CLASS . '" data-hl="$1">$1</span>', $part, -1, $c);
            $count += $c;
        }

        if ($count === 0) {
            return $html;
        }

        // inline-CSS: документ живёт в iframe без внешних стилей — добавляем свой
        $joined = implode('', $parts);
        $style = '<style>.' . self::HIGHLIGHT_CLASS . '{background:#ffe57f;color:#000;border-radius:2px}'
            . '.' . self::HIGHLIGHT_CLASS . '.cp-hl--active{outline:2px solid #f57c00;background:#ffd740}</style>';
        if (preg_match('/<\/head>/iu', $joined)) {
            return preg_replace('/<\/head>/iu', $style . '</head>', $joined, 1);
        }

        return $style . $joined;
    }
}

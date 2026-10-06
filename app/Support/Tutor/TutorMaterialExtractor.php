<?php

namespace App\Support\Tutor;

use App\Models\Course;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Извлечение текста из материалов курса.
 *
 * Материал курса лежит как HTML-страницы в каталоге
 * private/<самолёт>/<курс>/. Из них берётся ТОЛЬКО текст: теги, скрипты,
 * стили и разметка в вопросы не попадают, а модель получает читаемый
 * фрагмент.
 *
 * Три каталога исключены намеренно, и это не «оптимизация»:
 *
 *  - GIFT — импортированный банк вопросов, то есть МАТЕРИАЛ ЭКЗАМЕНА.
 *    Если его читать, тренажёр начнёт генерировать вопросы из вопросов
 *    экзамена, и граница «тренажёр не связан с аттестацией» перестанет
 *    существовать. Требование плана: тренажёр никогда не подменяет
 *    экзаменационные вопросы. Здесь он даже не читает их.
 *  - app — сгенерированные скрипты страницы, не содержание.
 *  - models — ресурсы 3D/сцен.
 *
 * Иначе «оптимизация» превратилась бы в утечку: вопрос, спроектированный
 * по банку, почти наверняка попадёт на экзамен через отбор методиста —
 * то есть AI-код начнёт влиять на аттестацию.
 */
final class TutorMaterialExtractor
{
    /** Каталоги внутри курса, из которых текст не берётся. */
    public const EXCLUDED_DIRS = ['GIFT', 'app', 'models', 'orig', 'eDoc'];

    /** Расширения, из которых имеет смысл извлекать текст. */
    public const TEXT_EXTENSIONS = ['html', 'htm', 'txt', 'xml', 'md'];

    /** Файлы, которые пропускаются всегда. */
    public const EXCLUDED_FILES = ['index.html'];

    /**
     * Текст курса, готовый к нарезке на фрагменты.
     *
     * @return array{text: string, files: int, skipped: int}
     */
    public function fromCourse(Course $course): array
    {
        $aircraft = $course->aircraft;

        if (! $aircraft || ! $aircraft->path || ! $course->path) {
            return ['text' => '', 'files' => 0, 'skipped' => 0];
        }

        $directory = 'private/'.$aircraft->path.'/'.trim((string) $course->path).'/';

        return $this->fromDirectory($directory);
    }

    /**
     * Текст из каталога материала.
     *
     * @return array{text: string, files: int, skipped: int}
     */
    public function fromDirectory(string $directory): array
    {
        $disk = Storage::disk('private');

        try {
            $files = $disk->allFiles($directory);
        } catch (Throwable $e) {
            Log::warning('Не удалось прочитать каталог материала', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);

            return ['text' => '', 'files' => 0, 'skipped' => 0];
        }

        $parts = [];
        $taken = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (! $this->isUsable($file, $directory)) {
                $skipped++;

                continue;
            }

            $html = $disk->get($file);

            if (! is_string($html) || $html === '') {
                $skipped++;

                continue;
            }

            $text = $this->htmlToText($html);

            if (mb_strlen(trim($text)) < 120) {
                // Слишком короткий файл почти всегда служебный (страница
                // с одной ссылкой, заглушка). Вопрос по нему получится
                // мусорным, поэтому лучше не тратить на него генерацию.
                $skipped++;

                continue;
            }

            // Заголовок раздела берём из имени файла: там он есть
            // («4.2.4 Исходное положение элементов…html»), а внутри
            // HTML его может не быть вовсе.
            $title = $this->titleFromFilename($file);

            $parts[] = trim($title."\n".$text);
            $taken++;
        }

        return [
            'text' => $this->normalize($this->join($parts)),
            'files' => $taken,
            'skipped' => $skipped,
        ];
    }

    /**
     * Годится ли файл для извлечения текста.
     *
     * Проверка на исключение каталогов идёт по СЕГМЕНТАМ пути, а не по
     * вхождению подстроки: иначе каталог «Модели_БПЛА» отсечётся как
     * «models».
     */
    private function isUsable(string $file, string $directory): bool
    {
        $relative = Str::after($file, rtrim($directory, '/').'/');

        if ($relative === $file) {
            // Файл не подкаталог: путь неожиданный, пропускаем.
            return false;
        }

        $segments = explode('/', $relative);
        $filename = (string) array_pop($segments);

        foreach ($segments as $segment) {
            if (in_array(Str::lower($segment), self::EXCLUDED_DIRS, true)) {
                return false;
            }
        }

        if (in_array(Str::lower($filename), self::EXCLUDED_FILES, true)) {
            return false;
        }

        $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, self::TEXT_EXTENSIONS, true);
    }

    /**
     * HTML → читаемый текст.
     *
     * Сначала вырезаются script/style с содержимым: strip_tags на
     * <script> без содержимого оставил бы код программы в тексте, и
     * модель начала бы генерировать вопросы про переменные JavaScript.
     * Затем блочные теги превращаются в переводы строк — иначе весь
     * текст слипается в одну строку и модель теряет границы абзацев.
     */
    public function htmlToText(string $html): string
    {
        $text = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;

        $text = preg_replace('#<(br|hr)\b[^>]*/?>#i', "\n", $text) ?? $text;

        $text = preg_replace(
            '#</(p|div|li|tr|h[1-6]|section|article|td|th|table|ul|ol)>#i',
            "\n",
            $text
        ) ?? $text;

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->normalize($text);
    }

    /**
     * Заголовок раздела из имени файла.
     *
     * Расширение отбрасывается, а не ищется «после последней точки»:
     * имена вида «4.2.4 Порядок.html» содержат точки и в номере.
     */
    private function titleFromFilename(string $file): string
    {
        $name = pathinfo(basename($file), PATHINFO_FILENAME);

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /** @param array<int,string> $parts */
    private function join(array $parts): string
    {
        return implode("\n\n", array_filter($parts, fn ($p) => trim($p) !== ''));
    }

    /**
     * Нормализация пробелов.
     *
     * HTML даёт неразрывные пробелы и куски разметки, которые в тексте
     * выглядят как мусор. NBSP тоже убирается: для модели это просто
     * «пробел», но в тексте остаётся как отдельный символ, из-за чего
     * обрезка фрагментов по длине считается неверно.
     */
    private function normalize(string $text): string
    {
        $text = str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
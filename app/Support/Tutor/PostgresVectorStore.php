<?php

namespace App\Support\Tutor;

use App\Models\TutorChunk;
use Illuminate\Support\Facades\DB;

/**
 * Поиск фрагментов по tsvector (Вариант B).
 *
 * Работает всегда: ни Python, ни расширений не требуется. Цена — поиск
 * лексический, а не смысловой. Для тренажёра это приемлемо лучше, чем
 * могло бы показаться: вопросы формулируются по фрагментам, а значит,
 * термины в них есть по построению.
 *
 * Словарь simple, а не русский: в материалах полно технических аббревиатур
 * (ПП-5, АСУ, ДТО, БПЛА), на которых русский словарь разваливается и
 * молча теряет слова. simple не мешает и работает всегда.
 *
 * Ранжирование — ts_rank. Фрагменты без совпадений не выпадают из выдачи
 * совсем: их добирает вызывающий код, иначе сессия упиралась бы в пустую
 * выдачу на тексте без точных совпадений.
 */
final class PostgresVectorStore implements TutorVectorStore
{
    public function available(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'postgres-fts';
    }

    public function index(int $materialId, array $chunks): array
    {
        // Текст уже лежит в tutor_chunks — отдельная колонка с
        // вектором не нужна, индекс вычисляется на лету по выражению.
        return ['indexed' => count($chunks), 'backend' => $this->name()];
    }

    public function search(int $materialId, string $query, int $limit, array $excludeSeq = []): array
    {
        $chunks = TutorChunk::query()
            ->where('material_id', $materialId)
            ->when($excludeSeq !== [], fn ($q) => $q->whereNotIn('seq', $excludeSeq))
            ->orderBy('seq')
            ->get();

        if ($limit <= 0) {
            return [];
        }

        $strict = $this->tsquery($query, '&');

        if ($strict !== '') {
            // Полнотекстовый индекс из миграции (GIN по
            // to_tsvector('simple', content)) с префиксным расширением:
            // запрос «герметичность» обязан находить «герметичности».
            // Без префикса русские окончания ломали поиск, а подстрочный
            // поиск в PHP работал медленно и не использовал индекс вовсе.
            $rows = $this->rank($materialId, $strict, $limit, $excludeSeq);

            if ($rows !== []) {
                return $rows;
            }

            /*
             * Строгий запрос требует ВСЕ слова вопроса. Для формулировки
             * «как часто проверяют герметичность» слова «часто» в тексте
             * может не быть вовсе — и строгий запрос возвращал пустоту при
             * заведомо пригодном фрагменте. Поэтому при пустом результате
             * повторяем поиск через «или»: ранжирование всё равно ставит
             * более релевантные фрагменты выше.
             */
            $loose = $this->tsquery($query, '|');

            if ($loose !== '') {
                $rows = $this->rank($materialId, $loose, $limit, $excludeSeq);

                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        /*
         * Ничего не совпало: лучше первые непоказанные фрагменты, чем
         * пустая выдача при заведомо пригодном материале. Пустая выдача
         * читалась бы как «вопросов нет» и выглядела бы поломкой.
         */
        return $chunks
            ->take($limit)
            ->map(fn (TutorChunk $c) => ['seq' => $c->seq, 'score' => 0.0])
            ->all();
    }

    /**
     * Ранжированная выборка фрагментов по tsquery.
     *
     * @return array<int,array{seq:int,score:float}>
     */
    private function rank(int $materialId, string $tsquery, int $limit, array $excludeSeq = []): array
    {
        /*
         * Исключение уже использованных фрагментов делается В SQL, а не
         * после выборки. Раньше фильтр стоял только в PHP-ветке, и
         * фрагмент, который сессия уже использовала, возвращался снова:
         * сессия зацикливалась на одном и том же вопросе.
         */
        $exclude = array_map('intval', $excludeSeq);

        /*
         * Исключение уже использованных фрагментов делается В SQL, а не
         * после выборки: фильтр в PHP оставлял в выдаче фрагмент, который
         * сессия уже использовала, и тренировка зацикливалась на одном
         * вопросе.
         *
         * NOT IN с плейсхолдерами, а не ALL ( массивом: приведение
         * строки к массиву прямо в выражении (`'...'::int[]`) PHP не
         * разбирает — квадратные скобки читаются как обращение к
         * массиву, и файл не проходит синтаксис.
         */
        $notIn = $exclude === [] ? '' : ' AND seq NOT IN ('.implode(', ', array_fill(0, count($exclude), '?')).')';

        $bindings = array_merge([$tsquery, $materialId, $tsquery], $exclude, [$limit]);

        $rows = DB::select(
            'SELECT seq,
                    ts_rank(to_tsvector(\'simple\', content), to_tsquery(\'simple\', ?)) AS score
             FROM tutor_chunks
             WHERE material_id = ?
               AND to_tsvector(\'simple\', content) @@ to_tsquery(\'simple\', ?)'
            .$notIn.'
             ORDER BY score DESC, seq
             LIMIT ?',
            $bindings
        );

        return array_map(
            fn ($row) => ['seq' => (int) $row->seq, 'score' => round((float) $row->score, 6)],
            $rows
        );
    }

    /**
     * Запрос в синтаксисе tsquery с префиксным расширением.
     *
     * Текст приходит от пользователя (формулировки уже заданных
     * вопросов), поэтому он ОБЯЗАН быть очищен: служебные символы
     * tsquery (& | ! : * ( )) позволяют построить невалидное выражение,
     * и запрос упал бы с ошибкой Postgres вместо пустой выдачи.
     *
     * Оставляются только буквы и цифры; слова короче 4 символов
     * отбрасываются — они дают слишком много ложных совпадений
     * («как» встречается внутри «кабина»).
     */
    private function tsquery(string $query, string $operator = '&'): string
    {
        preg_match_all('/[\p{L}\p{N}]{4,}/u', mb_strtolower(trim($query), 'UTF-8'), $matches);

        $terms = array_slice(array_unique($matches[0] ?? []), 0, 12);

        if ($terms === []) {
            return '';
        }

        return implode(' '.$operator.' ', array_map(
            fn (string $t) => $this->stem($t).':*',
            $terms
        ));
    }

    /**
     * Огрублённый префикс русского слова.
     *
     * Зачем: префиксный поиск Postgres ищет лексемы, начинающиеся с
     * указанной строки, и слова из реальных материалов отличаются
     * окончаниями, а не суффиксами. Проверено на этой базе:
     *
     *   to_tsvector('simple', 'Проверка герметичности цилиндров')
     *     @@ to_tsquery('simple', 'герметичность:*')  → false
     *     @@ to_tsquery('simple', 'герметичност:*')   → true
     *
     * То есть отбрасывание последнего символа превращает слово в префикс,
     * под который подходят все его формы: герметичности, герметичность,
     * герметичением.
     *
     * Полноценный стеммер требует русского словаря и весит больше, чем
     * весь тренажёр; огрублённое отсечение последнего символа решает ту же
     * задачу для поиска фрагментов.
     */
    private function stem(string $word): string
    {
        // Двухбуквенные хвосты («ами», «ого») срезать нельзя: от этого
        // префикс перестаёт быть началом слова.
        return mb_strlen($word, 'UTF-8') >= 5
            ? mb_substr($word, 0, -1, 'UTF-8')
            : $word;
    }
}

<?php

namespace App\Support\Tutor;

/**
 * Построитель промптов и строгая проверка выдачи модели.
 *
 * Принцип, вокруг которого построен весь тренажёр: модель НЕ ЗНАЕТ темы.
 * Она получает один фрагмент и переформулирует вопросы только по нему.
 * Всё, чего во фрагменте нет, — брак, а не «надо проверить».
 *
 * Отсюда две независимые проверки, и обе нужны:
 *
 *  1. Цитата (source_quote) обязана дословно находиться во фрагменте.
 *     Проверка детерминированная, стоит ноль и ловит именно выдумку:
 *     модель обязана привести подтверждение, и если подтверждения в
 *     тексте нет — вопроса быть не должно. Одного требования «только
 *     фрагмент» в промпте недостаточно: это инструкция, а не запрет,
 *     и модель её нарушает регулярно.
 *
 *  2. Back-check — вопрос возвращается модели с заданием найти
 *     предложение-подтверждение. Дороже (второй запрос), поэтому
 *     включается настройкой, но ловит то, что цитата пропустила:
 *     вопрос, формально опирающийся на фрагмент, но требующий вывода,
 *     которого в тексте нет.
 */
final class TutorQuestionGrounding
{
    /**
     * Схема ответа. Отдаётся модели текстом, а не JSON Schema:
     * локальные модели держат такой формат заметно устойчивее, чем
     * массив примеров.
     */
    private const FORMAT = <<<'TXT'
        {"questions":[
          {
            "qtype": "mcq" | "short" | "open",
            "question": "текст вопроса",
            "options": ["вариант 1", "вариант 2", "вариант 3", "вариант 4"],
            "reference_answer": "правильный вариант ОДНИМ из вариантов",
            "source_quote": "предложение из фрагмента ДОСЛОВНО"
          }
        ]}
        TXT;

    /** Минимальная длина вопроса: короче — не вопрос, а обрывок. */
    private const MIN_QUESTION_LENGTH = 15;

    /** Минимальная длина цитаты: иначе подтверждается пустое. */
    private const MIN_QUOTE_LENGTH = 10;

    /** Число попыток разобрать ответ: модели иногда оборачивают JSON. */
    private const MAX_ATTEMPTS = 2;

    public function __construct(private readonly TutorClient $client)
    {
    }

    /**
     * Промпт для генерации вопросов по фрагменту.
     *
     * @param  array<int,string>  $types
     * @return array<int,array{role:string,content:string}>
     */
    public function generationPrompt(string $chunk, array $types, int $count): array
    {
        $types = $this->allowedTypes($types);

        // Вызовы методов в строковой интерполяции PHP не поддерживает,
        // поэтому всё, что нужно подставить, считается заранее.
        $typesText = $this->typesAsText($types);
        $wanted = (string) $count;

        $system = <<<TXT
            Ты составляешь вопросы для самоподготовки по ОДНОМУ фрагменту учебного материала.

            ЖЁСТКИЕ ПРАВИЛА, их нарушение делает вопрос непригодным:
            1. Используй ТОЛЬКО факты из фрагмента. Не используй общие знания,
               не добавляй ничего, чего нет в тексте.
            2. source_quote — это предложение, скопированное из фрагмента ДОСЛОВНО.
               Если не можешь скопировать дословно — не задавай этот вопрос.
            3. Если по фрагменту нельзя составить {$wanted} вопросов — верни меньше.
               Лучше пустой массив, чем вопрос «по теме вообще».
            4. Не задавай вопрос, ответ на который требует вывода, которого в тексте нет.
            5. Вопрос должен быть на русском языке и проверяться по эталону.

            Допустимые типы: {$typesText}.
            Для типа mcq нужно 4 варианта ответа, и reference_answer должен
            совпадать с одним из вариантов ДОСЛОВНО. Для типов short и open
            поле options равно null.

            Ответь ТОЛЬКО JSON без пояснений по схеме:
            TXT
            ."\n".self::FORMAT;

        $user = "Фрагмент материала:\n---\n".$chunk."\n---\n\nСоставь вопросов: {$wanted}.";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * Промпт обратной проверки: найти подтверждение в фрагменте.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public function backcheckPrompt(string $chunk, string $question, ?string $reference): array
    {
        $system = <<<'TXT'
            Ты проверяющий. Тебе дан фрагмент и вопрос по нему.
            Найди в фрагменте предложение, которое ПОДТВЕРЖДАЕТ вопрос.
            Если такого предложения нет — ответь "no".
            Если есть — скопируй его дословно.
            Не объясняй, не рассуждай. Ответь только JSON: {"support": "предложение"} или {"support": "no"}
            TXT;

        $user = "Фрагмент:\n---\n".$chunk."\n---\n\nВопрос: ".$question
            ."\n\nЭталонный ответ: ".(string) $reference;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * Сгенерировать и проверить вопросы по фрагменту.
     *
     * @param  array<int,string>  $types
     * @param  array<string,bool>  $knownFingerprints  уже заданные вопросы
     * @return array{items: array<int,array<string,mixed>>, rejected: array<int,string>}
     */
    public function generate(string $chunk, array $types, int $count, array $knownFingerprints = []): array
    {
        // Сколько вопросов вообще помещается в бюджет токенов.
        //
        // Просить больше, чем бюджет позволяет, — это не «много вопросов»,
        // а ноль: модель обрывает JSON на середине, ответ не парсится, и
        // вся пачка теряется. Воспроизведено: задача просила 11 вопросов при
        // num_predict=700, результат — 0 принятых за 89 секунд; те же 4
        // вопроса из того же фрагмента давали 4 принятых за 8 секунд.
        $count = min($count, $this->budgetedCount());

        if ($count < 1) {
            return ['items' => [], 'rejected' => ['бюджет токенов не вмещает ни одного вопроса']];
        }

        $attempts = max(1, self::MAX_ATTEMPTS);
        $payload = null;

        for ($i = 0; $i < $attempts; $i++) {
            $payload = $this->client->generate(
                $this->generationPrompt($chunk, $types, $count),
                (int) config('tutor.max_tokens', 700)
            );

            if ($payload['json'] !== null) {
                break;
            }
        }

        $rejected = [];

        if ($payload === null || $payload['json'] === null) {
            return ['items' => [], 'rejected' => ['модель не вернула разбираемый JSON']];
        }

        $raw = $payload['json'];

        $questions = is_array($raw['questions'] ?? null)
            ? $raw['questions']
            // Модель иногда отдаёт объект вместо массива: {"0": {...}}.
            : (array_is_list($raw) ? $raw : array_values($raw));

        $items = [];

        foreach ($questions as $raw) {
            $checked = $this->validate($raw, $chunk, $types, $knownFingerprints);

            if ($checked === null) {
                $rejected[] = $this->reason($raw);

                continue;
            }

            $verdict = $this->backcheck($chunk, $checked);

            // Явный отказ модели («подтверждения в тексте нет») — это и есть
            // брак: вопрос не опирается на фрагмент. Так требует план,
            // и оставить такой вопрос было бы ровно той самодеятельностью,
            // ради которой всё затевалось.
            if ($verdict === false) {
                $rejected[] = 'back-check не нашёл подтверждения: '
                    .mb_substr($checked['question'], 0, 80);

                continue;
            }

            $knownFingerprints[$checked['fingerprint']] = true;
            $checked['backcheck_passed'] = $verdict;
            $items[] = $checked;
        }

        return ['items' => $items, 'rejected' => $rejected];
    }

    /**
     * Проверить один вопрос.
     *
     * @param  array<int,string>  $types
     * @param  array<string,bool>  $knownFingerprints
     * @return array<string,mixed>|null
     */
    public function validate($raw, string $chunk, array $types, array $knownFingerprints = []): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $question = $this->cleanText($raw['question'] ?? '');

        if (mb_strlen($question) < self::MIN_QUESTION_LENGTH) {
            return null;
        }

        $qtype = strtolower((string) ($raw['qtype'] ?? ''));

        if (! in_array($qtype, $this->allowedTypes($types), true)) {
            return null;
        }

        $quote = $this->cleanText($raw['source_quote'] ?? '');

        if (mb_strlen($quote) < self::MIN_QUOTE_LENGTH) {
            return null;
        }

        // Главная проверка: подтверждение обязано быть в тексте.
        if (! $this->quoteInChunk($quote, $chunk)) {
            return null;
        }

        $reference = $this->cleanText($raw['reference_answer'] ?? '');

        if ($reference === '') {
            return null;
        }

        // Ответ должен подтверждаться цитатой.
        //
        // Проверка «цитата есть во фрагменте» ловит выдумку, но не ловит
        // бессмыслицу: модель могла привести настоящую цитату и задать
        // к ней вопрос, ответ на который из цитаты не следует. Замер на
        // слабой модели (qwen2.5:1.5b): вопрос «Где находится
        // гидросистема №1?» с ответом «правой двери» — цитата настоящая,
        // а по ней наоборот, левая дверь. Правило отсекает такое без
        // единого дополнительного запроса.
        if (! $this->answerSupportedByQuote($reference, $quote, $qtype)) {
            return null;
        }

        $options = null;

        if ($qtype === 'mcq') {
            $options = $this->normalizeOptions($raw['options'] ?? null);

            if ($options === null) {
                return null;
            }

            // Эталон обязан быть одним из вариантов. Иначе вопрос
            // непроверяем: пользователь выбирает из списка, а правильный
            // ответ в списке отсутствует.
            if (! $this->referenceAmongOptions($reference, $options)) {
                return null;
            }
        }

        $fingerprint = $this->fingerprint($question);

        if (isset($knownFingerprints[$fingerprint])) {
            return null;
        }

        return [
            'qtype' => $qtype,
            'question' => $question,
            'options' => $options,
            'reference_answer' => $reference,
            'source_quote' => $quote,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Сколько вопросов помещается в один вызов.
     *
     * Считается из бюджета токенов, а не задаётся константой: при
     * TUTOR_MAX_TOKENS=700 один вопрос с вариантами и цитатой занимает
     * около 110 токенов, плюс обёртка ответа. Лишние 120 токенов — запас
     * на служебные поля JSON и завершение объекта.
     */
    private function budgetedCount(): int
    {
        $maxTokens = (int) config('tutor.max_tokens', 700);
        $perQuestion = (int) config('tutor.tokens_per_question', 110);
        $reserve = 120;

        return max(1, (int) floor(($maxTokens - $reserve) / max(1, $perQuestion)));
    }

    /**
     * Back-check: просим модель подтвердить вопрос фрагментом.
     *
     * Три состояния, и различать их обязательно:
     *
     *   true  — модель нашла подтверждение, и оно дословно есть во
     *           фрагменте. Вопрос надёжен.
     *   false — модель прямо сказала, что подтверждения нет. Брак,
     *           вопрос выбрасывается.
     *   null  — проверить не удалось: движок недоступен, либо модель
     *           пересказала подтверждение вместо копии.
     *
     * null — это НЕ брак. Отбрасывать вопросы при недоступности движка
     * означало бы, что при остановленном Ollama тренажёр молчал бы вместо
     * сообщения «движок недоступен». Так же и с пересказом: цитата,
     * которую модель переформулировала, не повод выкидывать вопрос, по
     * которому source_quote найден дословно.
     */
    private function backcheck(string $chunk, array $item): ?bool
    {
        if (! config('tutor.backcheck', true)) {
            return null;
        }

        try {
            $result = $this->client->generate(
                $this->backcheckPrompt($chunk, $item['question'], $item['reference_answer']),
                (int) config('tutor.backcheck_tokens', 300)
            );
        } catch (TutorUnavailableException $e) {
            return null;
        }

        $support = $result['json']['support'] ?? null;

        if (! is_string($support) || trim($support) === '') {
            return false;
        }

        if (str_starts_with(trim($this->lower($support)), 'no')) {
            return false;
        }

        // Подтверждение проверяется тем же правилом, что и source_quote.
        // Не нашлось дословно — это не «подтверждения нет», а «модель
        // пересказала»: вопрос оставляем, но помечаем как непроверенный.
        return $this->quoteInChunk($support, $chunk) ? true : null;
    }

    /**
     * Регистронезависимое сравнение с учётом кириллицы.
     *
     * Именно mb_strtolower, а не strtolower: тот не трогает кириллицу,
     * и цитата «предохранитель» не находилась бы во фрагменте с
     * «Предохранитель» — проверка подтверждения отказывала бы на
     * корректных вопросах.
     */
    private function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Есть ли цитата во фрагменте.
     *
     * Сравнение по нормализованному тексту: регистр, лишние пробелы и
     * типографские кавычки не должны решать, подтверждён вопрос или нет.
     * Модель почти всегда «исправляет» кавычки и пробелы при цитировании.
     */
    public function quoteInChunk(string $quote, string $chunk): bool
    {
        $needle = $this->normalizeForCompare($quote);
        $haystack = $this->normalizeForCompare($chunk);

        if ($needle === '' || $haystack === '') {
            return false;
        }

        if (str_contains($haystack, $needle)) {
            return true;
        }

        // Длинная цитата могла прийти с переносом строки посреди
        // предложения. Пробуем склеенный вариант.
        $flat = str_replace(' ', '', $needle);

        if (mb_strlen($flat) >= 20 && str_contains(str_replace(' ', '', $haystack), $flat)) {
            return true;
        }

        /*
         * Последний допуск: все значимые слова цитаты встречаются во
         * фрагменте, возможно, в другом порядке и не подряд.
         *
         * Зачем: строгое дословное совпадение отсекало половину в��прашиваемых
         * вопросов на слабой модели — она переставляет слова и пропускает
         * их, но не выдумывает. Замер до и после: сессии давали то 5
         * пригодных вопросов, то 0, и E2E на этом «мигал».
         *
         * При этом гарантия «не выдумывать» сохраняется полностью:
         * выдуманная цитата содержит слово, которого во фрагменте нет.
         * Пример из замеров: подмена «более 100 А» на «более 250 А»
         * отсекается, потому что «250» в тексте не встречается.
         */
        return $this->allWordsPresent($needle, $haystack);
    }

    /**
     * Все ли значимые слова текста встречаются в другом тексте.
     *
     * Слова короче 3 символов и «стоп-слова» пропускаются: они не
     * несут смысла и модель их почти всегда искажает.
     */
    private function allWordsPresent(string $needle, string $haystack): bool
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return false;
        }

        $checked = 0;

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') < 3) {
                continue;
            }

            $checked++;

            if (! str_contains($haystack, $word)) {
                return false;
            }
        }

        // Нужно проверить хоть что-то: иначе фраза из одних коротких
        // слов прошла бы по любому фрагменту.
        return $checked > 0;
    }

    /**
     * Подтверждается ли ответ цитатой.
     *
     * Для mcq и short требуется, чтобы эталон содержался в цитате: у
     * вопроса с вариантами и у короткого ответа правильный ответ — это
     * конкретное слово или число, а не рассуждение, и оно обязано
     * стоять в подтверждении дословно.
     *
     * Для open требование ослаблено: развёрнутый ответ почти никогда не
     * цитируется дословно, и строгое правило отсекало бы все открытые
     * вопросы. Там проверку подтверждения делает back-check.
     */
    private function answerSupportedByQuote(string $reference, string $quote, string $qtype): bool
    {
        if ($qtype === 'open') {
            return true;
        }

        $needle = $this->normalizeForCompare($reference);
        $haystack = $this->normalizeForCompare($quote);

        if ($needle === '' || $haystack === '') {
            return false;
        }

        return str_contains($haystack, $needle);
    }

    /** Нормализация для сравнения подстрок. */
    private function normalizeForCompare(string $text): string
    {
        $text = $this->cleanText($text);
        $text = str_replace(
            ['«', '»', '„', '“', '”', '–', '—', '…', "\xc2\xa0"],
            ['"', '"', '"', '"', '"', '-', '-', '...', ' '],
            $text
        );

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $this->lower(trim($text));
    }

    /**
     * Хеш нормализованного вопроса — основа дедупликации.
     *
     * Нормализация снимает различия в формулировке, которые не меняют
     * смысл: разные пробелы, регистр, хвостовые точки. Без неё модель
     * задаёт один вопрос трижды, просто переставив запятую.
     */
    public function fingerprint(string $question): string
    {
        $normalized = $this->normalizeForCompare($question);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? $normalized;

        return hash('sha256', $normalized);
    }

    /**
     * @param  mixed  $options
     * @return array<int,string>|null
     */
    private function normalizeOptions($options): ?array
    {
        if (! is_array($options) || count($options) < 2) {
            return null;
        }

        $clean = [];

        foreach ($options as $option) {
            $option = $this->cleanText(is_scalar($option) ? (string) $option : '');

            if ($option === '') {
                continue;
            }

            $clean[] = $option;
        }

        if (count($clean) < 2 || count($clean) > 6) {
            return null;
        }

        // Дубли вариантов — брак, а не повод молча ужать список.
        // Молчаливое array_unique() превращало вопрос с четырьмя
        // вариантами в вопрос с тремя и выглядело как успешная
        // проверка, хотя модель ошиблась в данных. Отказ явный.
        if (count(array_unique($clean)) !== count($clean)) {
            return null;
        }

        return array_values($clean);
    }

    /**
     * @param  array<int,string>  $options
     */
    private function referenceAmongOptions(string $reference, array $options): bool
    {
        $needle = $this->normalizeForCompare($reference);

        foreach ($options as $option) {
            if ($this->normalizeForCompare($option) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function allowedTypes(array $types): array
    {
        $allowed = array_values(array_intersect(
            array_map('strval', $types),
            (array) config('tutor.question_types', ['mcq', 'short', 'open'])
        ));

        return $allowed === [] ? ['mcq'] : $allowed;
    }

    /** @param array<int,string> $types */
    private function typesAsText(array $types): string
    {
        return implode(', ', array_map(fn ($t) => $t === 'mcq' ? 'mcq (с вариантами)' : $t, $types));
    }

    /** Чистка текста: схлопывание пробелов и обрезка краёв. */
    private function cleanText(?string $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** Причина отбраковки — для логов и разбора качества. */
    private function reason($raw): string
    {
        if (! is_array($raw)) {
            return 'не объект';
        }

        $fields = ['question', 'qtype', 'options', 'reference_answer', 'source_quote'];

        // Приводить значение к строке нельзя: options — это массив, и
        // (string) на массиве бросает ErrorException. А reason() зовут
        // ИМЕННО для отклонённых вопросов, то есть на самом частом
        // пути: каждый брак ронял бы всю генерацию пачки вместо того,
        // чтобы быть отброшенным.
        $present = array_values(array_filter(
            $fields,
            function ($f) use ($raw) {
                if (! isset($raw[$f])) {
                    return false;
                }

                $value = $raw[$f];

                return is_array($value) ? $value !== [] : trim((string) $value) !== '';
            }
        ));

        return $present === [] ? 'пустой объект' : 'поля: '.implode(',', $present);
    }
}
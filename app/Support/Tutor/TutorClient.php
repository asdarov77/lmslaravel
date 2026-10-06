<?php

namespace App\Support\Tutor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Клиент локального нейродвижка (Ollama или llama.cpp).
 *
 * Наружу этот класс выдаёт три операции: health(), generate(), embed().
 * Всё остальное — парсинг ответа, приведение к JSON, ретраи — внутри.
 *
 * Три вещи, которые здесь нельзя упростить, иначе фича не работает:
 *
 *  1. think=false. У qwen3/qwen3.5 по умолчанию включено «размышление»:
 *     модель тратит весь бюджет токенов на рассуждение и возвращает
 *     пустой content с done_reason=length. Замер: 96 секунд и ноль
 *     вопросов против 3 секунд с think=false.
 *
 *  2. format=json. Без него модель оборачивает JSON в пояснения
 *     («Вот вопросы по вашему фрагменту:»), и разбор падает.
 *
 *  3. Разбор JSON терпимый к обёрткам: модель даже в json-режиме
 *     иногда отдаёт ```json ... ``` или текст вокруг объекта. Раньше
 *     такие ответы просто отбрасывались, и пачка вопросов терялась
 *     целиком из-за одного лишнего блока кода в ответе.
 *
 * Класс намеренно не final: тесты подменяют его в контейнере подклассом
 * с заранее заданными ответами. Иначе каждый тест тренажёра требовал бы
 * запущенного Ollama и зависел бы от модели — то есть проверял бы не
 * логику приложения, а наличие локальной нейросети у того, кто запустил
 * тесты. Отдельный интерфейс для этого не нужен: клиент тонкий, у него
 * три операции, и подменять его целиком дешевле, чем вводить слой.
 */
class TutorClient
{
    /**
     * Доступен ли движок.
     *
     * Не бросает исключений: вызывающая сторона обязана уметь показать
     * «нейродвижок недоступен» как состояние, а не как 500.
     *
     * @return array{available: bool, models?: array<int,string>, error?: string}
     */
    public function health(): array
    {
        if (! $this->enabled()) {
            return [
                'available' => false,
                'error' => 'Тренажёр выключен (TUTOR_ENABLED=false)',
            ];
        }

        try {
            $response = $this->request('GET', '/api/tags', null, 10);
        } catch (Throwable $e) {
            return [
                'available' => false,
                'error' => 'Нейродвижок не отвечает: '.$e->getMessage(),
            ];
        }

        $models = [];

        foreach ($response['models'] ?? [] as $model) {
            if (isset($model['name'])) {
                $models[] = $model['name'];
            }
        }

        // Модель из конфига может отсутствовать: с qwen2.5:3b на этой
        // машине первая же генерация падала «model not found».
        // Об этом честнее сказать на диагностике, чем получить пустую
        // выдачу вопросов.
        $wanted = $this->model();

        return [
            'available' => true,
            'models' => $models,
            'model' => $wanted,
            'model_present' => $this->hasModel($models, $wanted),
        ];
    }

    /**
     * Генерация текста с требованием JSON.
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     * @return array{json: mixed, raw: string}
     *
     * @throws TutorUnavailableException движок недоступен или ответил мусором
     */
    public function generate(array $messages, int $maxTokens = 900): array
    {
        if (! $this->enabled()) {
            throw new TutorUnavailableException('Тренажёр выключен (TUTOR_ENABLED=false)');
        }

        $payload = [
            'model' => $this->model(),
            'stream' => false,
            'format' => 'json',
            'messages' => $messages,
            'options' => [
                'temperature' => (float) config('tutor.temperature', 0.4),
                'num_predict' => $maxTokens,
            ],
        ];

        if (config('tutor.disable_thinking', true)) {
            // Ключ понимают Ollama >= 0.9 и llama.cpp с флагом reasoning.
            // Старые версии его игнорируют — хуже не будет.
            $payload['think'] = false;
        }

        try {
            $response = $this->request('POST', '/api/chat', $payload, (int) config('tutor.timeout', 180));
        } catch (Throwable $e) {
            throw new TutorUnavailableException('Нейродвижок не ответил: '.$e->getMessage(), 0, $e);
        }

        $raw = $response['message']['content'] ?? '';

        if (! is_string($raw) || trim($raw) === '') {
            // Почти всегда означает, что бюджет токенов съеден
            // «размышлением»: done_reason=length, content пуст.
            Log::warning('Пустой ответ нейродвижка', [
                'model' => $this->model(),
                'done_reason' => $response['done_reason'] ?? null,
                'thinking_length' => isset($response['message']['thinking'])
                    ? strlen((string) $response['message']['thinking'])
                    : null,
            ]);

            throw new TutorUnavailableException(sprintf(
                'Нейродвижок вернул пустой ответ (done_reason=%s). Обычно это значит, '
                .'модель потратила бюджет токенов на «размышление»: проверьте TUTOR_DISABLE_THINKING.',
                (string) ($response['done_reason'] ?? 'unknown')
            ));
        }

        return [
            'json' => $this->decodeJson($raw),
            'raw' => $raw,
        ];
    }

    /**
     * Эмбеддинги.
     *
     * Необязательны: используются только если модель эмбеддингов реально
     * установлена. Отсутствие эмбеддингов — не ошибка, а переход на
     * полнотекстовый отбор фрагментов.
     *
     * @param  array<int,string>  $input
     * @return array<int,float>|null
     */
    public function embed(array $input): ?array
    {
        $model = (string) config('tutor.embed_model', '');

        if ($model === '' || ! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->request('POST', '/api/embed', [
                'model' => $model,
                'input' => $input,
            ], 120);
        } catch (Throwable $e) {
            // Типичный случай: модель эмбеддингов не скачана. Это не
            // поломка, а штатный режим работы без эмбеддингов.
            Log::info('Эмбеддинги недоступны, используется полнотекстовый отбор', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $vector = $response['embeddings'][0] ?? $response['embedding'] ?? null;

        return is_array($vector) ? array_map('floatval', $vector) : null;
    }

    /**
     * Модель эмбеддингов установлена?
     *
     * Отдельный метод, потому что «эмбеддинги есть» и «эмбеддинги
     * нужны» — разные вещи: режим без них полностью работоспособен.
     */
    public function embedAvailable(): bool
    {
        $model = (string) config('tutor.embed_model', '');

        if ($model === '' || ! $this->enabled()) {
            return false;
        }

        $health = $this->health();

        return $health['available'] && $this->hasModel($health['models'] ?? [], $model);
    }

    /**
     * Разбор ответа модели в JSON.
     *
     * Терпим к типичной обёртке: ```json ... ``` и текст вокруг
     * объекта. Возвращает null, если разобрать нечем — вызывающий код
     * решает, что делать (для вопросов это «пропустить пачку», для
     * диагностики — «показать модератору сырой ответ»).
     */
    public function decodeJson(string $raw): mixed
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // 1. Блок ```json ... ```.
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $decoded = json_decode($m[1], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 2. Первый массив или первый объект в тексте.
        if (preg_match('/[\{\[].*[\}\]]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('Ответ нейродвижка не разобран как JSON', [
            'preview' => mb_substr($raw, 0, 300),
        ]);

        return null;
    }

    /** Имя модели из конфига. */
    public function model(): string
    {
        return (string) config('tutor.model', '');
    }

    /**
     * Включён ли тренажёр.
     *
     * Настройка в базе важнее .env: см. TutorSettings. Иначе
     * переключатель в админке работал бы только до первого
     * перезапуска воркеров.
     */
    public function enabled(): bool
    {
        return TutorSettings::enabled();
    }

    /**
     * Учитывает ли Ollama тег. `qwen3:8b` и `qwen3:8b-q4_K_M` — разные
     * строки, а установлен может быть любой из них; Ollama сама
     * подставляет тег по умолчанию, поэтому сравнение по полному имени
     * давало бы ложное «модель отсутствует».
     *
     * @param  array<int,string>  $installed
     */
    private function hasModel(array $installed, string $wanted): bool
    {
        if ($wanted === '') {
            return false;
        }

        foreach ($installed as $name) {
            if ($name === $wanted) {
                return true;
            }

            $base = explode(':', $wanted)[0];

            if (str_starts_with($name, $base.':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * HTTP-вызов к движку с понятной ошибкой вместо исключения Http.
     *
     * @param  array<string,mixed>|null  $payload
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload, int $timeout): array
    {
        $url = (string) config('tutor.url').$path;

        $response = $payload === null
            ? Http::timeout($timeout)->get($url)
            : Http::timeout($timeout)->acceptJson()->post($url, $payload);

        if ($response->failed()) {
            throw new TutorUnavailableException(sprintf(
                'Нейродвижок ответил %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 200)
            ));
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }
}
<?php

namespace App\Support\Tutor;

use App\Models\TutorChunk;

/**
 * Нарезка текста материала на фрагменты.
 *
 * Размер фрагмента и перекрытие заданы в config/tutor.php, и смысл их
 * такой:
 *
 *  - фрагмент крупный — модель отвечает «по теме вообще», grounding
 *    размывается, и вопросы перестают быть проверяемыми по цитате;
 *  - фрагмент мелкий — вопрос вырождается в «прочитайте предложение
 *    вслух», и тренировка теряет смысл.
 *
 * Перекрытие нужно для одной причины: вопрос почти никогда не строится
 * на предложении, которое попало точно на границу двух фрагментов, и
 * без перекрытия такая тема не генерируется вовсе.
 *
 * Нарезка идёт по абзацам, а не по символам: разрезанный посреди
 * предложения фрагмент модель пересказывает по памяти и путает, что
 * было в оригинале.
 */
final class TutorChunker
{
    /**
     * @return array<int,array{content: string, token_count: int}>
     */
    public function split(string $text, ?int $maxChars = null, ?int $overlapChars = null): array
    {
        // Размер меряется в символах, а не в токенах: для русского текста
        // оценка «4 символа на токен» гуляет, и фрагмент выходит то
        // 1000, то 3000 символов. Символы предсказуемы, а размер промпта
        // модели всё равно считает Ollama на своей стороне.
        $maxChars = $maxChars ?: (int) config('tutor.max_chunk_chars', 2000);
        $overlapChars = $overlapChars ?? (int) config('tutor.chunk_overlap_chars', 300);

        $paragraphs = $this->paragraphs($text);

        if ($paragraphs === []) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            // Абзац длиннее фрагмента режется по предложениям: иначе
            // он один занял бы весь фрагмент, а вопросы по остальному
            // тексту не появились бы.
            if (mb_strlen($paragraph) > $maxChars) {
                if (trim($current) !== '') {
                    $chunks[] = $current;
                    $current = '';
                }

                foreach ($this->splitLongParagraph($paragraph, $maxChars) as $part) {
                    $chunks[] = $part;
                }

                continue;
            }

            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;

                continue;
            }

            $chunks[] = $current;
            $current = $this->withOverlap($current, $overlapChars);
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return array_values(array_map(
            fn (string $content) => [
                'content' => trim($content),
                'token_count' => TutorChunk::estimateTokens($content),
            ],
            array_filter($chunks, fn ($c) => trim((string) $c) !== '')
        ));
    }

    /** @return array<int,string> */
    private function paragraphs(string $text): array
    {
        $parts = preg_split('/\n{2,}/', trim($text)) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            fn (string $p) => mb_strlen($p) > 0
        ));
    }

    /**
     * Длинный абзац режется по границам предложений.
     *
     * @return array<int,string>
     */
    private function splitLongParagraph(string $paragraph, int $maxChars): array
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph];
        $parts = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $candidate = $current === '' ? $sentence : $current.' '.$sentence;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;

                continue;
            }

            if (trim($current) !== '') {
                $parts[] = trim($current);
            }

            $current = $sentence;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Хвост предыдущего фрагмента, который переносится в начало
     * следующего.
     *
     * Обрезка идёт по границе предложения: хвост, начинающийся с
     * середины фразы, хуже, чем его отсутствие.
     */
    private function withOverlap(string $chunk, int $overlapChars): string
    {
        if ($overlapChars <= 0 || mb_strlen($chunk) <= $overlapChars) {
            return '';
        }

        $tail = mb_substr($chunk, -$overlapChars);

        $boundary = strpos($tail, '. ');

        if ($boundary !== false) {
            $tail = substr($tail, $boundary + 2);
        }

        return trim($tail);
    }

    /** ~4 символа на токен для русского текста. */
    private function tokensToChars(int $tokens): int
    {
        return max(200, $tokens * 4);
    }
}
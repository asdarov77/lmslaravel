<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Question;
use Illuminate\Http\Request;

/**
 * Импорт банка вопросов из CSV/Excel.
 *
 * Формат файла: первая колонка — текст вопроса, дальше — варианты;
 * верный вариант помечен ведущим знаком «=» (конвенция GIFT, знакомая
 * составителям курсов). Строки без вопроса или без верного варианта
 * пропускаются с подсчётом, а не обрывают весь импорт: файл готовится
 * людьми, и одна кривая строка не должна убивать 500 корректных.
 *
 * POST /api/questions/import: file (uploads) + aukstructure_id.
 */
class QuestionImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'aukstructure_id' => ['required', 'integer', 'exists:aukstructures,id'],
        ]);

        $path = $request->file('file')->getRealPath();
        $rows = $this->readRows($path, $request->file('file')->getClientOriginalExtension());

        $auk = Aukstructure::findOrFail($request->integer('aukstructure_id'));

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $cells = array_values(array_map(fn ($v) => trim((string) $v), $row));

            $questionText = $cells[0] ?? '';
            if ($questionText === '') {
                $skipped++;
                continue;
            }

            $answers = array_slice($cells, 1);
            $hasCorrect = false;
            foreach ($answers as $a) {
                if ($a !== '' && str_starts_with($a, '=')) {
                    $hasCorrect = true;
                }
            }

            if (! $hasCorrect) {
                $skipped++;
                continue;
            }

            $question = Question::create([
                'question_text' => $questionText,
                'category_id' => $auk->course?->category_id ? (int) $auk->course->category_id : null,
                'aukstructure_id' => $auk->id,
            ]);

            foreach ($answers as $answer) {
                if ($answer === '') {
                    continue;
                }

                \App\Models\Answer::create([
                    'question_id' => $question->id,
                    'answer' => ltrim($answer, '='),
                    'is_correct' => str_starts_with($answer, '='),
                ]);
            }

            $imported++;
        }

        return response()->json([
            'success' => true,
            'data' => ['imported' => $imported, 'skipped' => $skipped],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Читает строки файла: CSV с разделителем „;“ или Excel
     * (PhpSpreadsheet). Расширение определяет способ чтения, расширение
     * придуманное — читается как CSV.
     *
     * @return list<list<string>>
     */
    private function readRows(string $path, string $extension): array
    {
        if (in_array(strtolower($extension), ['xlsx', 'xls'], true)) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(
                strtolower($extension) === 'xls' ? 'Xls' : 'Xlsx'
            );
            return $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $rows = [];

        foreach ($lines as $line) {
            $rows[] = str_getcsv($line, ';', '"');
        }

        return $rows;
    }
}

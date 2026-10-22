<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Feature\AuthenticatesApi;

/**
 * Импорт банка вопросов из CSV/Excel (POST /api/questions/import).
 *
 * Формат строки: «вопрос;=верный вариант;неверный;неверный».
 * Кривые строки (нет вопроса или нет верного варианта) пропускаются
 * и считаются, а не обрывают весь импорт.
 */
class QuestionImportTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    public function test_guest_is_rejected(): void
    {
        $this->postJson('/api/questions/import', ['aukstructure_id' => 1])->assertUnauthorized();
    }

    public function test_requires_questions_manage(): void
    {
        $trainee = \App\Models\User::factory()->create(['role' => 'Обучаемый']);
        $this->asExistingUser($trainee);

        $this->postJson('/api/questions/import', ['aukstructure_id' => 1])
            ->assertStatus(403);
    }

    public function test_imports_csv_rows_and_counts_skipped(): void
    {
        $this->admin();

        $course = Course::factory()->create();
        $auk = Aukstructure::factory()->create([
            'title' => 'Импортируемый раздел',
            'course_id' => $course->id,
            'type' => 3,
        ]);

        $csv = "Какой прибор показывает скорость;=Указатель скорости;Высотомер;Авиагоризонт\n\nНе вопрос с ответами;Вариант без верного\n";

        $response = $this->postJson('/api/questions/import', [
            'file' => UploadedFile::fake()->createWithContent('questions.csv', $csv),
            'aukstructure_id' => $auk->id,
        ])->assertOk();

        $this->assertSame(1, $response->json('data.imported'));
        $this->assertSame(1, $response->json('data.skipped'));

        $question = Question::where('aukstructure_id', $auk->id)->firstOrFail();
        $this->assertSame('Какой прибор показывает скорость', $question->question_text);
        // Вырезается „=“ и ведётся флаг is_correct.
        $this->assertTrue((bool) Answer::where('question_id', $question->id)->where('is_correct', true)->firstOrFail()->is_correct);
        $this->assertSame('Указатель скорости', Answer::where('question_id', $question->id)->where('is_correct', true)->value('answer'));
        $this->assertSame(3, Answer::where('question_id', $question->id)->count());
    }
}

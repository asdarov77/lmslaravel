<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Банк вопросов: счётчики и целостность.
 *
 * Закрывает то, чего на странице банка раньше не было.
 *
 *  1. СЧЁТЧИКОВ НЕ БЫЛО ВООБЩЕ. Чтобы узнать, сколько вопросов в
 *     специальности, нужно было открыть каждую тему вручную. Теперь
 *     /api/questions/statistics отдаёт счётчики заранее.
 *
 *  2. ВОПРОСЫ БЕЗ ОТВЕТОВ ИЛИ БЕЗ ВЕРНОГО ВАРИАНТА ВЫГЛЯДЕЛИ КАК
 *     ОБЫЧНЫЕ. Методист узнавал о битом вопросе только тогда, когда
 *     обучающийся «не сдал» экзамен. Теперь в каждой строке списка есть
 *     answers_count / correct_answers_count и метка integrity.
 *
 *  3. СЧЁТЧИКИ СЧИТАЛИСЬ НА КЛИЕНТЕ. Это значило, что в браузер
 *     попадала правильность каждого варианта — та же утечка, которую
 *     закрыли в /exams/{id}/questions. Теперь счётчики приходят с
 *     withCount на сервере.
 *
 *  4. СПИСОК ТЕМ СТРОИЛСЯ ДЕДУПЛИКАЦИЕЙ ПО НАЗВАНИЮ НА КЛИЕНТЕ,
 *     из-за чего две темы с одинаковым названием схлопывались в одну,
 *     а вопросы второй становились недостижимыми. Теперь темы приходят
 *     из статистики готовыми.
 */
class QuestionBankTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Category $category;

    private Category $otherCategory;

    private Aukstructure $module;

    private Aukstructure $otherModule;

    protected function setUp(): void
    {
        parent::setUp();

        $course = Course::factory()->create();
        $this->category = Category::factory()->create(['title' => 'Командир экипажа']);
        $this->otherCategory = Category::factory()->create(['title' => 'Пустая специальность']);

        $this->module = Aukstructure::factory()->create([
            'course_id' => $course->id,
            'title' => 'Силовая установка',
        ]);

        // Вторая тема С ТЕМ ЖЕ названием: раньше дедупликация по title
        // на клиенте делала её недостижимой.
        $this->otherModule = Aukstructure::factory()->create([
            'course_id' => $course->id,
            'title' => 'Силовая установка',
        ]);
    }

    private function makeQuestion(array $attrs = [], array $answers = ['correct', 'wrong', 'wrong']): Question
    {
        $question = Question::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'aukstructure_id' => $this->module->id,
        ], $attrs));

        foreach ($answers as $answer) {
            if ($answer === 'correct') {
                Answer::factory()->create(['question_id' => $question->id, 'is_correct' => true]);
            } elseif ($answer === 'wrong') {
                Answer::factory()->create(['question_id' => $question->id, 'is_correct' => false]);
            }
        }

        return $question;
    }

    public function test_statistics_requires_authentication(): void
    {
        $this->getJson('/api/questions/statistics')->assertUnauthorized();
    }

    public function test_statistics_requires_question_manager_permission(): void
    {
        $this->asUser(['role' => 'Обучаемый']);
        $this->getJson('/api/questions/statistics')->assertStatus(403);
    }

    public function test_statistics_counts_questions_per_category_and_module(): void
    {
        $this->makeQuestion();
        $this->makeQuestion();

        $this->asUser(['role' => 'Инструктор']);
        $data = $this->getJson('/api/questions/statistics')->assertOk()->json('data');

        $category = collect($data['categories'])->firstWhere('id', $this->category->id);

        $this->assertSame(2, $category['questions']);
        $this->assertSame(1, $category['modules']);
        $this->assertSame(2, $data['totals']['questions']);
    }

    public function test_empty_category_is_visible_but_marked(): void
    {
        // Категория без вопросов есть в списке — иначе методист не понимает,
        // почему её нет. Пустое состояние теперь сообщает об этом явно.
        $this->asUser(['role' => 'Инструктор']);
        $data = $this->getJson('/api/questions/statistics')->json('data');

        $empty = collect($data['categories'])->firstWhere('id', $this->otherCategory->id);

        $this->assertNotNull($empty, 'пустая специальность видна в списке');
        $this->assertSame(0, $empty['questions']);

        // Считаем по самому списку, а не сравниваем с единицей: фабрика
        // Course создаёт собственную категорию, и число пустых зависит
        // от фикстур.
        $emptyIds = collect($data['categories'])
            ->where('questions', 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $this->assertTrue($emptyIds->contains($this->otherCategory->id));
        $this->assertSame($emptyIds->count(), $data['totals']['empty_categories']);
    }

    public function test_modules_with_same_title_are_not_collapsed(): void
    {
        // Регресс: список тем строился дедупликацией по названию на
        // клиенте, поэтому две темы «Силовая установка» схлопывались в
        // одну, и вопросы второй становились недостижимы.
        $this->makeQuestion();
        $this->makeQuestion(['aukstructure_id' => $this->otherModule->id]);

        $this->asUser(['role' => 'Инструктор']);
        $modules = $this->getJson('/api/questions/statistics')->json('data.modules');

        $titles = collect($modules)->where('title', 'Силовая установка');

        $this->assertCount(2, $titles, 'обе темы с одинаковым названием попадают в список');
        $this->assertEqualsCanonicalizing(
            [$this->module->id, $this->otherModule->id],
            $titles->pluck('id')->values()->all()
        );
    }

    public function test_statistics_detects_integrity_problems(): void
    {
        $this->makeQuestion();                                                   // здоровый
        $this->makeQuestion([], []);                                             // без ответов
        $this->makeQuestion([], ['correct', 'correct']);                          // два верных

        $this->asUser(['role' => 'Инструктор']);
        $totals = $this->getJson('/api/questions/statistics')->json('data.totals');

        $this->assertSame(1, $totals['without_answers']);
        $this->assertSame(1, $totals['multiple_correct']);
        $this->assertSame(0, $totals['without_correct']);
    }

    public function test_statistics_detects_question_without_correct_answer(): void
    {
        $this->makeQuestion([], ['wrong', 'wrong']);

        $this->asUser(['role' => 'Инструктор']);
        $totals = $this->getJson('/api/questions/statistics')->json('data.totals');

        $this->assertSame(1, $totals['without_correct']);
    }

    public function test_list_exposes_answer_counts(): void
    {
        $question = $this->makeQuestion();

        $this->asUser(['role' => 'Инструктор']);
        $row = $this->getJson('/api/questions?aukstructure_id='.$this->module->id)
            ->assertOk()
            ->json('data.0');

        $this->assertSame(3, $row['answers_count']);
        $this->assertSame(1, $row['correct_answers_count']);
        $this->assertNull($row['integrity']);
    }

    public function test_list_marks_broken_questions(): void
    {
        $this->makeQuestion([], []);
        $this->makeQuestion([], ['correct', 'correct']);
        $this->makeQuestion([], ['wrong']);

        $this->asUser(['role' => 'Инструктор']);
        $rows = $this->getJson('/api/questions?aukstructure_id='.$this->module->id)->json('data');

        $byIntegrity = collect($rows)->groupBy('integrity');

        $this->assertCount(1, $byIntegrity['no_answers'] ?? []);
        $this->assertCount(1, $byIntegrity['multiple_correct'] ?? []);
        $this->assertCount(1, $byIntegrity['no_correct'] ?? []);
    }

    public function test_question_bank_is_closed_to_trainee(): void
    {
        // Раньше /api/questions висел только на auth:sanctum и отдавал
        // вместе с ответами is_correct, то есть правильные ответы на все
        // вопросы читались любым вошедшим.
        $this->makeQuestion();

        $this->asUser(['role' => 'Обучаемый']);
        $this->getJson('/api/questions')->assertStatus(403);
    }

    public function test_list_filters_by_module(): void
    {
        $this->makeQuestion();
        $this->makeQuestion(['aukstructure_id' => $this->otherModule->id]);

        $this->asUser(['role' => 'Инструктор']);

        $rows = $this->getJson('/api/questions?aukstructure_id='.$this->otherModule->id)->json('data');

        $this->assertCount(1, $rows, 'вопросы другой темы не подмешиваются');
    }

    public function test_questions_without_module_row_are_reported(): void
    {
        // Контроллер делает inner JOIN с aukstructures ради title темы.
        // Вопрос с потерянной темой в выдачу не попадает — это должно
        // быть видно в сводке, а не молча пропадать.
        $orphan = Question::factory()->create([
            'category_id' => $this->category->id,
            'aukstructure_id' => 999999,
        ]);
        Answer::factory()->create(['question_id' => $orphan->id, 'is_correct' => true]);

        $this->asUser(['role' => 'Инструктор']);

        // В сводке он учитывается (счётчик по questions, без JOIN).
        $totals = $this->getJson('/api/questions/statistics')->json('data.totals');
        $this->assertSame(1, $totals['questions']);

        // А в списке темы отсутствует — JOIN его отбрасывает.
        $rows = $this->getJson('/api/questions?aukstructure_id=999999')->json('data');
        $this->assertSame([], $rows);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Answer;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Экзамены: выдача вопросов и приём попыток.
 *
 * Закрывает три дефекта, найденные в работающей системе.
 *
 *  1. ПРАВИЛЬНЫЕ ОТВЕТЫ УХОДИЛИ КЛИЕНТУ.
 *     /api/questions отдавал все ответы вместе с is_correct и висел
 *     только на auth:sanctum, поэтому ЛЮБОЙ вошедший — включая
 *     обучаемого — мог вычитать ключи на все 1657 вопросов. А страница
 *     экзамена считала результат у себя в браузере. Итог: экзамен можно
 *     было «сдать» не отвечая.
 *
 *  2. РЕЗУЛЬТАТ НИКУДА НЕ СОХРАНЯЛСЯ.
 *     submitTest() во фронте был написан, но ни разу не вызван, а
 *     маршрута /api/student-answers не существовало. test_results
 *     держали 0 строк, и дашборд показывал «Экзаменов сдано: 0» всем
 *     подряд.
 *
 *  3. НЕ БЫЛО СУЩНОСТИ ЭКЗАМЕНА: негде хранить назначение, окно
 *     доступности, лимит попыток и проходной балл.
 *
 * Лимиты проверяются на сервере сознательно: проверка во фронте
 * обходится повтором запроса.
 */
class ExamAttemptTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $group;

    private Group $otherGroup;

    private Category $category;

    private Question $question;

    private User $trainee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create(['groupname' => 'Группа А']);
        $this->otherGroup = Group::factory()->create(['groupname' => 'Группа Б']);
        $this->category = Category::factory()->create(['title' => 'Специальность А']);

        $module = Aukstructure::factory()->create(['course_id' => \App\Models\Course::factory()->create()->id]);

        // Один вопрос с одним правильным и одним неверным ответом:
        // проверка должна отличать их.
        $this->question = Question::factory()->create([
            'aukstructure_id' => $module->id,
            'category_id' => $this->category->id,
        ]);

        $correct = Answer::factory()->create(['question_id' => $this->question->id, 'is_correct' => true]);
        Answer::factory()->create(['question_id' => $this->question->id, 'is_correct' => false]);

        $this->trainee = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
    }

    private function exam(array $attrs = []): Exam
    {
        return Exam::create(array_merge([
            'title' => 'Экзамен по модулю',
            'aukstructure_id' => $this->question->aukstructure_id,
            'category_id' => $this->category->id,
            'group_id' => $this->group->id,
            'max_attempts' => 1,
            'passing_score' => 0.5,
        ], $attrs));
    }

    private function correctAnswerId(): int
    {
        return (int) Answer::where('question_id', $this->question->id)->where('is_correct', true)->value('id');
    }

    private function wrongAnswerId(): int
    {
        return (int) Answer::where('question_id', $this->question->id)->where('is_correct', false)->value('id');
    }

    public function test_correct_answers_are_not_exposed_to_trainee(): void
    {
        $exam = $this->exam();

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $questions = $this->getJson("/api/exams/{$exam->id}/questions")->assertOk()->json('data.questions');

        $this->assertCount(1, $questions);

        foreach ($questions[0]['answers'] as $answer) {
            $this->assertArrayNotHasKey(
                'is_correct',
                $answer,
                'правильность ответа не должна покидать сервер до сдачи'
            );
        }
    }

    public function test_trainee_cannot_read_question_bank_anymore(): void
    {
        // Банк вопросов отдавал is_correct и висел только на auth:sanctum.
        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $this->getJson('/api/questions')->assertStatus(403);
    }

    public function test_question_manager_still_sees_bank(): void
    {
        // Админские инструменты (ExamineMain, QuestionEdit) legitimately
        // need the correct answer, otherwise the bank cannot be edited.
        $this->asUser(['role' => 'Инструктор']);

        $this->getJson('/api/questions')->assertOk();
    }

    public function test_submit_is_graded_on_server(): void
    {
        $exam = $this->exam();

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $response = $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [['question_id' => $this->question->id, 'answer_id' => $this->correctAnswerId()]],
        ]);

        $response->assertStatus(201);

        $this->assertSame(1.0, (float) $response->json('data.score'));
        $this->assertSame(1, $response->json('data.correct_count'));
        $this->assertTrue($response->json('data.passed'));
    }

    public function test_wrong_answers_score_zero(): void
    {
        // Регресс на «клиентская проверка»: раньше результат считался в
        // браузере по is_correct, поэтому в базу попадало ничего.
        $exam = $this->exam();

        $actor = $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $response = $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [['question_id' => $this->question->id, 'answer_id' => $this->wrongAnswerId()]],
        ]);

        $response->assertStatus(201);
        $this->assertSame(0.0, (float) $response->json('data.score'));
        $this->assertFalse($response->json('data.passed'));

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $exam->id,
            'user_id' => $actor->id,
            'correct_count' => 0,
        ]);
    }

    public function test_client_cannot_declare_its_own_score(): void
    {
        // Клиент шлёт answer_id, а не «правильно/неправильно»: иначе
        // результат полностью подконтролен ему.
        $exam = $this->exam();

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [
                ['question_id' => $this->question->id, 'answer_id' => $this->wrongAnswerId(), 'is_correct' => true],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $exam->id,
            'correct_count' => 0,
        ]);
    }

    public function test_attempt_limit_is_enforced_server_side(): void
    {
        $exam = $this->exam(['max_attempts' => 2]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $payload = ['answers' => [['question_id' => $this->question->id, 'answer_id' => $this->correctAnswerId()]]];

        $this->postJson("/api/exams/{$exam->id}/attempts", $payload)->assertStatus(201);
        $this->postJson("/api/exams/{$exam->id}/attempts", $payload)->assertStatus(201);
        // Третья попытка сверх лимита — 403, а не 201.
        $this->postJson("/api/exams/{$exam->id}/attempts", $payload)->assertStatus(403);

        $this->assertSame(2, ExamAttempt::where('exam_id', $exam->id)->count());
    }

    public function test_exam_of_other_group_is_not_available(): void
    {
        $exam = $this->exam(['group_id' => $this->otherGroup->id]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $this->getJson("/api/exams/{$exam->id}/questions")->assertStatus(403);
        $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [['question_id' => $this->question->id, 'answer_id' => $this->correctAnswerId()]],
        ])->assertStatus(403);
    }

    public function test_exam_is_hidden_from_other_group_list(): void
    {
        $mine = $this->exam(['title' => 'Мой экзамен']);
        $this->exam(['title' => 'Чужой экзамен', 'group_id' => $this->otherGroup->id]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $titles = collect($this->getJson('/api/my/exams')->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Мой экзамен'));
        $this->assertFalse($titles->contains('Чужой экзамен'));
    }

    public function test_answers_from_another_question_are_not_counted(): void
    {
        // Иначе можно было бы подсунуть верный ответ на чужой вопрос.
        $exam = $this->exam();

        $otherQuestion = Question::factory()->create([
            'aukstructure_id' => $this->question->aukstructure_id,
            'category_id' => $this->category->id,
        ]);
        $otherCorrect = Answer::factory()->create([
            'question_id' => $otherQuestion->id,
            'is_correct' => true,
        ]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $response = $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [
                ['question_id' => $this->question->id, 'answer_id' => $otherCorrect->id],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertSame(0, $response->json('data.correct_count'), 'ответ чужого вопроса не засчитан');
    }

    public function test_closed_window_is_not_available(): void
    {
        $exam = $this->exam([
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->subDay(),
        ]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $this->getJson("/api/exams/{$exam->id}/questions")->assertStatus(403);
    }

    public function test_future_exam_is_not_available_yet(): void
    {
        $exam = $this->exam(['opens_at' => now()->addDay()]);

        $this->asUser(['role' => 'Обучаем��мый', 'group_id' => $this->group->id]);

        $this->getJson("/api/exams/{$exam->id}/questions")->assertStatus(403);
    }

    public function test_open_exam_is_available(): void
    {
        $exam = $this->exam([
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $this->getJson("/api/exams/{$exam->id}/questions")->assertOk();
    }

    public function test_question_limit_applies_to_both_questions_and_grading(): void
    {
        // Лимит применяется в questionQuery(), поэтому и выдача, и сверка
        // ответов работают с ОДНИМ набором. Если применить его только при
        // выдаче, вопросы показывались бы одни, а засчитывались другие.
        $module = $this->question->aukstructure_id;

        $first = $this->question;
        Answer::factory()->create(['question_id' => $first->id, 'is_correct' => true]);

        $second = Question::factory()->create([
            'aukstructure_id' => $module,
            'category_id' => $this->category->id,
        ]);
        Answer::factory()->create(['question_id' => $second->id, 'is_correct' => true]);

        $exam = $this->exam(['question_limit' => 1]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $questions = $this->getJson("/api/exams/{$exam->id}/questions")->json('data.questions');

        $this->assertCount(1, $questions);
        $this->assertSame($first->id, $questions[0]['id'], 'детерминированно: первый по id');

        // Ответ на второй вопрос не входит в лимит — не засчитывается.
        $secondCorrect = (int) Answer::where('question_id', $second->id)->where('is_correct', true)->value('id');

        $response = $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [
                ['question_id' => $second->id, 'answer_id' => $secondCorrect],
            ],
        ]);

        $this->assertSame(0, $response->json('data.correct_count'));
        $this->assertSame(0, $response->json('data.total_count'));
    }

    public function test_manager_can_create_exam(): void
    {
        $this->asUser(['role' => 'Инструктор']);

        $response = $this->postJson('/api/exams', [
            'title' => 'Итоговый экзамен',
            'group_id' => $this->group->id,
            'aukstructure_id' => $this->question->aukstructure_id,
            'category_id' => $this->category->id,
            'max_attempts' => 3,
            'passing_score' => 0.7,
            'question_limit' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exams', ['title' => 'Итоговый экзамен', 'max_attempts' => 3]);
    }

    public function test_trainee_cannot_create_exam(): void
    {
        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $this->postJson('/api/exams', [
            'title' => 'Свой экзамен',
            'group_id' => $this->group->id,
        ])->assertStatus(403);
    }

    public function test_exam_without_assignment_is_rejected_on_create(): void
    {
        // group_id = null в выборке читается как «все группы», поэтому
        // создать «никому не назначенный» экзамен нельзя: он бы сразу
        // появился у всех.
        $this->asUser(['role' => 'Инструктор']);

        $this->postJson('/api/exams', ['title' => 'Без назначения'])->assertStatus(422);
    }

    public function test_partial_update_keeps_other_fields(): void
    {
        // Регресс: update() использовал правила создания, где title
        // required, поэтому PATCH с одним полем отклонялся «The title
        // field is required» и изменить существующий экзамен было нельзя.
        $exam = $this->exam(['max_attempts' => 2]);

        $this->asUser(['role' => 'Инструктор']);

        $this->patchJson("/api/exams/{$exam->id}", ['question_limit' => 7])->assertOk();

        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'question_limit' => 7,
            'max_attempts' => 2,
            'title' => 'Экзамен по модулю',
        ]);
    }

    public function test_delete_keeps_attempt_history(): void
    {
        $exam = $this->exam();

        $trainee = $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [['question_id' => $this->question->id, 'answer_id' => $this->correctAnswerId()]],
        ])->assertStatus(201);

        $this->asUser(['role' => 'Инструктор']);
        $this->deleteJson("/api/exams/{$exam->id}")->assertStatus(204);

        // Попытка остаётся: экзамен могли снять, а факт сдачи — нет.
        $this->assertDatabaseHas('exam_attempts', ['exam_id' => null, 'user_id' => $trainee->id]);
    }

    public function test_attempts_history_is_own_only(): void
    {
        $exam = $this->exam();

        // Сначала сдаёт «свой» пользователь — именно его историю мы
        // потом и запрашиваем.
        $viewer = $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [['question_id' => $this->question->id, 'answer_id' => $this->correctAnswerId()]],
        ])->assertStatus(201);

        // Затем сдаёт другой обучаемый той же группы: его попытка не
        // должна появляться в истории первого.
        $other = $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $this->postJson("/api/exams/{$exam->id}/attempts", [
            'answers' => [['question_id' => $this->question->id, 'answer_id' => $this->wrongAnswerId()]],
        ])->assertStatus(201);

        $this->assertNotSame($viewer->id, $other->id);

        $this->asExistingUser($viewer);
        $rows = $this->getJson('/api/exam-attempts')->json('data');

        $this->assertCount(1, $rows, 'видна только своя история');
        $this->assertSame($viewer->id, $rows[0]['user_id']);
    }

    public function test_my_exams_requires_authentication(): void
    {
        $this->getJson('/api/my/exams')->assertUnauthorized();
        $this->getJson('/api/exams')->assertUnauthorized();
    }
}

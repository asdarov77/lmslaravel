<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Question\FilterRequest;
use App\Http\Filters\QuestionFilter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Question;
use App\Models\Answer;



class QuestionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    public function index(FilterRequest $request)
    {
        // Правило живёт и в политике, и в middleware маршрута: ответы на
        // вопрос уходят в браузер вместе с признаком правильности, поэтому
        // доступ не должен зависеть от того, каким путём пришёл запрос.
        $this->authorize('viewAny', Question::class);

        $data = $request->validated();
        $questionFilter = app()->make(QuestionFilter::class, ['queryParams' => array_filter($data)]);
        /*
         * Счётчики ответов и верных вариантов считаются двумя withCount,
         * а не на клиенте: иначе фронт знал бы правильность каждого
         * варианта ещё до сдачи экзамена — ровно та утечка, которую мы
         * закрыли в /exams/{id}/questions.
         *
         * JOIN с aukstructures остаётся inner: он же тянет title темы.
         */
        $questions = Question::with('answers')
            ->filter($questionFilter)
            ->join('aukstructures', 'questions.aukstructure_id', '=', 'aukstructures.id')
            ->select(
                'questions.id',
                'questions.category_id',
                'questions.aukstructure_id',
                'questions.question_text',
                'questions.created_at',
                'questions.updated_at',
                'aukstructures.title'
            )
            // withCount обязательно ПОСЛЕ select(): явный select() заменяет
            // список колонок, и колонки подзапросов (answers_count,
            // correct_answers_count) при этом пропадали из ответа.
            ->withCount([
                'answers',
                'answers as correct_answers_count' => fn ($q) => $q->where('is_correct', true),
            ])
            ->get();

        // Нарушения целостности видны сразу в ответе, чтобы страница могла
        // показать предупреждение, а методист не узнал о битом вопросе
        // только на экзамене обучающегося.
        $questions->each(function ($question) {
            $question->integrity = match (true) {
                (int) $question->answers_count === 0 => 'no_answers',
                (int) $question->correct_answers_count === 0 => 'no_correct',
                (int) $question->correct_answers_count > 1 => 'multiple_correct',
                default => null,
            };
        });

        return $questions;
    }

    /**
     * Сводка по банку вопросов: счётчики и нарушения целостности.
     *
     * GET /api/questions/statistics
     *
     * Нужна методисту, чтобы увидеть состояние банка ДО назначения
     * экзамена: сколько вопросов в каждой специальности и теме, есть ли
     * вопросы без ответов или без верного варианта.
     */
    public function statistics()
    {
        $this->authorize('statistics', Question::class);

        $perCategory = DB::table('questions')
            ->select('category_id', DB::raw('count(*) as questions'), DB::raw('count(distinct aukstructure_id) as modules'))
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $perModule = DB::table('questions')
            ->select('aukstructure_id', 'category_id', DB::raw('count(*) as questions'))
            ->groupBy('aukstructure_id', 'category_id')
            ->get()
            ->keyBy('aukstructure_id');

        $answers = DB::table('answers')
            ->select('question_id', DB::raw('count(*) as total'), DB::raw('count(*) filter (where is_correct) as correct'))
            ->groupBy('question_id')
            ->get();

        $withoutAnswers = 0;
        $withoutCorrect = 0;
        $multipleCorrect = 0;

        foreach (DB::table('questions')->pluck('id') as $questionId) {
            $row = $answers->firstWhere('question_id', $questionId);

            $withoutAnswers += $row === null;
            $withoutCorrect += $row !== null && (int) $row->correct === 0;
            $multipleCorrect += $row !== null && (int) $row->correct > 1;
        }

        $issues = $withoutAnswers + $withoutCorrect + $multipleCorrect;

        $categories = Category::orderBy('title')->get(['id', 'title'])->map(function (Category $category) use ($perCategory, $perModule) {
            $stats = $perCategory->get($category->id);

            return [
                'id' => $category->id,
                'title' => $category->title,
                'questions' => (int) ($stats->questions ?? 0),
                'modules' => (int) ($stats->modules ?? 0),
            ];
        });

        $modules = collect($perModule->all())->map(function ($stats) {
            $module = Aukstructure::find($stats->aukstructure_id);

            return [
                'id' => (int) $stats->aukstructure_id,
                'category_id' => (int) $stats->category_id,
                'title' => $module->title ?? 'Модуль удалён',
                'questions' => (int) $stats->questions,
            ];
        })->sortBy('title')->values();

        return response()->json([
            'data' => [
                'totals' => [
                    'questions' => DB::table('questions')->count(),
                    'categories' => $categories->count(),
                    'modules' => $modules->count(),
                    'without_answers' => $withoutAnswers,
                    'without_correct' => $withoutCorrect,
                    'multiple_correct' => $multipleCorrect,
                    // Категории без вопросов видны в списке, но выбрать
                    // тему в них нельзя — об этом стоит сказать заранее.
                    'empty_categories' => $categories->where('questions', 0)->count(),
                ],
                'categories' => $categories,
                'modules' => $modules,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        // $question = new Question;
        // $question->question_text = $request->input('question_text');
        // $question->answers = $request->input('answers');
        // $question->correct_answer = $request->input('correct_answer');
        // $question->save();

        $question = new Question;
        $question->category_id = $request->input('category_id');
        $question->aukstructure_id = $request->input('aukstructure_id');
        $question->question_text = $request->input('question_text');
        $question->save();

        foreach ($request->input('answers') as $answer) {
            $a = new Answer;
            $a->answer = $answer['answer'];
            $a->is_correct = $answer['is_correct'];
            $a->question_id = $question->id;
            $a->save();
        }


        return response()->json(['message' => 'Вопрос создан'], 200);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $question = Question::with('answers')->find($id);

        if (!$question) {
            return response()->json(['message' => 'Вопрос не найден'], 404);
        }

        return response()->json($question);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // $question = Question::find($id);
        // if (!$question) {
        //     return response()->json(['message' => 'Вопрос не найден'], 404);
        // }

        // $question->question_text = $request->input('question_text');
        // $question->answers = $request->input('answers');
        // $question->correct_answer = $request->input('correct_answer');
        // $question->save();

        // return response()->json(['message' => 'Данные вопроса обновлены успешно'], 200);



        $questionData = $request->only(['question_text', 'category_id', 'aukstructure_id']);
        $answersData = $request->input('answers');

        $question = Question::find($id);
        if (!$question) {
            return response()->json(['message' => 'Question not found'], 404);
        }

        $question->question_text = $questionData['question_text'];
        $question->category_id = $questionData['category_id'];
        $question->aukstructure_id = $questionData['aukstructure_id'];
        $question->save();

        // Обновление ответов
        $question->answers()->delete(); // Удаляем все существующие ответы

        foreach ($answersData as $answerData) {
            $answer = new Answer;
            $answer->answer = $answerData['answer'];
            $answer->is_correct = $answerData['is_correct'];
            $answer->question_id = $question->id;
            $answer->save();
        }

        return response()->json(['message' => 'Данные вопроса обновлены успешно'], 200);
        //return "ok update";

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $question = Question::find($id);
        if (!$question) {
            return response()->json(['message' => 'Question not found'], 404);
        }

        $question->answers()->delete(); // Удаляем все ответы, связанные с вопросом
        $question->delete(); // Удаляем сам вопрос

        return response()->json(['message' => 'Question and answers deleted successfully'], 200);
    }

    public function truncate()
    {
        DB::table('questions')->truncate();
        DB::table('answers')->truncate();

        return response()->json([
            'message' => 'Таблица questions & answers была успешно очищена'
        ]);
    }
}

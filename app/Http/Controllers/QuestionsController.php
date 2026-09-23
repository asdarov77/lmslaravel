<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Question\FilterRequest;
use App\Http\Filters\QuestionFilter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validated();
        $questionFilter = app()->make(QuestionFilter::class, ['queryParams' => array_filter($data)]);
        $questions = Question::with('answers')->filter($questionFilter)
            ->join('aukstructures', 'questions.aukstructure_id', '=', 'aukstructures.id')
            ->select('questions.id', 'questions.category_id', 'questions.aukstructure_id', 'questions.question_text', 'questions.created_at', 'questions.updated_at', 'aukstructures.title')
            ->get();
        return $questions;
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

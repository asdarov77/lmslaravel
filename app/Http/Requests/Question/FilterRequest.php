<?php

namespace App\Http\Requests\Question;

use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // nullable: фронт шлёт пустые фильтры (category_id=), а QuestionsController::index
        // сам вызывает array_filter($data) и рассчитан на их отбрасывание.
        // Без nullable пустой фильтр падал в 422 и ломал страницу вопросов.
        return [
            'aukstructure_id' => 'nullable|int',
            'category_id' => 'nullable|int',
            'id' => 'nullable|int',
        ];
    }
}

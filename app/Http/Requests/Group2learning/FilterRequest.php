<?php

namespace App\Http\Requests\Group2learning;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация фильтров учебных записей: group_id, course_id, category_id (int)
 * и course (string).
 */
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
        return [
            'group_id' => 'int',
            'course_id' => 'int',
            'category_id' => 'int',
            'course' => 'string'
        ];
    }
}

<?php

namespace App\Http\Requests\Course;

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
        return [
            'title' => 'string',
            'path' => 'string',
            'aircraft_id' => 'int',
            'category_id' => 'int',
            'parent_id' => 'int',
            'type_id' => 'int',
            'course_id' => 'int',
        ];
    }
}

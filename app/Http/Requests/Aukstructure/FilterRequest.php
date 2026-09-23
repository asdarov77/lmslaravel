<?php

namespace App\Http\Requests\Aukstructure;

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

            'course_id' => 'int',
            'parent_id' => 'int' ,
            'type' => 'int' ,

//            'category_id' => 'int',
//            'group_id' => 'int',

        ];
    }
}

<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Aukstructure;
use App\Models\Question;
use App\Models\Category;

class QuestionFilter extends AbstractFilter
{
    public const CATEGORY_ID = 'category_id';        
    public const AUKSTRUCTURE_ID = 'aukstructure_id';
    public const ID = 'id';

    protected function getCallbacks(): array
    {
        return [
            self::CATEGORY_ID => [$this, 'categoryId'],            
            self::AUKSTRUCTURE_ID => [$this, 'aukstructureId'],
            self::ID => [$this, 'Id'],
        ];
    }


    public function aukstructureId(Builder $builder, $value)
    {
        $builder->where('aukstructure_id', $value);
    }

    public function categoryId(Builder $builder, $value)
    {
        $builder->where('category_id', $value);
    }  
    public function Id(Builder $builder, $value)
    {
        $builder->where('questions.id', $value);
        
    }  
}
<?php

namespace App\Http\Filters;
use Illuminate\Database\Eloquent\Builder;

class AukstructureFilter extends AbstractFilter
{
    public const COURSE_ID = 'course_id';
    public const PARENT_ID = 'parent_id';
    public const TYPE = 'type';


    protected function getCallbacks(): array
    {
        return [
            self::COURSE_ID => [$this, 'courseId'],
            self::PARENT_ID => [$this, 'parentId'],
            self::TYPE => [$this, 'typeId'],
        ];
    }

    public function courseId(Builder $builder, $value)
    {
        $builder->where('course_id', $value);
    }

    public function parentId(Builder $builder, $value)
    {
        $builder->where('parent_id', $value);
    }

    public function typeId(Builder $builder, $value)
    {
        $builder->where('type', $value);
    }

}
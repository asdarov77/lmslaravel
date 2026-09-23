<?php

namespace App\Http\Filters;
use Illuminate\Database\Eloquent\Builder;

class Group2learningFilter extends AbstractFilter
{
    public const COURSE_ID = 'course_id';
    public const CATEGORY_ID = 'category_id';
    public const GROUP_ID = 'group_id';
    public const COURSE = 'course_title';

    protected function getCallbacks(): array
    {
        return [
            self::COURSE_ID => [$this, 'courseId'],
            self::CATEGORY_ID => [$this, 'categoryId'],
            self::GROUP_ID => [$this, 'groupId'],
            self::COURSE => [$this, 'course_title'],
        ];
    }

    public function courseId(Builder $builder, $value)
    {
        $builder->where('course_id', $value)->with('course');
    }

    public function categoryId(Builder $builder, $value)
    {
        $builder->where('category_id', $value);
    }

    public function groupId(Builder $builder, $value)
    {
        $builder->where('group_id', $value)->with('group');
    }
    
    public function course_title(Builder $builder, $value)
    {
        $builder->with('courses', function ($q) use ($value) {
            return $q->where('title','like', "%{$value}%");
        });              
    }

}
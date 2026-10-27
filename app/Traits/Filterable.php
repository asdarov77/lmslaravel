<?php

namespace App\Traits;

use App\Http\Filters\FilterInterface;
use Illuminate\Database\Eloquent\Builder;



/**
 * Добавляет scopeFilter, делегирующий применение фильтров из app/Http/Filters
 * (FilterInterface::apply) к Builder.
 *
 * Общий механизм фильтрации списков. Права здесь не проверяются: область
 * видимости добавляется отдельно в контроллере.
 */
trait Filterable
{
      /**
     * @param Builder $builder
     * @param FilterInterface $filter
     *
     * @return Builder
     */
    public function scopeFilter(Builder $builder, FilterInterface $filter)
    {
        $filter->apply($builder);

        return $builder;
    }
}

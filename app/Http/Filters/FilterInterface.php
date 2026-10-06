<?php

namespace App\Http\Filters;
use Illuminate\Database\Eloquent\Builder;
/**
 * Контракт фильтрации списков: единственный метод apply(Builder $builder).
 *
 * Фильтры применяются через scopeFilter трейта Filterable.
 */
interface FilterInterface
{
    public function apply(Builder $builder);
}
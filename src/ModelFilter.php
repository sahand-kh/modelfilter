<?php

namespace App\Application\Modules\Filter;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Filter
{
    private string $tableName;
    private const PARTIAL_MATCH_PATTERN = "%%%s%%";
    private const START_MATCH_PATTERN = "%s%%";
    private const END_MATCH_PATTERN = "%%%s";

    public const EXACT = 'exact';
    public const PARTIAL = 'partial';
    public const START = 'start';
    public const END = 'end';
    public const EXIST = 'exist';

    /**
     * @param $model
     * @param array|null $queryParams
     * @param bool $returnCollection
     * @return Model|Builder|Collection
     */
    public function filter($model, ?array $queryParams, bool $returnCollection = true): Model|Builder|Collection
    {
        if (!$model instanceof Model && class_exists($model))
            $model = new $model;

        $this->tableName = $model->getTable();

        foreach ($queryParams ?? [] as $searchKey => $value):
            $filter = $model->filtersConfig[$searchKey] ?? null;

            //For properties with several filter rules, name of the filter will be appended to search key
            //Therefore, to get pure search key, filter should be removed
            if (!empty($filter) && !empty($searchKey))
                $searchKey = str_replace(':' . $filter, '', $searchKey);

            $model = match ($filter) {
                static::EXACT => $this->exactMatch($model, $searchKey, $value),
                static::PARTIAL => $this->partialMatch($model, $searchKey, $value, static::PARTIAL_MATCH_PATTERN),
                static::START => $this->partialMatch($model, $searchKey, $value, static::START_MATCH_PATTERN),
                static::END => $this->partialMatch($model, $searchKey, $value, static::END_MATCH_PATTERN),
                static::EXIST => $this->existMatch($model, $searchKey, $value),
                default => $model
            };
        endforeach;

        return $returnCollection ? $model->get() : $model;
    }


    /**
     * @param Model|Builder $model
     * @param $searchKey
     * @param $value
     * @return Model|Builder
     */
    private function exactMatch(Model|Builder $model, $searchKey, $value): Model|Builder
    {
        if (Schema::hasColumn($this->tableName, $searchKey))
            return $this->filterOnExactMatch($value, $model, $searchKey);

        $relations = explode(':', $searchKey);

        $searchKey = array_pop($relations);

        $relations = implode('.', $relations);

        return $model->whereHas($relations, function (Model|Builder $query) use ($searchKey, $value) {
            return $this->filterOnExactMatch($value, $query, $searchKey);
        });
    }


    /**
     * @param Model|Builder $model
     * @param $searchKey
     * @param $value
     * @param $pattern
     * @return Model|Builder
     */
    private function partialMatch(Model|Builder $model, $searchKey, $value, $pattern): Model|Builder
    {
        if (!Schema::hasColumn($this->tableName, $searchKey))
            return $this->filterRelations($model, $searchKey, $value, $pattern);

        if (is_array($value))
            return $this->filterBasedOnArrayParams($model, $searchKey, $value, $pattern);

        return $model->Where($searchKey, 'like', sprintf($pattern, $value));
    }


    /**
     * @param Model|Builder $model
     * @param $searchKey
     * @param $value
     * @return Model|Builder
     */
    private function existMatch(Model|Builder $model, $searchKey, $value): Model|Builder
    {
        if ($value === 'false' || $value === 0 || $value === '0')
            $value = false;
        elseif (is_null($value) || $value === 'true' || $value === 1 || $value === '1')
            $value = true;

        if (!is_bool($value))
            return $model;

        $searchKey = str_replace(':', ".", $searchKey);

        if ($value)
            return $model->has($searchKey);

        return $model->doesntHave($searchKey);
    }


    /**
     * @param Model|Builder $model
     * @param array $value
     * @param $searchKey
     * @param $pattern
     * @return Model|Builder
     */
    private function filterBasedOnArrayParams(Model|Builder $model, $searchKey, array $value, $pattern): Model|Builder
    {
        return $model->Where(function (Model|Builder $query) use ($value, $searchKey, $pattern) {
            foreach ($value as $singleValue)
                $query->orWhere($searchKey, 'like', sprintf($pattern, $singleValue));
        });
    }

    /**
     * @param Model|Builder $model
     * @param $searchKey
     * @param $value
     * @param $pattern
     * @return Model|Builder
     */
    private function filterRelations(Model|Builder $model, $searchKey, $value, $pattern): Model|Builder
    {
        $relations = explode(':', $searchKey);

        $searchKey = array_pop($relations);

        $relations = implode('.', $relations);

        return $model->whereHas($relations, function (Model|Builder $query) use ($searchKey, $value, $pattern) {
            if (is_array($value))
                return $this->filterBasedOnArrayParams($query, $searchKey, $value, $pattern);

            return $query->Where($searchKey, 'like', sprintf($pattern, $value));
        });
    }

    /**
     * @param $value
     * @param Model|Builder $model
     * @param string|null $searchKey
     * @return Model|Builder
     */
    private function filterOnExactMatch($value, Model|Builder $model, ?string $searchKey): Model|Builder
    {
        if (is_array($value))
            return $model->whereIn($searchKey, $value);

        return $model->where($searchKey, $value);
    }

}

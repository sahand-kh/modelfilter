<?php


namespace App\Application\Modules\Filter;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class Filter
{
    private string $tableName;
    private const PARTIAL_MATCH_PATTERN = "%%%s%%";
    private const START_MATCH_PATTERN = "%s%%";
    private const END_MATCH_PATTERN = "%%%s";

    public function filterResults($modelClass, $filterParams, $returnCollection = true)
    {
        $model = new $modelClass;
        $this->tableName = $model->getTable();
        $filterConfigs = $model->filtersConfigs;

        foreach ($filterParams ?? [] as $property => $value):
            $model = match ($filterConfigs[$property] ?? null) {
                'exact' => $this->exactMatchingStrategy($model, $property, $value),
                'partial' => $this->partialMatchingStrategy($model, $property, $value, self::PARTIAL_MATCH_PATTERN),
                'start' => $this->partialMatchingStrategy($model, $property, $value, self::START_MATCH_PATTERN),
                'end' => $this->partialMatchingStrategy($model, $property, $value, self::END_MATCH_PATTERN),
                'exist' => $this->existMatchingStrategy($model, $property, $value),
                default => $model
            };
        endforeach;

        return $returnCollection ? $model->get() : $model;
    }


    private function exactMatchingStrategy($model, $property, $value)
    {
        if (!Schema::hasColumn($this->tableName, $property)):
            $relations = explode(':', $property);
            $property = array_pop($relations);
            $relations = implode('.', $relations);
            return $model->whereHas($relations, function (Builder $query) use ($property, $value) {
                return $this->filterOnExactMatch($value, $query, $property);
            });
        else:
            return $this->filterOnExactMatch($value, $model, $property);
        endif;
    }


    private function partialMatchingStrategy($model, $property, $value, $pattern)
    {
        if (!Schema::hasColumn($this->tableName, $property))
            return $this->filterRelations($model, $property, $value, $pattern);

        if (is_array($value))
            return $this->filterBasedOnArrayParams($model, $property, $value, $pattern);

        return $model->Where($property, 'like', sprintf($pattern,$value));
    }


    private function existMatchingStrategy($model, $property, $value)
    {
        if (is_null($value))
            return $model->has(str_replace(':', ".", $property));
    }


    /**
     * @param $model
     * @param array $value
     * @param $property
     * @param $pattern
     * @return mixed
     */
    private function filterBasedOnArrayParams($model, $property, array $value, $pattern): mixed
    {
        return $model->Where(function (Builder $query) use ($value, $property, $pattern) {
            foreach ($value as $singleValue)
                $query->orWhere($property, 'like', sprintf($pattern, $singleValue));
        });
    }

    /**
     * @param $model
     * @param $property
     * @param $value
     * @param $pattern
     * @return mixed
     */
    private function filterRelations($model, $property, $value, $pattern): mixed
    {
        $relations = explode(':', $property);
        $property = array_pop($relations);
        $relations = implode('.', $relations);
        return $model->whereHas($relations, function (Builder $query) use ($property, $value, $pattern) {
            if (is_array($value))
                return $this->filterBasedOnArrayParams($query, $property, $value, $pattern);
            else
                return $query->Where($property, 'like', sprintf($pattern, $value));
        });
    }

    /**
     * @param $value
     * @param $query
     * @param string|null $property
     * @return mixed
     */
    private function filterOnExactMatch($value, $model, ?string $property): mixed
    {
        if (is_array($value))
            return $model->whereIn($property, $value);
        else
            return $model->where($property, $value);
    }
}

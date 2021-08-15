<?php


namespace App\Application\Modules\Filter;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class Filter
{
    private string $tableName;
    public function __construct(private bool $returnCollection = true)
    {
    }

    public function filterResults($modelClass, $filterParams)
    {
        $model = new $modelClass;
        $this->tableName = $model->getTable();
        $filterConfigs = $model->filtersConfigs;

        foreach ($filterParams ?? [] as $property => $value):
            $model = match ($filterConfigs[$property] ?? null) {
                'exact' => $this->exactMatchingStrategies($model, $property, $value),
                'partial' => $this->partialMatchingStrategies($model, $property, $value, "%%%s%%"),
                'start' => $this->partialMatchingStrategies($model, $property, $value, "%s%%"),
                'end' => $this->partialMatchingStrategies($model, $property, $value, "%%%s"),
                default => $model
            };
        endforeach;

        return $this->returnCollection ? $model->get() : $model;
    }


    private function exactMatchingStrategies($model, $property, $value)
    {
        if (!Schema::hasColumn($this->tableName, $property)):
            $relations = explode(':', $property);
            $property = array_pop($relations);
            $relations = implode('.', $relations);
            return $model->whereHas($relations, function (Builder $query) use ($property, $value) {
                if (is_array($value))
                    return $query->whereIn($property, $value);
                else
                    return $query->where($property, $value);
            });
        elseif (is_array($value)):
            return $model->whereIn($property, $value);
        else:
            return $model->where($property, $value);
        endif;
    }


    private function partialMatchingStrategies($model, $property, $value, $pattern)
    {
        if (!Schema::hasColumn($this->tableName, $property))
            return $this->filterRelations($property, $model, $value, $pattern);

        if (is_array($value))
            return $this->filterBasedOnArrayParams($model, $value, $property, $pattern);

        return $model->Where($property, 'like', sprintf($pattern,$value));
    }

    /**
     * @param $model
     * @param array $value
     * @param $property
     * @param $pattern
     * @return mixed
     */
    private function filterBasedOnArrayParams($model, array $value, $property, $pattern): mixed
    {
        return $model->Where(function (Builder $query) use ($value, $property, $pattern) {
            foreach ($value as $singleValue)
                $query->orWhere($property, 'like', sprintf($pattern, $singleValue));
        });
    }

    /**
     * @param $property
     * @param $model
     * @param $value
     * @param $pattern
     * @return mixed
     */
    private function filterRelations($property, $model, $value, $pattern): mixed
    {
        $relations = explode(':', $property);
        $property = array_pop($relations);
        $relations = implode('.', $relations);
        return $model->whereHas($relations, function (Builder $query) use ($property, $value, $pattern) {
            if (is_array($value))
                return $this->filterBasedOnArrayParams($query, $value, $property, $pattern);
            else
                return $query->Where($property, 'like', sprintf($pattern, $value));
        });
    }
}

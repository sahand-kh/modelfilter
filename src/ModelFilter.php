<?php


namespace Basilisk\ModelFilter;


use Illuminate\Database\Eloquent\Builder;

class ModelFilter
{
    private $returnCollection = true;

    public function __construct(bool $returnCollection = true)
    {
        $this->returnCollection = $returnCollection;
    }

    public function filterResults($modelClass, $filterParams)
    {
        $model = new $modelClass;
        $filterConfigs = $model->filtersConfigs;

        foreach ($filterParams ?? [] as $property => $value):
            $model = match ($filterConfigs[$property] ?? null) {
                'exact' => $this->exactMatchingStrategies($model, $property, $value),
                'partial' => $this->partialMatchingStrategies($model, $property, $value),
                'start' => $this->startMatchingStrategies($model, $property, $value),
                'end' => $this->endMatchingStrategies($model, $property, $value),
                default => $model
            };
        endforeach;

        return $this->returnCollection ? $model->get() : $model;
    }


    protected function exactMatchingStrategies($model, $property, $value)
    {
        if (is_array($value))
            return $model->whereIn($property, $value);
        return $model->where($property, $value);
    }


    protected function partialMatchingStrategies($model, $property, $value)
    {
        if (is_array($value))
            return $model->Where(function (Builder $query) use ($value, $property){
                foreach ($value as $singleValue)
                    $query->orWhere($property, 'like', '%' . $singleValue . '%');
            });

        return $model->Where($property, 'like', '%' . $value . '%');
    }


    protected function startMatchingStrategies($model, $property, $value)
    {
        if (is_array($value))
            return $model->Where(function (Builder $query) use ($value, $property){
                foreach ($value as $singleValue)
                    $query->orWhere($property, 'like', $singleValue . '%');
            });

        return $model->Where($property, 'like', $value . '%');
    }


    protected function endMatchingStrategies($model, $property, $value)
    {
        if (is_array($value))
            return $model->Where(function (Builder $query) use ($value, $property){
                foreach ($value as $singleValue)
                    $query->orWhere($property, 'like', '%' . $singleValue);
            });

        return $model->Where($property, 'like', '%' . $value);
    }
}

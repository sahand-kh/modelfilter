<?php


namespace Basilisk\ModelFilter\Facades;


use Illuminate\Support\Facades\Facade;

class ModelFilterFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ModelFilter';
    }
}

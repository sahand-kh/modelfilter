<?php


namespace Basilisk\ModelFilter;


use Illuminate\Support\ServiceProvider;

class ModelFilterServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('ModelFilter', function ($app){
            return new ModelFilter();
        });
    }
}

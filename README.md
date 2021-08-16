# Laravel Eloquent Model Filter
## Introduction
ModelFilter is a Laravel package for filtering Eloquent models. it is very easy to use and can be configured to match your needs. Lets say you want to enable api to filter results by multiple parameters, ModelFilter can dynamically filter results and return a collection or maybe you want to also do some process or add other conditions after filter is implemented, no problem, ModelFilter can return the model instead of the collection

## Requirements

-   PHP 8.0 
-   Laravel 6.x, 7.x, 8.x


## Installation

#### Install with Composer

    composer require basilisk/modelfilter


## Supported Matching Strategies

 - **exact:** search for fields that are equal to the search param. Numerical fields should always use exact matching strategy
 - **partial:** search for fields that contain the search param.
 - **start:** search for fields that start with the search param.
 - **end:** search for fields that end with the search param.
 - **exist:** in this case the search key is the relation name and search value is a boolean value specifying if we interested in relation existence or absence.


## Configuration
You need to specify filter rules in your model. To do this you have to add a public property called ***$filtersConfigs*** to your models. ***filtersConfigs*** accepts an associative array where keys are specifying fields and relations and values are the search rules. for example imagine we add following ***filtersConfigs*** to the User model:

    public $filtersConfigs = [
    'id' => 'exact', 
    'first_name' =>'partial', 
    'roles:id' => 'exact', 
    'roles' => 'exist'
    ];
As can be seen, you can filter based on properties of the related models, for instance ***'roles:id'*** helps you to filter users based on their role's id. 
You can chain as many relation as you want. For example imagine User has a relation called books and in turn Book model has a relation called category and each category has a property called name. For filtering users based the category name of the books in which user is associated with, you can define following filter rule:

    public $filtersConfigs = [
    'books:category:name' => 'exact'
    ];


## Usage
To implement filters, all you need to do is to call Filter Facade and pass two arguments, first is the Model class which you define ***filtersConfigs*** and the second is an associative array of search keys and values. Although there is no rule for the source of this associative array however in real world scenarios you usually get these key-value pairs from query string:

    ModelFilterFacade::filter(User::class, $request->query())

An example for setting query strings to filter the results could be as follow:

    Base_url/users?books:category:name=fantasy&roles=true
    
You can define several values for a single key in your query string, for instance in following example every User which their name partially match 'sahand' or 'salar' would be selected

    Base_url/users?first_name[]:sahand&first_name[]:salar
 
 
 ## Specifying Return Type
By default **ModelFilter** return a collection of the results. However you may want to do some extra checks on the model before returning the results (for instance you may want to paginate the results). In these cases you can ask ModelFilter to return the model instead of the collection. To do this all you need to do is specify third argument as false when calling the filter method:

    $user = ModelFilterFacade::filter(User::class, $request->query(), false);
    $user->paginate();

 ## Set More than One Rule For A Key
 You may want to define more than one rule for a search key. For instance you may want to define a rule to select user if value provided for first_name exactly matches and another for partially match. To make this happened you need to define rules as you always did but append filter strategy to the search key. 

     public $filtersConfigs = [
    'first_name:exact' =>'exact', 
    'first_name:partial' =>'partial', 
    ];
Now you can specify which filter should be used dynamically and in runtime, for example to filter users based on their first name on exact match you can set query strings as follow:

    Base_url/users?first_name:exact=sahand
and to filter users on partial match you can do as follow:

    Base_url/users?first_name:partial=sah
<?php


$cp_path  = trim(config('cms.cp_path'), '/');

Route::pattern('any', '.*');

$router->group(['middleware' => 'web'], function ($router) use ($cp_path) {
    $router->get($cp_path.'/javascript/{any}', 'Cp\Controller@javascript');
    $router->get($cp_path.'/css/{any}', 'Cp\Controller@css');
    $router->any($cp_path.'{any}', 'Cp\Controller@all');
    $router->get('{any}', 'SiteController@all');
});

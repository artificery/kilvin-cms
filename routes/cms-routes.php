<?php


$cp_path  = trim(config('cms.cp_path'), '/');

Route::pattern('any', '.*');

$router->group(['middleware' => 'web'], function ($router) use ($cp_path) {
    $router->get($cp_path.'/javascript/{any}', 'Cp\Controller@javascript');
    $router->get($cp_path.'/css/{any}', 'Cp\Controller@css');
    $router->get($cp_path.'/docs', 'Cp\DocsController@show');
    $router->get($cp_path.'/docs/{any}', 'Cp\DocsController@show');
    $router->any($cp_path.'{any}', 'Cp\Controller@all');

    // This makes sure the Laravel Debugbar routes work
    if (env('DEBUGBAR_ENABLED') === false || request()->segment(1) != '_debugbar') {
        $router->get('{any}', 'SiteController@all');
    }
});

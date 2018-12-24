<?php

Route::pattern('any', '.*');

$router->group(['middleware' => 'web'], function ($router) {
    $router->get('/installer/javascript/{any}', 'InstallController@javascript');
    $router->get('/installer/css/{any}', 'InstallController@css');
    $router->get('/installer', 'InstallController@all');
    $router->any('/installer/{any}', 'InstallController@all');
});

<?php

Route::pattern('any', '.*');

$router->group(['middleware' => 'web'], function ($router) {
    $router->get('/installer', 'InstallController@all');
    $router->any('/installer/{any}', 'InstallController@all');
});

<?php

use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('admin.home');
    $router->get('wxsendmsg', 'WxMsgController@sendMsg');
    $router->resource('users', WxUserController::class);        // 用户管理
    $router->resource('goods', GoodsController::class);         //
});

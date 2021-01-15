<?php

/*
 * This file is part of Piplin.
 *
 * Copyright (C) 2016-2017 piplin.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Route::group([
    'middleware' => ['auth',],
    'namespace' => 'Dashboard',
], function () {
    Route::get('/', [
        'as' => 'dashboard',
        'uses' => 'DashboardController@index',
    ]);

    Route::get('timeline', [
        'as' => 'dashboard.timeline',
        'uses' => 'DashboardController@timeline',
    ]);

    Route::get('activities', [
        'as' => 'dashboard.activities',
        'uses' => 'DashboardController@activities',
    ]);

    Route::get('projects', [
        'as' => 'dashboard.projects',
        'uses' => 'DashboardController@projects',
    ]);

    Route::get('admin/templates/{template}/commands/{step}', [
        'as' => 'admin.templates.commands.step',
        'uses' => 'CommandController@index',
    ]);
});

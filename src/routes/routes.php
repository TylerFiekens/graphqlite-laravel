<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => config('graphqlite.middleware', ['web']), 'as' => 'graphqlite.'], function () {
    Route::post(config('graphqlite.uri', '/graphql'), 'TheCodingMachine\\GraphQLite\\Laravel\\Controllers\\GraphQLiteController@index')->name('index');
});

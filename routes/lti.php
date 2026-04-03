<?php

use Illuminate\Support\Facades\Route;
use RefBytes\Lti\Http\Controllers\JwksController;
use RefBytes\Lti\Http\Controllers\LaunchController;
use RefBytes\Lti\Http\Controllers\OidcLoginController;

Route::group([
    'prefix' => config('lti.route_prefix', 'lti'),
    'middleware' => config('lti.route_middleware', []),
], function () {
    Route::match(['get', 'post'], '/login', OidcLoginController::class)->name('lti.login');
    Route::post('/launch', LaunchController::class)->name('lti.launch');
    Route::get('/jwks', JwksController::class)->name('lti.jwks');
});

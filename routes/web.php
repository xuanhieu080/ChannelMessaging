<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::match(['GET', 'POST'], '/webhook/facebook', [\App\Http\Controllers\FacebookWebhookController::class, 'handle']);

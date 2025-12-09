<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('privacy-policy', function () {
    return view('privacy-policy');
});

Route::match(['GET', 'POST'], '/webhook/facebook', [\App\Http\Controllers\FacebookWebhookController::class, 'handle']);

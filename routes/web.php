<?php

use App\Http\Controllers\FacebookController;
use App\Http\Controllers\FacebookWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('privacy-policy', function () {
    return view('privacy-policy');
});

Route::match(['GET', 'POST'], '/webhook/facebook', [FacebookWebhookController::class, 'handle']);

Route::prefix('fb')->group(function () {
    Route::get('/threads', [FacebookController::class, 'threads'])->name('fb.threads');
    Route::get('/threads/{threadId}', [FacebookController::class, 'show'])->name('fb.threads.show');
    Route::post('/threads/{threadId}/send', [FacebookController::class, 'send'])->name('fb.threads.send');
});

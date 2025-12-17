<?php

use App\Http\Controllers\EbayController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\FacebookWebhookController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopifyController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('privacy-policy', function () {
    return view('privacy-policy');
});

Route::match(['GET', 'POST'], '/webhook/facebook', [FacebookWebhookController::class, 'handle']);
Route::match(['GET','POST'], '/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);


Route::prefix('fb')->group(function () {
    Route::get('/threads', [FacebookController::class, 'threads'])->name('fb.threads');
    Route::get('/threads/{threadId}', [FacebookController::class, 'show'])->name('fb.threads.show');
    Route::post('/threads/{threadId}/send', [FacebookController::class, 'send'])->name('fb.threads.send');
});

Route::prefix('whatsapp')->group(function () {
    Route::get('/threads', [WhatsAppController::class, 'threads'])->name('wa.threads');
    Route::get('/threads/{threadId}', [WhatsAppController::class, 'show'])->name('wa.threads.show');
    Route::post('/threads/{threadId}/send', [WhatsAppController::class, 'send'])->name('wa.threads.send');
});


Route::prefix('shopify')->group(function () {
    Route::prefix('oauth')->group(function () {
        Route::get('/install', [ShopifyController::class, 'install'])->name('shopify.oauth.install');
        Route::get('/callback', [ShopifyController::class, 'callback'])->name('shopify.oauth.callback');
    });

    Route::get('/orders', [ShopifyController::class, 'index'])->name('shopify.orders');
    Route::get('/orders/{orderId}', [ShopifyController::class, 'show'])->name('shopify.orders.show');

    Route::post('/orders/{orderId}/sync', [ShopifyController::class, 'syncOne'])->name('shopify.orders.syncOne');
    Route::post('/orders/{orderId}/note', [ShopifyController::class, 'updateNote'])->name('shopify.orders.updateNote');

    Route::post('/orders/sync', [ShopifyController::class, 'syncAll'])->name('shopify.orders.syncAll');
});

Route::prefix('ebay')->group(function () {
    Route::get('/threads', [EbayController::class, 'threads'])->name('ebay.threads');
    Route::get('/threads/{threadId}', [EbayController::class, 'show'])->name('ebay.threads.show');
    Route::post('/threads/{threadId}/send', [EbayController::class, 'send'])->name('ebay.threads.send');
});

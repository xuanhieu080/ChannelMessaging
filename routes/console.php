<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('message-hub:sync-ebay --page-limit=10')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
Schedule::command('message-hub:sync-shopify')
    ->everyTenMinutes()
    ->withoutOverlapping(10);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

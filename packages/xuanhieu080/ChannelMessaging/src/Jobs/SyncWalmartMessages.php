<?php

namespace xuanhieu080\ChannelMessaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use xuanhieu080\ChannelMessaging\Services\Walmart\WalmartMessageService;

class SyncWalmartMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WalmartMessageService $service): void
    {
        $service->syncFromEmail();
    }
}

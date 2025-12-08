<?php

namespace xuanhieu080\ChannelMessaging\Jobs;

use xuanhieu080\ChannelMessaging\Services\WalmartMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWalmartMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WalmartMessageService $service): void
    {
        $service->syncFromEmail();
    }
}

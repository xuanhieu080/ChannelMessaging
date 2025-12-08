<?php

namespace xuanhieu080\ChannelMessaging\Jobs;

use xuanhieu080\ChannelMessaging\Services\FacebookMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncFacebookMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FacebookMessageService $service): void
    {
        $service->syncMessages();
    }
}

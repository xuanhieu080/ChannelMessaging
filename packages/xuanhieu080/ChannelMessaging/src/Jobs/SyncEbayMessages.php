<?php

namespace xuanhieu080\ChannelMessaging\Jobs;

use xuanhieu080\ChannelMessaging\Services\EbayMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEbayMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(EbayMessageService $service): void
    {
        $from = now()->subDays(2);
        $to   = now();

        $service->syncMessages($from, $to);
    }
}

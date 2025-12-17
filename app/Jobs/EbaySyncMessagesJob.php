<?php

namespace App\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use xuanhieu080\ChannelMessaging\Services\EbayMessageService;

class EbaySyncMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $fromIso,
        public string $toIso,
        public int $pageLimit = 10,
        public string $runId = ''
    ) {}

    public function handle(EbayMessageService $ebay): void
    {
        $from = new \DateTimeImmutable($this->fromIso);
        $to   = new \DateTimeImmutable($this->toIso);

        $stats = $ebay->syncMessages($from, $to, $this->pageLimit);

        // cộng dồn thống kê cho command
        $key = "message_hub:ebay:run:{$this->runId}";
        Cache::put($key, [
            'processed' => (Cache::get($key)['processed'] ?? 0) + ($stats['processed'] ?? 0),
            'created'   => (Cache::get($key)['created'] ?? 0) + ($stats['created'] ?? 0),
            'updated'   => (Cache::get($key)['updated'] ?? 0) + ($stats['updated'] ?? 0),
        ], now()->addHours(2));
    }
}

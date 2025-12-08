<?php

namespace xuanhieu080\ChannelMessaging\Console\Commands;

use Illuminate\Console\Command;
use xuanhieu080\ChannelMessaging\Jobs\SyncEbayMessages;

class SyncEbayMessagesCommand extends Command
{
    protected $signature = 'message-hub:sync-ebay {--now}';
    protected $description = 'Sync eBay messages into channel_messages table';

    public function handle(): int
    {
        if ($this->option('now')) {
            dispatch_sync(new SyncEbayMessages());
            $this->info('eBay messages synced (sync).');
        } else {
            dispatch(new SyncEbayMessages());
            $this->info('eBay messages sync job dispatched.');
        }

        return self::SUCCESS;
    }
}

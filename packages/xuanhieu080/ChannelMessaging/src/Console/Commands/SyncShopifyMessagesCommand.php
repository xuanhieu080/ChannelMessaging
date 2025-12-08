<?php

namespace xuanhieu080\ChannelMessaging\Console\Commands;

use Illuminate\Console\Command;
use xuanhieu080\ChannelMessaging\Jobs\SyncShopifyMessages;

class SyncShopifyMessagesCommand extends Command
{
    protected $signature = 'message-hub:sync-shopify {--now}';
    protected $description = 'Sync Shopify order notes into channel_messages table';

    public function handle(): int
    {
        if ($this->option('now')) {
            dispatch_sync(new SyncShopifyMessages());
            $this->info('Shopify messages synced (sync).');
        } else {
            dispatch(new SyncShopifyMessages());
            $this->info('Shopify messages sync job dispatched.');
        }

        return self::SUCCESS;
    }
}

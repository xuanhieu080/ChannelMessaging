<?php

namespace xuanhieu080\ChannelMessaging\Console\Commands;

use Illuminate\Console\Command;
use xuanhieu080\ChannelMessaging\Jobs\SyncWalmartMessages;

class SyncWalmartMessagesCommand extends Command
{
    protected $signature = 'message-hub:sync-walmart {--now}';
    protected $description = 'Sync Walmart email messages into channel_messages table';

    public function handle(): int
    {
        if ($this->option('now')) {
            dispatch_sync(new SyncWalmartMessages());
            $this->info('Walmart messages synced (sync).');
        } else {
            dispatch(new SyncWalmartMessages());
            $this->info('Walmart messages sync job dispatched.');
        }

        return self::SUCCESS;
    }
}

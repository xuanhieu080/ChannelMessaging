<?php

namespace xuanhieu080\ChannelMessaging\Console\Commands;

use Illuminate\Console\Command;
use xuanhieu080\ChannelMessaging\Jobs\SyncFacebookMessages;

class SyncFacebookMessagesCommand extends Command
{
    protected $signature = 'message-hub:sync-facebook {--now}';
    protected $description = 'Sync Facebook messages into channel_messages table';

    public function handle(): int
    {
        if ($this->option('now')) {
            dispatch_sync(new SyncFacebookMessages());
            $this->info('Facebook messages synced (sync).');
        } else {
            dispatch(new SyncFacebookMessages());
            $this->info('Facebook messages sync job dispatched.');
        }

        return self::SUCCESS;
    }
}

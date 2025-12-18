<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use xuanhieu080\ChannelMessaging\Models\WalmartAccount;
use xuanhieu080\ChannelMessaging\Services\Walmart\WalmartOrderSyncService;

class WalmartSyncOrders extends Command
{
    protected $signature = 'walmart:sync-orders
        {accountId? : Walmart account id}
        {--from= : YYYY-mm-dd}
        {--to= : YYYY-mm-dd}
        {--limit=100 : page limit}';

    protected $description = 'Sync Walmart orders into local database';

    public function handle(WalmartOrderSyncService $sync): int
    {
        $accountId = $this->argument('accountId');

        $accounts = WalmartAccount::query()
            ->where('is_active', true)
            ->when($accountId, fn($q) => $q->whereKey($accountId))
            ->get();

        if ($accounts->isEmpty()) {
            $this->error('No active Walmart accounts found.');
            return self::FAILURE;
        }

        $from = $this->option('from') ? now()->parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? now()->parse($this->option('to'))->endOfDay() : null;
        $limit = (int)$this->option('limit');

        foreach ($accounts as $acc) {
            $this->info("Syncing account #{$acc->id} {$acc->name} ...");
            $result = $sync->sync($acc, $from, $to, $limit);
            $this->info("=> synced {$result['synced_orders']} orders ({$result['from']} -> {$result['to']})");
        }

        return self::SUCCESS;
    }
}

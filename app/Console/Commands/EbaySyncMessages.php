<?php

namespace App\Console\Commands;

use App\Jobs\EbaySyncMessagesJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use xuanhieu080\ChannelMessaging\Services\EbayMessageService;

class EbaySyncMessages extends Command
{
    protected $signature = 'message-hub:sync-ebay
        {--from= : ISO datetime (UTC). Example: 2025-12-16T00:00:00Z}
        {--to= : ISO datetime (UTC). Default now()}
        {--days=7 : If no last sync, sync last N days}
        {--page-limit=10 : Max pages to fetch per run}
        {--direct : Run sync directly in command (no queue)}
        {--dry-run : Do not write last_sync}
        {--force : Ignore lock and last sync}';

    protected $description = 'Sync eBay messages (Trading API GetMyMessages)';

    public function handle(EbayMessageService $ebay): int
    {
        $lockKey = 'message_hub:sync_ebay:lock';
        $lastKey = 'message_hub:sync_ebay:last_at';

        $lock = null;
//        if (!$this->option('force')) {
//            $lock = Cache::lock($lockKey, 300);
//            if (!$lock->get()) {
//                $this->warn('Sync is already running (lock active).');
//                return self::SUCCESS;
//            }
//        }

        $runId = 'run_' . now()->format('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 8);
        $runKey = "message_hub:ebay:run:{$runId}";
        Cache::put($runKey, ['processed' => 0, 'created' => 0, 'updated' => 0], now()->addHours(2));

        try {
            [$from, $to] = $this->resolveWindow($lastKey);

            $pageLimit = max(1, (int) $this->option('page-limit'));

            $this->info("Sync window: {$from->toIso8601String()} -> {$to->toIso8601String()}");
            $this->info("Page limit: {$pageLimit}");
            $this->info($this->option('direct') ? 'Mode: DIRECT' : 'Mode: QUEUE');

            if (true || $this->option('direct')) {
                $stats = $ebay->syncMessages($from, $to, $pageLimit);

                $this->line("Processed: " . ($stats['processed'] ?? 0));
                $this->line("Created  : " . ($stats['created'] ?? 0));
                $this->line("Updated  : " . ($stats['updated'] ?? 0));
            } else {
                // dispatch 1 job cho window này (nhẹ, ổn định)
                EbaySyncMessagesJob::dispatch(
                    $from->toIso8601String(),
                    $to->toIso8601String(),
                    $pageLimit,
                    $runId
                );

                $this->info("Dispatched job. Run ID: {$runId}");
                $this->line("Tip: check stats via cache key: {$runKey}");
            }

            if (!$this->option('dry-run')) {
                Cache::forever($lastKey, $to->copy()->subMinutes(2)->toIso8601String());
            } else {
                $this->warn('Dry-run: last_sync NOT updated.');
            }

            // nếu direct thì in stats ngay; nếu queue thì user xem cache / logs
            $acc = Cache::get($runKey);
            if ($this->option('direct') && is_array($acc)) {
                // direct đã in, thôi.
            }

            $this->info('Done.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }

    private function resolveWindow(string $lastKey): array
    {
        $toOpt = $this->option('to');
        $to = $toOpt ? Carbon::parse($toOpt)->utc() : now()->utc();

        $fromOpt = $this->option('from');
        if ($fromOpt) {
            $from = Carbon::parse($fromOpt)->utc();
            return [$from, $to];
        }

        if ($this->option('force')) {
            $days = (int) $this->option('days');
            $from = now()->utc()->subDays(max(1, $days));
            return [$from, $to];
        }

        $last = Cache::get($lastKey);
        if ($last) {
            $from = Carbon::parse($last)->utc();
        } else {
            $days = (int) $this->option('days');
            $from = now()->utc()->subDays(max(1, $days));
        }

        if ($from->greaterThanOrEqualTo($to)) {
            $from = $to->copy()->subHours(1);
        }

        return [$from, $to];
    }
}

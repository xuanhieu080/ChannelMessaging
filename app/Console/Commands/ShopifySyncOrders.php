<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Services\ShopifyMessageService;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class ShopifySyncOrders extends Command
{
    protected $signature = 'message-hub:sync-shopify
        {--order-id= : Sync only one order by ID}
        {--from= : ISO datetime (UTC). Example: 2025-12-16T00:00:00Z}
        {--to= : ISO datetime (UTC). Default now()}
        {--days=7 : If no from/to, sync last N days}
        {--dry-run : Do not write data}
        {--force : Ignore lock}';

    protected $description = 'Sync Shopify orders notes into channel_messages';

    public function handle(ShopifyMessageService $svc): int
    {
        $lockKey = 'message_hub:sync_shopify:lock';

        $lock = null;
        if (!$this->option('force')) {
            $lock = Cache::lock($lockKey, 300); // 5 phút
            if (!$lock->get()) {
                $this->warn('Shopify sync is already running.');
                return self::SUCCESS;
            }
        }

        try {
            // ===== Sync single order =====
            if ($orderId = $this->option('order-id')) {
                return $this->syncOneOrder((string)$orderId);
            }

            // ===== Resolve window =====
            [$from, $to] = $this->resolveWindow();

            $this->info('Shopify sync window:');
            $this->line('  From: ' . $from->toIso8601String());
            $this->line('  To  : ' . $to->toIso8601String());

            if ($this->option('dry-run')) {
                $this->warn('Dry-run enabled: no DB write');
            }

            // Shopify Admin API không filter by updated_at tốt cho orders list (cursor),
            // nên service syncOrderNotes() sẽ pull paging toàn bộ.
            // => window dùng để filter ở tầng store (nếu muốn nâng cấp sau).
            $beforeCount = ChannelMessage::where('source', 'shopify')->count();

            if (!$this->option('dry-run')) {
                $svc->syncOrderNotes();
            }

            $afterCount = ChannelMessage::where('source', 'shopify')->count();

            $this->info('Sync completed.');
            $this->line('New records: ' . max(0, $afterCount - $beforeCount));

            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('Shopify sync failed', ['error' => $e->getMessage()]);
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Sync a single order by ID
     */
    protected function syncOneOrder(string $orderId): int
    {
        $this->info("Sync Shopify order: {$orderId}");

        $baseUrl = rtrim((string) config('message-hub.shopify.base_url'), '/');
        $token   = (string) config('message-hub.shopify.admin_token');
        $ver     = (string) config('message-hub.shopify.api_version', '2024-10');

        if (!$baseUrl || !$token) {
            $this->error('Missing SHOPIFY_BASE_URL or SHOPIFY_ADMIN_TOKEN');
            return self::FAILURE;
        }

        $url = "{$baseUrl}/admin/api/{$ver}/orders/{$orderId}.json";

        $res = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->get($url);

        if (!$res->ok()) {
            $this->error('Fetch order failed: ' . $res->body());
            return self::FAILURE;
        }

        $order = $res->json('order');
        if (!$order) {
            $this->error('Order not found in response');
            return self::FAILURE;
        }

        if (!empty($order['note']) && !$this->option('dry-run')) {
            ChannelMessage::updateOrCreate(
                [
                    'source'      => 'shopify',
                    'external_id' => 'order-note-' . $order['id'],
                ],
                [
                    'thread_id' => (string) $order['id'],
                    'sender'    => $order['customer']['email'] ?? ($order['email'] ?? null),
                    'receiver'  => 'shop',
                    'direction' => 'in',
                    'subject'   => 'Shopify order note ' . ($order['name'] ?? ''),
                    'body'      => (string) $order['note'],
                    'sent_at'   => isset($order['created_at'])
                        ? new \DateTime($order['created_at'])
                        : now(),
                    'raw_json'  => json_encode($order),
                ]
            );
        }

        $this->info('Order synced successfully.');
        return self::SUCCESS;
    }

    protected function resolveWindow(): array
    {
        $toOpt = $this->option('to');
        $to = $toOpt ? Carbon::parse($toOpt)->utc() : now()->utc();

        $fromOpt = $this->option('from');
        if ($fromOpt) {
            return [Carbon::parse($fromOpt)->utc(), $to];
        }

        $days = (int) $this->option('days');
        return [now()->utc()->subDays(max(1, $days)), $to];
    }
}

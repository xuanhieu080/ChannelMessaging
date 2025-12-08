<?php

namespace xuanhieu080\ChannelMessaging\Services;

use xuanhieu080\ChannelMessaging\Models\ChannelMessage;
use Illuminate\Support\Facades\Http;

class ShopifyMessageService
{
    protected string $baseUrl;
    protected string $token;
    protected string $apiVersion;

    public function __construct()
    {
        $this->baseUrl    = rtrim(config('message-hub.shopify.base_url'), '/');
        $this->token      = config('message-hub.shopify.admin_token');
        $this->apiVersion = config('message-hub.shopify.api_version', '2024-10');
    }

    public function syncOrderNotes(): void
    {
        if (!$this->baseUrl || !$this->token) {
            return;
        }

        $pageInfo = null;

        do {
            $query = [
                'status' => 'any',
                'limit'  => 250,
            ];
            if ($pageInfo) {
                $query['page_info'] = $pageInfo;
            }

            $url = "{$this->baseUrl}/admin/api/{$this->apiVersion}/orders.json";

            $res = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type'           => 'application/json',
            ])->get($url, $query);

            if (!$res->ok()) {
                break;
            }

            $orders = $res->json('orders') ?? [];

            foreach ($orders as $order) {
                if (!empty($order['note'])) {
                    $this->storeNoteAsMessage($order);
                }
            }

            $pageInfo = $this->extractNextPageInfo($res->header('Link'));
        } while ($pageInfo);
    }

    protected function storeNoteAsMessage(array $order): void
    {
        $externalId = 'order-note-'.$order['id'];

        ChannelMessage::updateOrCreate(
            [
                'source'      => 'shopify',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => (string) $order['id'],
                'sender'    => $order['customer']['email'] ?? null,
                'receiver'  => 'shop',
                'direction' => 'in',
                'subject'   => 'Shopify order note '.$order['name'],
                'body'      => $order['note'],
                'sent_at'   => isset($order['created_at'])
                    ? new \DateTime($order['created_at'])
                    : now(),
                'raw_json'  => json_encode($order),
            ]
        );
    }

    protected function extractNextPageInfo(?string $link): ?string
    {
        if (!$link) {
            return null;
        }

        if (preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $link, $m)) {
            return $m[1];
        }

        return null;
    }
}

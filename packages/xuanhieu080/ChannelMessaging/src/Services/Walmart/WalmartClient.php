<?php

namespace xuanhieu080\ChannelMessaging\Services\Walmart;


use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use xuanhieu080\ChannelMessaging\Models\WalmartAccount;

class WalmartClient
{
    public function http(WalmartAccount $account): PendingRequest
    {
        $token = $this->getAccessToken($account);

        return Http::baseUrl(config('services.walmart.base_url'))
            ->acceptJson()
            ->timeout(60)
            ->withHeaders([
                // Walmart Marketplace header dùng access token kiểu này :contentReference[oaicite:3]{index=3}
                'WM_SEC.ACCESS_TOKEN' => $token,
                'WM_CONSUMER.CHANNEL.TYPE' => (string) config('services.walmart.channel_type', '0'),
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                'WM_SVC.NAME' => (string) config('services.walmart.svc_name', 'YourApp'),
            ]);
    }

    public function getAccessToken(WalmartAccount $account): string
    {
        if ($account->access_token && $account->token_expires_at && $account->token_expires_at->isFuture()) {
            return $account->access_token;
        }

        // Token API thường: POST /v3/token dùng client_id/client_secret :contentReference[oaicite:4]{index=4}
        if (!$account->client_id || !$account->client_secret) {
            throw new RuntimeException("Walmart account {$account->id} missing client_id/client_secret.");
        }

        $basic = base64_encode($account->client_id . ':' . $account->client_secret);

        $res = Http::baseUrl(config('services.walmart.base_url'))
            ->asForm()
            ->timeout(60)
            ->withHeaders([
                'Authorization' => "Basic {$basic}",
                'WM_QOS.CORRELATION_ID' => (string) Str::uuid(),
                'WM_SVC.NAME' => (string) config('services.walmart.svc_name', 'YourApp'),
            ])
            ->post('/v3/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (!$res->ok()) {
            throw new RuntimeException("Token API failed: HTTP {$res->status()} - ".$res->body());
        }

        $data = $res->json();
        $token = $data['access_token'] ?? null;
        $expiresIn = (int)($data['expires_in'] ?? 900);

        if (!$token) {
            throw new RuntimeException("Token API missing access_token: ".$res->body());
        }

        // trừ hao 60s
        $account->forceFill([
            'access_token' => $token,
            'token_expires_at' => now()->addSeconds(max(60, $expiresIn - 60)),
        ])->save();

        return $token;
    }

    /**
     * Lấy orders (ví dụ endpoint phổ biến):
     * GET /v3/orders?createdStartDate=...&createdEndDate=...&limit=...
     * (Bạn có thể chỉnh endpoint/params theo market docs của bạn)
     */
    public function getOrders(WalmartAccount $account, array $params = []): array
    {
        $res = $this->http($account)->get('/v3/orders', $params);

        if (!$res->ok()) {
            throw new RuntimeException("Orders API failed: HTTP {$res->status()} - ".$res->body());
        }

        return $res->json();
    }

    public function getOrderDetail(WalmartAccount $account, string $purchaseOrderId): array
    {
        $res = $this->http($account)->get("/v3/orders/{$purchaseOrderId}");

        if (!$res->ok()) {
            throw new RuntimeException("Order detail API failed: HTTP {$res->status()} - ".$res->body());
        }

        return $res->json();
    }
}

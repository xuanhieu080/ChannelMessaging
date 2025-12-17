<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;
use xuanhieu080\ChannelMessaging\Services\ShopifyMessageService;

class ShopifyController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // group by thread_id (= order_id)
        $rows = ChannelMessage::query()
            ->where('source', 'shopify')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('thread_id', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%")
                        ->orWhere('sender', 'like', "%{$q}%");
                });
            })
            ->select([
                'thread_id',
                DB::raw('MAX(sent_at) as last_sent_at'),
                DB::raw('MAX(id) as last_id'),
                DB::raw('COUNT(*) as msg_count'),
            ])
            ->groupBy('thread_id')
            ->orderByDesc('last_sent_at')
            ->paginate(30)
            ->appends($request->query());

        $lastIds = $rows->pluck('last_id')->filter()->values()->all();
        $lastMap = ChannelMessage::query()->whereIn('id', $lastIds)->get()->keyBy('id');

        return view('shopify.orders.index', [
            'rows' => $rows,
            'lastMap' => $lastMap,
            'q' => $q,
        ]);
    }

    public function show(Request $request, string $orderId)
    {
        $messages = ChannelMessage::query()
            ->where('source', 'shopify')
            ->where('thread_id', $orderId)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->paginate(50)
            ->appends($request->query());

        // Lấy order info từ raw_json của message gần nhất (nếu có)
        $latest = ChannelMessage::query()
            ->where('source', 'shopify')
            ->where('thread_id', $orderId)
            ->orderByDesc('sent_at')
            ->first();

        $order = null;
        if ($latest?->raw_json) {
            $order = json_decode($latest->raw_json, true);
        }

        return view('shopify.orders.show', [
            'orderId' => $orderId,
            'messages' => $messages,
            'order' => $order,
        ]);
    }

    /**
     * Sync all orders notes (calls your service)
     */
    public function syncAll(ShopifyMessageService $svc)
    {
        $svc->syncOrderNotes();
        return back()->with('status', 'Đã sync Shopify orders (notes)!');
    }

    /**
     * Sync ONE order by id (fetch /orders/{id}.json then store note)
     */
    public function syncOne(Request $request, string $orderId)
    {
        $res = $this->fetchOrder($orderId);

        if (!$res['ok']) {
            return back()->withErrors(['sync' => $res['error'] ?? 'Sync failed']);
        }

        $order = $res['order'];

        if (!empty($order['note'])) {
            $this->storeOrderNote($order);
        }

        return back()->with('status', 'Đã sync order từ Shopify!');
    }

    /**
     * Update order.note on Shopify + store as OUT message
     */
    public function updateNote(Request $request, string $orderId)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'mode' => ['nullable', 'in:replace,append'],
        ]);

        $mode = $data['mode'] ?? 'append';
        $noteText = $data['note'];

        // pull current note first
        $resOrder = $this->fetchOrder($orderId);
        if (!$resOrder['ok']) {
            return back()->withErrors(['note' => $resOrder['error'] ?? 'Cannot fetch order']);
        }

        $order = $resOrder['order'];
        $currentNote = (string) ($order['note'] ?? '');

        $newNote = $mode === 'replace'
            ? $noteText
            : trim($currentNote . "\n\n---\n" . $noteText);

        $res = $this->putOrderNote($orderId, $newNote);

        if (!$res['ok']) {
            return back()->withErrors(['note' => $res['error'] ?? 'Update note failed'])->withInput();
        }

        // store OUT message (staff note)
        ChannelMessage::query()->create([
            'source'      => 'shopify',
            'external_id' => 'order-note-out-' . $orderId . '-' . uniqid(),
            'thread_id'   => (string) $orderId,
            'sender'      => 'staff',
            'receiver'    => 'shop',
            'direction'   => 'out',
            'subject'     => 'Update Shopify order note',
            'body'        => $noteText,
            'sent_at'     => now(),
            'raw_json'    => json_encode($res['raw'] ?? []),
        ]);

        // store latest note as IN snapshot too (optional, but useful)
        $order['note'] = $newNote;
        $this->storeOrderNote($order);

        return redirect()->route('shopify.orders.show', $orderId)->with('status', 'Đã cập nhật note lên Shopify!');
    }

    // ===================== Helpers =====================

    protected function storeOrderNote(array $order): void
    {
        $externalId = 'order-note-' . $order['id'];

        ChannelMessage::query()->updateOrCreate(
            [
                'source'      => 'shopify',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => (string) $order['id'],
                'sender'    => $order['customer']['email'] ?? ($order['email'] ?? null),
                'receiver'  => 'shop',
                'direction' => 'in',
                'subject'   => 'Shopify order note ' . ($order['name'] ?? ('#' . $order['id'])),
                'body'      => (string) ($order['note'] ?? ''),
                'sent_at'   => isset($order['created_at']) ? new \DateTime($order['created_at']) : now(),
                'raw_json'  => json_encode($order),
            ]
        );
    }

    protected function shopifyHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => config('message-hub.shopify.admin_token'),
            'Content-Type' => 'application/json',
        ];
    }

    protected function fetchOrder(string $orderId): array
    {
        $baseUrl = rtrim((string) config('message-hub.shopify.base_url'), '/');
        $token = (string) config('message-hub.shopify.admin_token');
        $ver = (string) config('message-hub.shopify.api_version', '2024-10');

        if (!$baseUrl || !$token) {
            return ['ok' => false, 'error' => 'Missing SHOPIFY_BASE_URL or SHOPIFY_ADMIN_TOKEN'];
        }

        $url = "{$baseUrl}/admin/api/{$ver}/orders/{$orderId}.json";

        $res = Http::withHeaders($this->shopifyHeaders())->get($url);

        if (!$res->ok()) {
            Log::error('Shopify fetch order failed', ['status' => $res->status(), 'body' => $res->body()]);
            return ['ok' => false, 'error' => $res->body()];
        }

        $order = $res->json('order');
        if (!$order) {
            return ['ok' => false, 'error' => 'Order not found in response'];
        }

        return ['ok' => true, 'order' => $order];
    }

    protected function putOrderNote(string $orderId, string $note): array
    {
        $baseUrl = rtrim((string) config('message-hub.shopify.base_url'), '/');
        $token = (string) config('message-hub.shopify.admin_token');
        $ver = (string) config('message-hub.shopify.api_version', '2024-10');

        if (!$baseUrl || !$token) {
            return ['ok' => false, 'error' => 'Missing SHOPIFY_BASE_URL or SHOPIFY_ADMIN_TOKEN'];
        }

        $url = "{$baseUrl}/admin/api/{$ver}/orders/{$orderId}.json";

        $res = Http::withHeaders($this->shopifyHeaders())->put($url, [
            'order' => [
                'id' => (int) $orderId,
                'note' => $note,
            ],
        ]);

        if (!$res->ok()) {
            Log::error('Shopify update note failed', ['status' => $res->status(), 'body' => $res->body()]);
            return ['ok' => false, 'error' => $res->body(), 'raw' => $res->json()];
        }

        return ['ok' => true, 'raw' => $res->json()];
    }


    /// Oauth
    /**
     * Step 1: tạo URL authorize
     * /shopify/oauth/install?shop=xxx.myshopify.com
     */
    public function install(Request $request)
    {
        $shop = $request->query('shop');

        if (!$this->isValidShopDomain($shop)) {
            abort(400, 'Invalid shop domain');
        }

        $clientId    = config('message-hub.shopify.client_id');
        $scopes      = config('message-hub.shopify.scopes');
        $redirectUri = config('message-hub.shopify.redirect_uri');

        if (!$clientId || !$redirectUri) {
            abort(500, 'Missing SHOPIFY_CLIENT_ID or SHOPIFY_REDIRECT_URI');
        }

        $state = bin2hex(random_bytes(16));
        session(['shopify_oauth_state' => $state]);

        $params = http_build_query([
            'client_id'    => $clientId,
            'scope'        => $scopes,
            'redirect_uri' => $redirectUri,
            'state'        => $state,
        ]);

        $url = "https://{$shop}/admin/oauth/authorize?{$params}";

        return redirect()->away($url);
    }

    /**
     * Step 2: Shopify redirect về callback
     */
    public function callback(Request $request)
    {
        $shop  = $request->query('shop');
        $code  = $request->query('code');
        $hmac  = $request->query('hmac');
        $state = $request->query('state');

        if (!$shop || !$code || !$hmac) {
            abort(400, 'Missing required params');
        }

        if ($state !== session('shopify_oauth_state')) {
            abort(403, 'Invalid state');
        }

        if (!$this->verifyHmac($request->query(), config('message-hub.shopify.client_secret'))) {
            abort(403, 'HMAC verification failed');
        }

        // Step 3: đổi code lấy access_token
        $tokenRes = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id'     => config('message-hub.shopify.client_id'),
            'client_secret' => config('message-hub.shopify.client_secret'),
            'code'          => $code,
        ]);

        if (!$tokenRes->ok()) {
            Log::error('Shopify OAuth token failed', ['body' => $tokenRes->body()]);
            abort(500, 'Cannot get access token');
        }

        $accessToken = $tokenRes->json('access_token');
        $scopes      = $tokenRes->json('scope');

        /**
         * 👉 Ở đây bạn có thể:
         * - Lưu DB (recommended)
         * - Hoặc set vào env/config
         */
        // Ví dụ demo: log ra
        Log::info('Shopify OAuth success', [
            'shop' => $shop,
            'access_token' => $accessToken,
            'scopes' => $scopes,
        ]);

        return response()->json([
            'message' => 'OAuth success',
            'shop' => $shop,
            'access_token' => $accessToken,
            'scopes' => $scopes,
        ]);
    }

    // ================= helpers =================

    protected function isValidShopDomain(?string $shop): bool
    {
        if (!$shop) return false;
        return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $shop) === 1;
    }

    protected function verifyHmac(array $params, string $secret): bool
    {
        $hmac = $params['hmac'] ?? '';
        unset($params['hmac'], $params['signature']);

        ksort($params);
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $calculated = hash_hmac('sha256', $query, $secret);

        return hash_equals($hmac, $calculated);
    }
}

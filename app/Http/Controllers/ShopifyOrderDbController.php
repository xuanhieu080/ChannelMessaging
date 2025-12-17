<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopifyOrderIndexRequest;
use App\Http\Requests\ShopifyOrderUpdateNoteRequest;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelOrder;
use xuanhieu080\ChannelMessaging\Models\ChannelOrderAddress;
use xuanhieu080\ChannelMessaging\Models\ChannelOrderFulfillment;
use xuanhieu080\ChannelMessaging\Models\ChannelOrderItem;

class ShopifyOrderDbController extends Controller
{
    // ===== LIST ORDERS FROM DB =====
    public function index(ShopifyOrderIndexRequest $request)
    {
        $perPage = (int) ($request->validated('per_page') ?? 30);
        $q = trim((string) ($request->validated('q') ?? ''));

        $query = ChannelOrder::query()
            ->where('source', 'shopify')
            ->where('store_key', $this->storeKey());

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('external_id', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('contact_email', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%")
                    ->orWhere('note', 'like', "%{$q}%");
            });
        }

        if ($fs = $request->validated('financial_status')) {
            $query->where('financial_status', $fs);
        }

        if ($ffs = $request->validated('fulfillment_status')) {
            $query->where('fulfillment_status', $ffs);
        }

        if ($from = $request->validated('from')) {
            $query->whereDate('created_at_shop', '>=', $from);
        }

        if ($to = $request->validated('to')) {
            $query->whereDate('created_at_shop', '<=', $to);
        }

        $orders = $query
            ->orderByDesc('created_at_shop')
            ->paginate($perPage)
            ->appends($request->query());

        return view('shopify.orders_db.index', compact('orders', 'q'));
    }

    // ===== SHOW ORDER DETAIL =====
    public function show(string $orderId)
    {
        $order = ChannelOrder::query()
            ->where('source', 'shopify')
            ->where('store_key', $this->storeKey())
            ->where('external_id', $orderId)
            ->with(['items', 'billingAddress', 'shippingAddress', 'fulfillments'])
            ->firstOrFail();

        return view('shopify.orders_db.show', compact('order'));
    }

    // ===== SYNC ALL FROM SHOPIFY (PULL) =====
    public function syncAll(Request $request)
    {
        // pull orders from Shopify and store
        $this->syncOrdersFromShopify();
        return back()->with('status', 'Đã sync Shopify Orders về DB!');
    }

    // ===== SYNC ONE ORDER BY ID =====
    public function syncOne(string $orderId)
    {
        $order = $this->fetchOrderFromShopify($orderId);
        if (!$order) {
            return back()->withErrors(['sync' => 'Không fetch được order từ Shopify (check token/shop/order id).']);
        }

        $this->storeOrderFull($order);

        return back()->with('status', 'Đã sync order từ Shopify!');
    }

    // ===== UPDATE NOTE ON SHOPIFY + SAVE DB =====
    public function updateNote(ShopifyOrderUpdateNoteRequest $request, string $orderId)
    {
        $data = $request->validated();
        $mode = $data['mode'] ?? 'append';
        $noteText = $data['note'];

        // fetch current order from shopify (to get existing note)
        $order = $this->fetchOrderFromShopify($orderId);
        if (!$order) {
            return back()->withErrors(['note' => 'Không fetch được order từ Shopify'])->withInput();
        }

        $current = (string) ($order['note'] ?? '');
        $newNote = $mode === 'replace'
            ? $noteText
            : trim($current . "\n\n---\n" . $noteText);

        $ok = $this->putOrderNoteToShopify($orderId, $newNote);
        if (!$ok) {
            return back()->withErrors(['note' => 'Update note Shopify failed'])->withInput();
        }

        // refresh order from shopify and store (hoặc store với note mới)
        $order['note'] = $newNote;
        $this->storeOrderFull($order);

        return redirect()->route('shopify.db.orders.show', $orderId)->with('status', 'Đã cập nhật note lên Shopify + lưu DB!');
    }

    // ================== Shopify API helpers ==================

    protected function syncOrdersFromShopify(): void
    {
        $baseUrl = rtrim((string) config('message-hub.shopify.base_url'), '/');
        $token   = (string) config('message-hub.shopify.admin_token');
        $ver     = (string) config('message-hub.shopify.api_version', '2024-10');

        if (!$baseUrl || !$token) {
            throw new \RuntimeException('Missing SHOPIFY_BASE_URL or SHOPIFY_ADMIN_TOKEN');
        }

        $pageInfo = null;

        do {
            $query = [
                'status' => 'any',
                'limit'  => 250,
            ];
            if ($pageInfo) $query['page_info'] = $pageInfo;

            $url = "{$baseUrl}/admin/api/{$ver}/orders.json";
            $res = Http::withHeaders($this->shopifyHeaders())->get($url, $query);

            if (!$res->ok()) {
                Log::error('Shopify orders sync failed', ['status' => $res->status(), 'body' => $res->body()]);
                break;
            }

            $orders = $res->json('orders') ?? [];
            foreach ($orders as $o) {
                $this->storeOrderFull($o);
            }

            $pageInfo = $this->extractNextPageInfo($res->header('Link'));
        } while ($pageInfo);
    }

    protected function fetchOrderFromShopify(string $orderId): ?array
    {
        $baseUrl = rtrim((string) config('message-hub.shopify.base_url'), '/');
        $token   = (string) config('message-hub.shopify.admin_token');
        $ver     = (string) config('message-hub.shopify.api_version', '2024-10');

        if (!$baseUrl || !$token) return null;

        $url = "{$baseUrl}/admin/api/{$ver}/orders/{$orderId}.json";

        $res = Http::withHeaders($this->shopifyHeaders())->get($url);

        if (!$res->ok()) {
            Log::error('Shopify fetch order failed', ['status' => $res->status(), 'body' => $res->body()]);
            return null;
        }

        return $res->json('order') ?: null;
    }

    protected function putOrderNoteToShopify(string $orderId, string $note): bool
    {
        $baseUrl = rtrim((string) config('message-hub.shopify.base_url'), '/');
        $token   = (string) config('message-hub.shopify.admin_token');
        $ver     = (string) config('message-hub.shopify.api_version', '2024-10');

        if (!$baseUrl || !$token) return false;

        $url = "{$baseUrl}/admin/api/{$ver}/orders/{$orderId}.json";

        $res = Http::withHeaders($this->shopifyHeaders())->put($url, [
            'order' => [
                'id' => (int) $orderId,
                'note' => $note,
            ],
        ]);

        if (!$res->ok()) {
            Log::error('Shopify update note failed', ['status' => $res->status(), 'body' => $res->body()]);
            return false;
        }

        return true;
    }

    protected function shopifyHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => (string) config('message-hub.shopify.admin_token'),
            'Content-Type'           => 'application/json',
        ];
    }

    protected function extractNextPageInfo(?string $link): ?string
    {
        if (!$link) return null;
        if (preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $link, $m)) {
            return $m[1];
        }
        return null;
    }

    protected function storeKey(): ?string
    {
        $baseUrl = (string) config('message-hub.shopify.base_url');
        return $baseUrl ? (parse_url($baseUrl, PHP_URL_HOST) ?: null) : null;
    }

    // ================== DB store (order + items + addresses + fulfillments) ==================

    protected function storeOrderFull(array $order): void
    {
        DB::transaction(function () use ($order) {
            $storeKey = $this->storeKey();

            $o = ChannelOrder::query()->updateOrCreate(
                [
                    'source' => 'shopify',
                    'store_key' => $storeKey,
                    'external_id' => (string) $order['id'],
                ],
                [
                    'external_gid' => $order['admin_graphql_api_id'] ?? null,
                    'name' => $order['name'] ?? null,
                    'order_number' => $order['order_number'] ?? null,
                    'currency' => $order['currency'] ?? null,

                    'financial_status' => $order['financial_status'] ?? null,
                    'fulfillment_status' => $order['fulfillment_status'] ?? null,

                    'subtotal_price' => $order['subtotal_price'] ?? null,
                    'total_price' => $order['total_price'] ?? null,
                    'total_tax' => $order['total_tax'] ?? null,
                    'total_discounts' => $order['total_discounts'] ?? null,

                    'email' => $order['email'] ?? null,
                    'contact_email' => $order['contact_email'] ?? null,

                    'customer_external_id' => isset($order['customer']['id']) ? (string) $order['customer']['id'] : null,
                    'customer_email' => $order['customer']['email'] ?? null,

                    'note' => $order['note'] ?? null,
                    'tags' => $order['tags'] ?? null,

                    'created_at_shop' => $order['created_at'] ?? null,
                    'updated_at_shop' => $order['updated_at'] ?? null,
                    'processed_at_shop' => $order['processed_at'] ?? null,

                    'raw_json' => $order,
                ]
            );

            // items
            foreach (($order['line_items'] ?? []) as $it) {
                if (empty($it['id'])) continue;

                ChannelOrderItem::query()->updateOrCreate(
                    [
                        'channel_order_id' => $o->id,
                        'external_id' => (string) $it['id'],
                    ],
                    [
                        'external_gid' => $it['admin_graphql_api_id'] ?? null,
                        'title' => $it['title'] ?? null,
                        'variant_title' => $it['variant_title'] ?? null,
                        'sku' => $it['sku'] ?? null,
                        'product_external_id' => isset($it['product_id']) ? (string) $it['product_id'] : null,
                        'variant_external_id' => isset($it['variant_id']) ? (string) $it['variant_id'] : null,
                        'quantity' => $it['quantity'] ?? null,
                        'price' => $it['price'] ?? null,
                        'total_discount' => $it['total_discount'] ?? null,
                        'vendor' => $it['vendor'] ?? null,
                        'fulfillment_service' => $it['fulfillment_service'] ?? null,
                        'fulfillment_status' => $it['fulfillment_status'] ?? null,
                        'raw_json' => $it,
                    ]
                );
            }

            // addresses
            foreach (['billing_address' => 'billing', 'shipping_address' => 'shipping'] as $key => $type) {
                $addr = $order[$key] ?? null;
                if (!$addr) continue;

                ChannelOrderAddress::query()->updateOrCreate(
                    [
                        'channel_order_id' => $o->id,
                        'type' => $type,
                    ],
                    [
                        'name' => $addr['name'] ?? null,
                        'first_name' => $addr['first_name'] ?? null,
                        'last_name' => $addr['last_name'] ?? null,
                        'company' => $addr['company'] ?? null,
                        'address1' => $addr['address1'] ?? null,
                        'address2' => $addr['address2'] ?? null,
                        'city' => $addr['city'] ?? null,
                        'province' => $addr['province'] ?? null,
                        'province_code' => $addr['province_code'] ?? null,
                        'zip' => $addr['zip'] ?? null,
                        'country' => $addr['country'] ?? null,
                        'country_code' => $addr['country_code'] ?? null,
                        'latitude' => $addr['latitude'] ?? null,
                        'longitude' => $addr['longitude'] ?? null,
                        'raw_json' => $addr,
                    ]
                );
            }

            // fulfillments
            foreach (($order['fulfillments'] ?? []) as $f) {
                if (empty($f['id'])) continue;

                ChannelOrderFulfillment::query()->updateOrCreate(
                    [
                        'channel_order_id' => $o->id,
                        'external_id' => (string) $f['id'],
                    ],
                    [
                        'external_gid' => $f['admin_graphql_api_id'] ?? null,
                        'name' => $f['name'] ?? null,
                        'status' => $f['status'] ?? null,
                        'service' => $f['service'] ?? null,
                        'shipment_status' => $f['shipment_status'] ?? null,
                        'tracking_company' => $f['tracking_company'] ?? null,
                        'tracking_number' => Arr::first($f['tracking_numbers'] ?? []) ?: ($f['tracking_number'] ?? null),
                        'tracking_url' => Arr::first($f['tracking_urls'] ?? []) ?: ($f['tracking_url'] ?? null),
                        'created_at_shop' => $f['created_at'] ?? null,
                        'updated_at_shop' => $f['updated_at'] ?? null,
                        'raw_json' => $f,
                    ]
                );
            }
        });
    }
}

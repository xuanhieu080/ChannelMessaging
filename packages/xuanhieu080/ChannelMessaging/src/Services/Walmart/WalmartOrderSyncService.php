<?php

namespace xuanhieu080\ChannelMessaging\Services\Walmart;


use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use xuanhieu080\ChannelMessaging\Models\WalmartAccount;
use xuanhieu080\ChannelMessaging\Models\WalmartOrder;
use xuanhieu080\ChannelMessaging\Models\WalmartOrderItem;

class WalmartOrderSyncService
{
    public function __construct(
        protected WalmartClient $client
    ) {}

    public function sync(WalmartAccount $account, ?Carbon $from = null, ?Carbon $to = null, int $limit = 100): array
    {
        $from ??= now()->subDays(2);
        $to ??= now();

        // Params tùy API, đây là common pattern
        $payload = $this->client->getOrders($account, [
            'createdStartDate' => $from->toIso8601String(),
            'createdEndDate' => $to->toIso8601String(),
            'limit' => $limit,
        ]);

        // Tùy response shape của Walmart, thường có list orders trong 1 field.
        $list = $payload['list'] ?? $payload['orders'] ?? $payload['elements'] ?? [];

        $synced = 0;

        DB::transaction(function () use ($account, $list, &$synced) {
            foreach ($list as $row) {
                $poId = data_get($row, 'purchaseOrderId') ?? data_get($row, 'purchase_order_id');
                if (!$poId) continue;

                // Nếu list không đủ items thì gọi detail
                $detail = $row;
                $hasLines = (bool) data_get($detail, 'orderLines') || (bool) data_get($detail, 'order_lines');

                if (!$hasLines) {
                    $detail = $this->client->getOrderDetail($account, $poId);
                }

                $order = WalmartOrder::updateOrCreate(
                    ['purchase_order_id' => $poId],
                    [
                        'walmart_account_id' => $account->id,
                        'customer_order_id' => data_get($detail, 'customerOrderId'),
                        'status' => data_get($detail, 'status') ?? data_get($detail, 'orderStatus.status'),
                        'order_date' => $this->parseDate(data_get($detail, 'orderDate')),
                        'ship_by_date' => $this->parseDate(data_get($detail, 'shippingInfo.shipByDate')),
                        'deliver_by_date' => $this->parseDate(data_get($detail, 'shippingInfo.deliverByDate')),
                        'buyer_email' => data_get($detail, 'customerEmail'),
                        'buyer_name' => data_get($detail, 'customerName'),
                        'order_total' => data_get($detail, 'orderTotal.amount') ?? data_get($detail, 'orderTotal'),
                        'currency' => data_get($detail, 'orderTotal.currency') ?? data_get($detail, 'currency'),
                        'raw' => $detail,
                        'synced_at' => now(),
                    ]
                );

                $lines = data_get($detail, 'orderLines.orderLine', [])
                    ?: data_get($detail, 'orderLines', [])
                        ?: data_get($detail, 'order_lines', []);

                // refresh items đơn giản: upsert theo (order_id + line_number)
                foreach ((array)$lines as $line) {
                    $lineNo = (string)(data_get($line, 'lineNumber') ?? data_get($line, 'line_number') ?? '');

                    WalmartOrderItem::updateOrCreate(
                        ['walmart_order_id' => $order->id, 'line_number' => $lineNo],
                        [
                            'sku' => data_get($line, 'item.sku') ?? data_get($line, 'sku'),
                            'product_name' => data_get($line, 'item.productName') ?? data_get($line, 'product_name'),
                            'qty' => (int)(data_get($line, 'orderLineQuantity.amount') ?? data_get($line, 'qty') ?? 0),
                            'unit_price' => data_get($line, 'charges.charge[0].chargeAmount.amount')
                                ?? data_get($line, 'unitPrice.amount')
                                    ?? null,
                            'line_total' => data_get($line, 'lineTotal.amount') ?? null,
                            'currency' => data_get($line, 'charges.charge[0].chargeAmount.currency')
                                ?? data_get($line, 'unitPrice.currency')
                                    ?? $order->currency,
                            'shipping_method' => data_get($line, 'shippingMethod') ?? null,
                            'fulfillment_type' => data_get($line, 'fulfillmentType') ?? null,
                            'raw' => $line,
                        ]
                    );
                }

                $synced++;
            }
        });

        return [
            'account_id' => $account->id,
            'synced_orders' => $synced,
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ];
    }

    protected function parseDate($value): ?Carbon
    {
        if (!$value) return null;
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

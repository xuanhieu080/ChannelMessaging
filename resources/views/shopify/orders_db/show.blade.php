<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order {{ $order->name }}</title>
    <style>
        body{font-family:system-ui;margin:20px}
        .wrap{max-width:1100px;margin:auto}
        .card{border:1px solid #eee;border-radius:14px;padding:12px;margin:12px 0}
        .muted{color:#666;font-size:12px}
        table{width:100%;border-collapse:collapse}
        td,th{border-bottom:1px solid #eee;padding:8px;text-align:left;vertical-align:top}
        .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px}
        textarea{width:100%;min-height:120px;padding:10px;border:1px solid #ddd;border-radius:12px}
        select,button{padding:8px 12px;border:1px solid #ddd;border-radius:12px}
        a{color:#0b66ff;text-decoration:none}
        .err{color:#b00020;margin:10px 0}
        .ok{color:#0a7a28;margin:10px 0}
    </style>
</head>
<body>
<div class="wrap">

    <a href="{{ route('shopify.db.orders') }}">← Back</a>

    @if(session('status')) <div class="ok">{{ session('status') }}</div> @endif
    @if($errors->any()) <div class="err">{{ $errors->first() }}</div> @endif

    <div class="card">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div>
                <h2 style="margin:0">{{ $order->name ?? ('#'.$order->order_number) }}</h2>
                <div class="muted">Order ID: {{ $order->external_id }} · Store: {{ $order->store_key }}</div>
            </div>
            <div>
                <span class="badge">{{ $order->financial_status ?? 'N/A' }}</span>
                <span class="badge">{{ $order->fulfillment_status ?? 'N/A' }}</span>
            </div>
        </div>

        <div class="muted" style="margin-top:8px;">
            Email: {{ $order->email ?? $order->customer_email ?? 'N/A' }} ·
            Total: {{ $order->total_price ?? '0' }} {{ $order->currency ?? '' }} ·
            Created: {{ $order->created_at_shop?->format('Y-m-d H:i') ?? '' }}
        </div>

        <form method="POST" action="{{ route('shopify.db.orders.syncOne', $order->external_id) }}" style="margin-top:10px;">
            @csrf
            <button type="submit">Sync this order</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Line items</h3>
        <table>
            <thead>
            <tr>
                <th>Title</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Vendor</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($order->items as $it)
                <tr>
                    <td>
                        <div><strong>{{ $it->title }}</strong></div>
                        <div class="muted">{{ $it->variant_title }}</div>
                    </td>
                    <td>{{ $it->sku ?? '—' }}</td>
                    <td>{{ $it->quantity }}</td>
                    <td>{{ $it->price }}</td>
                    <td>{{ $it->vendor ?? '—' }}</td>
                    <td class="muted">{{ $it->fulfillment_status ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Addresses</h3>
        <table>
            <tr>
                <th style="width:120px;">Billing</th>
                <td>
                    @php $b = $order->billingAddress; @endphp
                    @if($b)
                        <div><strong>{{ $b->name }}</strong></div>
                        <div class="muted">
                            {{ $b->address1 }} {{ $b->address2 }}<br>
                            {{ $b->city }} {{ $b->province }} {{ $b->zip }}<br>
                            {{ $b->country }} ({{ $b->country_code }})
                        </div>
                    @else
                        —
                    @endif
                </td>
            </tr>
            <tr>
                <th>Shipping</th>
                <td>
                    @php $s = $order->shippingAddress; @endphp
                    @if($s)
                        <div><strong>{{ $s->name }}</strong></div>
                        <div class="muted">
                            {{ $s->address1 }} {{ $s->address2 }}<br>
                            {{ $s->city }} {{ $s->province }} {{ $s->zip }}<br>
                            {{ $s->country }} ({{ $s->country_code }})
                        </div>
                    @else
                        —
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Fulfillments</h3>
        @if($order->fulfillments->isEmpty())
            <div class="muted">No fulfillments</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Service</th>
                    <th>Tracking</th>
                    <th>Updated</th>
                </tr>
                </thead>
                <tbody>
                @foreach($order->fulfillments as $f)
                    <tr>
                        <td>{{ $f->name ?? $f->external_id }}</td>
                        <td>{{ $f->status ?? '—' }}</td>
                        <td class="muted">{{ $f->service ?? '—' }}</td>
                        <td class="muted">
                            {{ $f->tracking_company ?? '' }}
                            {{ $f->tracking_number ?? '' }}
                            @if($f->tracking_url)
                                <div><a href="{{ $f->tracking_url }}" target="_blank">Tracking URL</a></div>
                            @endif
                        </td>
                        <td class="muted">{{ $f->updated_at_shop?->format('Y-m-d H:i') ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3 style="margin-top:0">Order note (Shopify)</h3>

        <div class="muted" style="margin-bottom:8px;">Current note in DB:</div>
        <div style="white-space:pre-wrap;border:1px solid #eee;border-radius:12px;padding:10px;">
            {{ $order->note ?? '—' }}
        </div>

        <form method="POST" action="{{ route('shopify.db.orders.updateNote', $order->external_id) }}" style="margin-top:12px;">
            @csrf
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">
                <select name="mode">
                    <option value="append">Append</option>
                    <option value="replace">Replace</option>
                </select>
                <div class="muted">Update note on Shopify + sync back to DB</div>
            </div>

            <textarea name="note" placeholder="Nhập nội dung note...">{{ old('note') }}</textarea>

            <div style="margin-top:10px;">
                <button type="submit">Save note to Shopify</button>
            </div>
        </form>
    </div>

</div>
</body>
</html>

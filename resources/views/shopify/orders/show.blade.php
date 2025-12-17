<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shopify Order {{ $orderId }}</title>
    <style>
        body{font-family:system-ui;margin:20px}
        .wrap{max-width:980px;margin:auto}
        .msg{margin:8px 0;display:flex}
        .in{justify-content:flex-start}
        .out{justify-content:flex-end}
        .bubble{padding:10px 12px;border-radius:14px;max-width:75%;border:1px solid #eee}
        .in .bubble{background:#fafafa}
        .out .bubble{background:#fff3d9;border-color:#ffe2a8}
        .meta{font-size:11px;color:#666;margin-top:4px}
        textarea{width:100%;min-height:120px;padding:10px;border:1px solid #ddd;border-radius:10px}
        button{padding:10px 14px;border:1px solid #ddd;border-radius:10px;cursor:pointer}
        select{padding:8px 10px;border:1px solid #ddd;border-radius:10px}
        a{color:#0b66ff;text-decoration:none}
        .top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px}
        .card{border:1px solid #eee;border-radius:12px;padding:12px;margin:10px 0}
        .err{color:#b00020;margin:10px 0}
        .ok{color:#0a7a28;margin:10px 0}
    </style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <div>
            <a href="{{ route('shopify.orders') }}">← Back</a>
            <h2 style="margin:8px 0;">Order ID: {{ $orderId }}</h2>
            <div class="meta">“Reply” = update Shopify <code>order.note</code></div>
        </div>

        <form method="POST" action="{{ route('shopify.orders.syncOne', $orderId) }}">
            @csrf
            <button type="submit">Sync this order</button>
        </form>
    </div>

    @if(session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif

    @if(is_array($order))
        <div class="card">
            <div><strong>Order:</strong> {{ $order['name'] ?? '' }}</div>
            <div class="meta">
                Email: {{ $order['email'] ?? ($order['customer']['email'] ?? 'N/A') }}
                · Created: {{ $order['created_at'] ?? '' }}
                · Total: {{ $order['total_price'] ?? '' }} {{ $order['currency'] ?? '' }}
            </div>
        </div>
    @endif

    <div class="card">
        @foreach($messages as $m)
            <div class="msg {{ $m->direction === 'out' ? 'out' : 'in' }}">
                <div class="bubble">
                    @if($m->subject)
                        <div style="font-weight:600;margin-bottom:6px;">{{ $m->subject }}</div>
                    @endif
                    <div style="white-space:pre-wrap;">{{ $m->body }}</div>
                    <div class="meta">
                        {{ $m->direction === 'out' ? 'Staff' : 'Shopify' }}
                        · {{ $m->sent_at ?? $m->created_at }}
                        · {{ $m->external_id }}
                    </div>
                </div>
            </div>
        @endforeach

        <div style="margin-top:8px;">
            {{ $messages->links() }}
        </div>
    </div>

    <form method="POST" action="{{ route('shopify.orders.updateNote', $orderId) }}">
        @csrf

        <div class="card">
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">
                <strong>Update order.note</strong>
                <select name="mode">
                    <option value="append">Append</option>
                    <option value="replace">Replace</option>
                </select>
            </div>

            <textarea name="note" placeholder="Ghi chú / CSKH...">{{ old('note') }}</textarea>

            <div style="margin-top:10px;">
                <button type="submit">Save to Shopify</button>
            </div>
        </div>
    </form>

</div>
</body>
</html>

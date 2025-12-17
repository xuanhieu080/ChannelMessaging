<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shopify Orders (DB)</title>
    <style>
        body{font-family:system-ui;margin:20px}
        .top{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:14px}
        input,select{padding:8px 10px;border:1px solid #ddd;border-radius:10px}
        button{padding:8px 12px;border:1px solid #ddd;border-radius:10px;cursor:pointer}
        .row{border:1px solid #e5e5e5;padding:12px;border-radius:12px;margin-bottom:10px}
        .muted{color:#666;font-size:12px}
        .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px}
        a{color:#0b66ff;text-decoration:none}
    </style>
</head>
<body>

<h1>Shopify Orders (DB)</h1>

@if(session('status'))
    <div style="color:green;margin:10px 0">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div style="color:#b00020;margin:10px 0">{{ $errors->first() }}</div>
@endif

<div class="top">
    <form method="GET" action="{{ route('shopify.db.orders') }}">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div>
                <div class="muted">Search</div>
                <input name="q" value="{{ request('q') }}" placeholder="#1001 / email / note / id">
            </div>
            <div>
                <div class="muted">Financial</div>
                <input name="financial_status" value="{{ request('financial_status') }}" placeholder="paid/pending...">
            </div>
            <div>
                <div class="muted">Fulfillment</div>
                <input name="fulfillment_status" value="{{ request('fulfillment_status') }}" placeholder="fulfilled/partial...">
            </div>
            <div>
                <div class="muted">From</div>
                <input type="date" name="from" value="{{ request('from') }}">
            </div>
            <div>
                <div class="muted">To</div>
                <input type="date" name="to" value="{{ request('to') }}">
            </div>
            <div>
                <div class="muted">Per page</div>
                <select name="per_page">
                    @foreach([15,30,50,100] as $n)
                        <option value="{{ $n }}" @selected((int)request('per_page',30)===$n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Filter</button>
        </div>
    </form>

    <form method="POST" action="{{ route('shopify.db.orders.syncAll') }}">
        @csrf
        <button type="submit">Sync all now</button>
    </form>
</div>

@foreach($orders as $o)
    <div class="row">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div>
                <strong>
                    <a href="{{ route('shopify.db.orders.show', $o->external_id) }}">
                        {{ $o->name ?? ('#'.$o->order_number) }} ({{ $o->external_id }})
                    </a>
                </strong>
                <span class="badge">{{ $o->financial_status ?? 'N/A' }}</span>
                <span class="badge">{{ $o->fulfillment_status ?? 'N/A' }}</span>
            </div>
            <div class="muted">
                {{ $o->created_at_shop?->format('Y-m-d H:i') ?? '' }}
            </div>
        </div>

        <div class="muted" style="margin-top:6px;">
            Email: {{ $o->email ?? $o->customer_email ?? 'N/A' }}
            · Total: {{ $o->total_price ?? '0' }} {{ $o->currency ?? '' }}
            · Items: {{ $o->items_count ?? $o->items()->count() }}
        </div>

        @if($o->note)
            <div style="margin-top:8px;">
                <strong>Note:</strong> {{ \Illuminate\Support\Str::limit($o->note, 220) }}
            </div>
        @endif

        <form method="POST" action="{{ route('shopify.db.orders.syncOne', $o->external_id) }}" style="margin-top:10px;">
            @csrf
            <button type="submit">Sync this</button>
        </form>
    </div>
@endforeach

{{ $orders->links() }}

</body>
</html>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shopify Orders (Notes)</title>
    <style>
        body{font-family:system-ui;margin:20px}
        .row{border:1px solid #e5e5e5;padding:12px;border-radius:10px;margin-bottom:10px}
        .muted{color:#666;font-size:12px}
        a{color:#0b66ff;text-decoration:none}
        .top{display:flex;gap:10px;align-items:center;margin-bottom:14px}
        input{padding:8px 10px;border:1px solid #ddd;border-radius:8px;width:360px}
        button{padding:8px 12px;border:1px solid #ddd;border-radius:8px;cursor:pointer}
        .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px}
    </style>
</head>
<body>

<h1>Shopify Orders (Notes)</h1>

@if(session('status'))
    <div style="color:green;margin:10px 0">{{ session('status') }}</div>
@endif

<div class="top">
    <form method="GET" action="{{ route('shopify.orders') }}">
        <input name="q" value="{{ $q }}" placeholder="Search order_id / email / text...">
        <button type="submit">Search</button>
    </form>

    <form method="POST" action="{{ route('shopify.orders.syncAll') }}">
        @csrf
        <button type="submit">Sync all now</button>
    </form>
</div>

@foreach($rows as $r)
    @php $last = $lastMap[$r->last_id] ?? null; @endphp
    <div class="row">
        <div style="display:flex;justify-content:space-between;gap:10px;">
            <div>
                <strong>Order ID:</strong>
                <a href="{{ route('shopify.orders.show', $r->thread_id) }}">{{ $r->thread_id }}</a>
                <span class="badge">{{ $r->msg_count }} records</span>
            </div>
            <div class="muted">{{ $r->last_sent_at }}</div>
        </div>

        @if($last)
            <div style="margin-top:8px;">
                <strong>{{ $last->direction === 'out' ? 'Staff' : 'Shopify' }}:</strong>
                {{ \Illuminate\Support\Str::limit($last->body, 200) }}
            </div>
            <div class="muted" style="margin-top:6px;">
                {{ $last->subject }} · {{ $last->sender ?? 'N/A' }}
            </div>
        @endif
    </div>
@endforeach

{{ $rows->links() }}

</body>
</html>

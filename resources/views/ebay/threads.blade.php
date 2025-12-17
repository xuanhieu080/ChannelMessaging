<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>eBay Inbox</title>
    <style>
        body{font-family:system-ui;margin:20px}
        .row{border:1px solid #ddd;padding:12px;border-radius:10px;margin-bottom:10px}
        .muted{color:#666;font-size:12px}
        a{text-decoration:none;color:#0b66ff}
    </style>
</head>
<body>

<h1>eBay Inbox</h1>

<form>
    <input name="q" value="{{ $q }}" placeholder="Search item / buyer / text">
    <button>Search</button>
</form>

@foreach($rows as $r)
    @php $last = $lastMap[$r->last_id] ?? null; @endphp
    <div class="row">
        <div>
            <strong>Thread:</strong>
            <a href="{{ route('ebay.threads.show', $r->thread_id) }}">
                {{ $r->thread_id }}
            </a>
        </div>
        <div class="muted">
            {{ $r->msg_count }} messages · {{ $r->last_sent_at }}
        </div>
        @if($last)
            <div><strong>{{ $last->direction === 'out' ? 'You' : 'Buyer' }}:</strong>
                {{ \Illuminate\Support\Str::limit($last->body, 150) }}</div>
        @endif
    </div>
@endforeach

{{ $rows->links() }}

</body>
</html>

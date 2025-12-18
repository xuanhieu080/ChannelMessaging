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

<h1>eBay Inbox</h1>

@if(session('status'))
    <div style="padding:10px;border:1px solid #0a0;border-radius:10px;margin:10px 0;">
        {{ session('status') }}
    </div>
@endif

@if($errors->has('sync'))
    <div style="padding:10px;border:1px solid #a00;border-radius:10px;margin:10px 0;">
        {{ $errors->first('sync') }}
    </div>
@endif

<form>
    <input name="q" value="{{ $q }}" placeholder="Search item / buyer / text">
    <button>Search</button>
</form>

{{-- ✅ Sync manual --}}
<form method="POST" action="{{ route('ebay.sync') }}" style="margin:10px 0; display:flex; gap:8px; align-items:center;">
    @csrf
    <input type="date" name="from" value="{{ request('from') }}">
    <input type="date" name="to" value="{{ request('to') }}">
    <button type="submit">Sync messages</button>
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

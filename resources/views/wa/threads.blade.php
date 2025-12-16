<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>WhatsApp Inbox</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:20px;}
        h1{margin-bottom:14px;}
        .row{border:1px solid #e5e5e5;padding:12px;border-radius:10px;margin-bottom:10px;}
        .muted{color:#666;font-size:12px;}
        a{color:#0b66ff;text-decoration:none;}
        .top{display:flex;gap:10px;align-items:center;margin-bottom:14px;}
        input{padding:8px 10px;border:1px solid #ddd;border-radius:8px;width:320px;}
        button{padding:8px 12px;border:1px solid #ddd;border-radius:8px;cursor:pointer;}
    </style>
</head>
<body>

<h1>WhatsApp Inbox</h1>

<div class="top">
    <form method="GET" action="{{ route('wa.threads') }}">
        <input name="q" value="{{ $q }}" placeholder="Search phone / text...">
        <button type="submit">Search</button>
    </form>
</div>

@foreach($rows as $r)
    @php
        $last = $lastMap[$r->last_id] ?? null;
    @endphp

    <div class="row">
        <div>
            <strong>Phone:</strong>
            <a href="{{ route('wa.threads.show', $r->thread_id) }}">
                {{ $r->thread_id }}
            </a>
        </div>

        <div class="muted">
            Last: {{ $r->last_sent_at }} |
            Total: {{ $r->msg_count }}
        </div>

        @if($last)
            <div style="margin-top:8px;">
                <strong>{{ $last->direction === 'out' ? 'You' : 'Customer' }}:</strong>
                {{ \Illuminate\Support\Str::limit($last->body, 200) }}
            </div>
        @endif
    </div>
@endforeach

{{ $rows->links() }}

</body>
</html>

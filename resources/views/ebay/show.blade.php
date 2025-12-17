<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>eBay Thread</title>
    <style>
        body{font-family:system-ui;margin:20px}
        .msg{margin:8px 0;display:flex}
        .in{justify-content:flex-start}
        .out{justify-content:flex-end}
        .bubble{border:1px solid #ddd;border-radius:14px;padding:10px;max-width:70%}
        .out .bubble{background:#e8f1ff}
        .meta{font-size:11px;color:#666;margin-top:4px}
        textarea,input{width:100%;padding:10px;margin-top:8px}
    </style>
</head>
<body>

<a href="{{ route('ebay.threads') }}">← Back</a>
<h2>{{ $threadId }}</h2>

@if(session('status'))
    <div style="color:green">{{ session('status') }}</div>
@endif

@foreach($messages as $m)
    <div class="msg {{ $m->direction === 'out' ? 'out' : 'in' }}">
        <div class="bubble">
            @if($m->subject)<strong>{{ $m->subject }}</strong><br>@endif
            {{ $m->body }}
            <div class="meta">{{ $m->sent_at }}</div>
        </div>
    </div>
@endforeach

{{ $messages->links() }}

<hr>

<form method="POST" action="{{ route('ebay.threads.send', $threadId) }}">
    @csrf
    <input name="subject" value="Re: Order">
    <textarea name="text" placeholder="Reply to buyer..."></textarea>
    <button>Send</button>
</form>

</body>
</html>

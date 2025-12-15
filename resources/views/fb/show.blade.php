<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Thread {{ $threadId }}</title>
    <style>
        body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin:20px;}
        .wrap{max-width:920px; margin:auto;}
        .msg{margin:8px 0; display:flex;}
        .bubble{padding:10px 12px; border-radius:14px; max-width:70%; border:1px solid #eee;}
        .in{justify-content:flex-start;}
        .in .bubble{background:#fafafa;}
        .out{justify-content:flex-end;}
        .out .bubble{background:#e8f1ff; border-color:#d6e7ff;}
        .meta{font-size:11px; color:#666; margin-top:4px;}
        textarea{width:100%; min-height:90px; padding:10px; border:1px solid #ddd; border-radius:10px;}
        button{padding:10px 14px; border:1px solid #ddd; border-radius:10px; cursor:pointer;}
        a{color:#0b66ff; text-decoration:none;}
        .top{display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;}
        .err{color:#b00020; margin:10px 0;}
        .ok{color:#0a7a28; margin:10px 0;}
    </style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <div>
            <a href="{{ route('fb.threads') }}">← Back</a>
            <h2 style="margin:8px 0;">Thread: {{ $threadId }}</h2>
        </div>
    </div>

    @if(session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif

    <div style="border:1px solid #eee; padding:12px; border-radius:12px;">
        @foreach($messages as $m)
            <div class="msg {{ $m->direction === 'out' ? 'out' : 'in' }}">
                <div class="bubble">
                    <div>{{ $m->body }}</div>
                    <div class="meta">
                        {{ $m->direction === 'out' ? 'You' : 'User' }} ·
                        {{ $m->sent_at ?? $m->created_at }} ·
                        {{ $m->external_id }}
                    </div>
                </div>
            </div>
        @endforeach

        <div style="margin-top:8px;">
            {{ $messages->links() }}
        </div>
    </div>

    <form method="POST" action="{{ route('fb.threads.send', $threadId) }}" style="margin-top:14px;">
        @csrf
        <label><strong>Reply</strong></label>
        <textarea name="text" placeholder="Type a message...">{{ old('text') }}</textarea>
        <div style="margin-top:10px; display:flex; gap:10px;">
            <button type="submit">Send</button>
        </div>
    </form>

</div>
</body>
</html>

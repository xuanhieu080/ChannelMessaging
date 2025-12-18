<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Walmart Integration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:20px}
        .nav a{margin-right:12px}
        .card{border:1px solid #ddd;border-radius:10px;padding:14px;margin:12px 0}
        table{width:100%;border-collapse:collapse}
        th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;vertical-align:top}
        .btn{display:inline-block;padding:8px 12px;border:1px solid #333;border-radius:8px;text-decoration:none}
        .muted{color:#666}
        .success{background:#eaffea;border:1px solid #b7efb7;padding:10px;border-radius:8px}
    </style>
</head>
<body>
<div class="nav">
    <a href="{{ route('accounts.index') }}">Accounts</a>
    <a href="{{ route('walmart.orders.index') }}">Orders</a>
</div>

@if(session('success'))
    <div class="success">{{ session('success') }}</div>
@endif

@yield('content')
</body>
</html>

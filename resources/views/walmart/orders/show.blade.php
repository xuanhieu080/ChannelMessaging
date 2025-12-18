@extends('walmart.layout')

@section('content')
    <div class="card">
        <h2>Order: {{ $order->purchase_order_id }}</h2>
        <div class="muted">Account: {{ $order->account?->name }} (#{{ $order->walmart_account_id }})</div>
        <div>Status: <b>{{ $order->status }}</b></div>
        <div>Order date: {{ optional($order->order_date)->format('Y-m-d H:i') }}</div>
        <div>Total: {{ $order->order_total }} {{ $order->currency }}</div>
    </div>

    <div class="card">
        <h3>Items</h3>
        <table>
            <thead>
            <tr>
                <th>Line</th>
                <th>SKU</th>
                <th>Name</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach($order->items as $it)
                <tr>
                    <td>{{ $it->line_number }}</td>
                    <td>{{ $it->sku }}</td>
                    <td>{{ $it->product_name }}</td>
                    <td>{{ $it->qty }}</td>
                    <td>{{ $it->unit_price }} {{ $it->currency }}</td>
                    <td>{{ $it->line_total }} {{ $it->currency }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Raw</h3>
        <pre style="white-space:pre-wrap">{{ json_encode($order->raw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
@endsection

@extends('walmart.layout')

@section('content')
    <div class="card">
        <h2>Walmart Orders</h2>

        <form method="GET" action="{{ route('walmart.orders.index') }}">
            <input name="search" placeholder="PO / Customer Order / Email" value="{{ request('search') }}" style="width:360px">
            <input name="status" placeholder="status" value="{{ request('status') }}" style="width:160px">
            <input name="account_id" placeholder="account_id" value="{{ request('account_id') }}" style="width:120px">
            <button class="btn" type="submit">Filter</button>
        </form>

        <hr style="border:none;border-top:1px solid #eee;margin:12px 0"/>

        <form method="POST" action="{{ route('walmart.orders.sync') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            @csrf
            <input name="account_id" placeholder="account_id (trống = tất cả)" value="{{ request('account_id') }}" style="width:220px">

            <input type="date" name="from" value="{{ request('from') }}">
            <input type="date" name="to" value="{{ request('to') }}">

            <input name="limit" value="{{ request('limit', 100) }}" style="width:120px" placeholder="limit">

            <button class="btn" onclick="return confirm('Đồng bộ đơn hàng Walmart ngay?')">Đồng bộ đơn hàng</button>
        </form>

        <div class="muted" style="margin-top:8px">
            Mặc định: đồng bộ 2 ngày gần nhất. Nhập account_id để sync 1 account.
        </div>
    </div>
@endsection

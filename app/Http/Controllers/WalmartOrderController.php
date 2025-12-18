<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use xuanhieu080\ChannelMessaging\Models\WalmartAccount;
use xuanhieu080\ChannelMessaging\Models\WalmartOrder;
use xuanhieu080\ChannelMessaging\Services\Walmart\WalmartOrderSyncService;

class WalmartOrderController extends Controller
{
    public function index(Request $request)
    {
        $q = WalmartOrder::query()->with('account');

        if ($request->filled('account_id')) {
            $q->where('walmart_account_id', $request->integer('account_id'));
        }

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $s = trim($request->string('search'));
            $q->where(function ($qq) use ($s) {
                $qq->where('purchase_order_id', 'like', "%{$s}%")
                    ->orWhere('customer_order_id', 'like', "%{$s}%")
                    ->orWhere('buyer_email', 'like', "%{$s}%");
            });
        }

        $orders = $q->latest('order_date')->paginate(30)->withQueryString();

        return view('walmart.orders.index', compact('orders'));
    }

    public function show(WalmartOrder $order)
    {
        $order->load(['account','items']);
        return view('walmart.orders.show', compact('order'));
    }

    public function sync(Request $request, WalmartOrderSyncService $sync)
    {
        $accountId = $request->integer('account_id');

        $accounts = WalmartAccount::query()
            ->where('is_active', true)
            ->when($accountId, fn($q) => $q->whereKey($accountId))
            ->get();

        if ($accounts->isEmpty()) {
            return back()->with('success', 'Không có Walmart account active để đồng bộ.');
        }

        $from = $request->date('from')?->startOfDay() ?? now()->subDays(2);
        $to   = $request->date('to')?->endOfDay() ?? now();
        $limit = (int)($request->get('limit', 100));

        $totalSynced = 0;

        foreach ($accounts as $acc) {
            $result = $sync->sync($acc, $from, $to, $limit);
            $totalSynced += (int)($result['synced_orders'] ?? 0);
        }

        return redirect()->route('walmart.orders.index', $request->only(['search','status','account_id']))
            ->with('success', "Đồng bộ xong: {$totalSynced} đơn (từ {$from->format('Y-m-d')} đến {$to->format('Y-m-d')}).");
    }
}

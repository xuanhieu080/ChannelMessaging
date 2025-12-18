<?php

namespace App\Http\Controllers;

use App\Http\Requests\Walmart\WalmartAccountRequest;
use Illuminate\Http\Request;
use xuanhieu080\ChannelMessaging\Models\WalmartAccount;
use xuanhieu080\ChannelMessaging\Services\Walmart\WalmartOrderSyncService;

class WalmartAccountController extends Controller
{
    public function index()
    {
        $accounts = WalmartAccount::query()->latest()->paginate(20);
        return view('walmart.accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('walmart.accounts.create');
    }

    public function store(WalmartAccountRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['auto_sync_enabled'] = (bool)($data['auto_sync_enabled'] ?? false);
        $data['auto_sync_minutes'] = (int)($data['auto_sync_minutes'] ?? 15);

        WalmartAccount::create($data);

        return redirect()->route('accounts.index')
            ->with('success', 'Created Walmart account.');
    }

    public function edit(WalmartAccount $account)
    {
        return view('walmart.accounts.edit', compact('account'));
    }

    public function update(WalmartAccountRequest $request, WalmartAccount $account)
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['auto_sync_enabled'] = (bool)($data['auto_sync_enabled'] ?? false);
        $data['auto_sync_minutes'] = (int)($data['auto_sync_minutes'] ?? 15);

        $account->update($data);

        return redirect()->route('accounts.index')
            ->with('success', 'Updated Walmart account.');
    }

    public function destroy(WalmartAccount $account)
    {
        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Deleted Walmart account.');
    }

    public function sync(Request $request, WalmartAccount $account, WalmartOrderSyncService $sync)
    {
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();

        $result = $sync->sync($account, $from, $to, (int)($request->get('limit', 100)));

        return back()->with('success', "Synced {$result['synced_orders']} orders ({$result['from']} → {$result['to']}).");
    }

    public function syncNow(Request $request, WalmartAccount $account, WalmartOrderSyncService $sync)
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(2);
        $to   = $request->date('to')?->endOfDay() ?? now();

        $result = $sync->sync($account, $from, $to, (int)($request->get('limit', 100)));

        return back()->with('success', "Synced {$result['synced_orders']} orders ({$result['from']} → {$result['to']}).");
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;
use xuanhieu080\ChannelMessaging\Services\EbayMessageService;

class EbayController extends Controller
{
    public function threads(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = ChannelMessage::query()
            ->where('source', 'ebay')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('thread_id', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%");
                });
            })
            ->select([
                'thread_id',
                DB::raw('MAX(sent_at) as last_sent_at'),
                DB::raw('MAX(id) as last_id'),
                DB::raw('COUNT(*) as msg_count'),
            ])
            ->groupBy('thread_id')
            ->orderByDesc('last_sent_at')
            ->paginate(30)
            ->appends($request->query());

        $lastIds = $rows->pluck('last_id')->filter()->all();
        $lastMap = ChannelMessage::query()->whereIn('id', $lastIds)->get()->keyBy('id');

        return view('ebay.threads', compact('rows', 'lastMap', 'q'));
    }

    public function show(string $threadId)
    {
        $messages = ChannelMessage::query()
            ->where('source', 'ebay')
            ->where('thread_id', $threadId)
            ->orderBy('sent_at')
            ->paginate(50);

        return view('ebay.show', compact('threadId', 'messages'));
    }

    public function send(Request $request, string $threadId, EbayMessageService $ebay)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'text'    => ['required', 'string', 'max:2000'],
        ]);

        // parse thread_id
        [$itemId, $buyerId, $txnId] = array_pad(explode(':', $threadId, 3), 3, null);

        if (!$itemId || !$buyerId) {
            return back()->withErrors(['text' => 'Invalid thread format'])->withInput();
        }

        $resp = $ebay->sendReply($itemId, $buyerId, $data['subject'], $data['text'], $txnId);

        if (!$resp['ok']) {
            return back()->withErrors(['text' => $resp['error'] ?? 'Send failed'])->withInput();
        }

        // Lưu message OUT
        ChannelMessage::create([
            'source'      => 'ebay',
            'external_id' => 'ebay-out-' . uniqid(),
            'thread_id'   => $threadId,
            'sender'      => 'seller',
            'receiver'    => $buyerId,
            'direction'   => 'out',
            'subject'     => $data['subject'],
            'body'        => $data['text'],
            'sent_at'     => now(),
            'raw_json'    => json_encode($resp['raw'] ?? []),
        ]);

        return redirect()->route('ebay.threads.show', $threadId)
            ->with('status', 'Đã gửi tin nhắn eBay');
    }

    public function sync(Request $request, EbayMessageService $ebay)
    {
        // optional filter thời gian
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? \Carbon\Carbon::parse($data['from']) : now()->subDays(2);
        $to   = isset($data['to'])   ? \Carbon\Carbon::parse($data['to'])   : now();

        $resp = $ebay->syncMessages($from, $to); // ✅ implement ở service bên dưới

        if (!($resp['ok'] ?? false)) {
            return back()->withErrors(['sync' => $resp['error'] ?? 'Sync failed']);
        }

        return back()->with('status', "Đã sync {$resp['synced']}/{$resp['total']} messages");
    }
}

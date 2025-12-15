<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class FacebookController extends Controller
{
    public function threads(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Group theo thread_id (= PSID), lấy last message + count
        $rows = ChannelMessage::query()
            ->where('source', 'facebook')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('thread_id', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%");
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

        // Lấy nội dung last message theo last_id
        $lastIds = $rows->pluck('last_id')->filter()->values()->all();
        $lastMap = ChannelMessage::query()
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('id');

        return view('fb.threads', [
            'rows' => $rows,
            'lastMap' => $lastMap,
            'q' => $q,
        ]);
    }

    public function show(Request $request, string $threadId)
    {
        $messages = ChannelMessage::query()
            ->where('source', 'facebook')
            ->where('thread_id', $threadId)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->paginate(50)
            ->appends($request->query());

        return view('fb.show', [
            'threadId' => $threadId,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, string $threadId)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $text = $data['text'];

        // Gửi message qua Graph API
        $resp = $this->sendMessage($threadId, $text);

        if (!$resp['ok']) {
            return back()->withErrors([
                'text' => $resp['error'] ?? 'Send message failed',
            ])->withInput();
        }

        // Lưu vào DB như message "out"
        $externalId = $resp['message_id'] ?? ('out-'.uniqid());

        ChannelMessage::query()->updateOrCreate(
            [
                'source'      => 'facebook',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => $threadId,
                'sender'    => 'page',     // bạn có thể set page_id nếu muốn
                'receiver'  => $threadId,  // PSID
                'direction' => 'out',
                'subject'   => null,
                'body'      => $text,
                'sent_at'   => now(),
                'raw_json'  => json_encode($resp['raw'] ?? []),
            ]
        );

        return redirect()->route('fb.threads.show', $threadId)->with('status', 'Đã gửi tin nhắn!');
    }

    protected function sendMessage(string $psid, string $messageText): array
    {
        $token   = config('message-hub.facebook.page_access_token');
        $version = config('message-hub.facebook.graph_version', 'v18.0');

        if (!$token) {
            return ['ok' => false, 'error' => 'Missing FACEBOOK_PAGE_ACCESS_TOKEN'];
        }

        $url = "https://graph.facebook.com/{$version}/me/messages";

        $res = Http::asJson()->post(
            $url . '?access_token=' . urlencode($token),
            [
                'recipient' => ['id' => $psid],
                'message'   => ['text' => $messageText],
                // 'messaging_type' => 'RESPONSE',
            ]
        );

        if (!$res->ok()) {
            Log::error('FB sendMessage failed', [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);

            return ['ok' => false, 'error' => $res->json('error.message') ?? $res->body(), 'raw' => $res->json()];
        }

        // Graph thường trả { "recipient_id": "...", "message_id": "m_..." }
        return [
            'ok' => true,
            'message_id' => $res->json('message_id'),
            'raw' => $res->json(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class WhatsAppController extends Controller
{
    public function threads(Request $request)
    {
        $q = trim((string)$request->query('q', ''));

        $rows = ChannelMessage::query()
            ->where('source', 'whatsapp')
            ->when($q !== '', fn ($qq) =>
            $qq->where('thread_id', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")
            )
            ->select([
                'thread_id',
                DB::raw('MAX(sent_at) as last_sent_at'),
                DB::raw('MAX(id) as last_id'),
                DB::raw('COUNT(*) as msg_count'),
            ])
            ->groupBy('thread_id')
            ->orderByDesc('last_sent_at')
            ->paginate(30);

        $lastIds = $rows->pluck('last_id')->all();
        $lastMap = ChannelMessage::whereIn('id', $lastIds)->get()->keyBy('id');

        return view('wa.threads', compact('rows', 'lastMap', 'q'));
    }

    public function show(string $threadId)
    {
        $messages = ChannelMessage::query()
            ->where('source', 'whatsapp')
            ->where('thread_id', $threadId)
            ->orderBy('sent_at')
            ->paginate(50);

        return view('wa.show', compact('threadId', 'messages'));
    }

    public function send(Request $request, string $threadId)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $resp = $this->sendMessage($threadId, $data['text']);

        if (!$resp['ok']) {
            return back()->withErrors(['text' => $resp['error']])->withInput();
        }

        ChannelMessage::updateOrCreate(
            [
                'source'      => 'whatsapp',
                'external_id' => $resp['message_id'] ?? uniqid('wa_out_'),
            ],
            [
                'thread_id' => $threadId,
                'sender'    => 'business',
                'receiver'  => $threadId,
                'direction' => 'out',
                'subject'   => 'text',
                'body'      => $data['text'],
                'sent_at'   => now(),
                'raw_json'  => json_encode($resp['raw'] ?? []),
            ]
        );

        return redirect()->route('wa.threads.show', $threadId)
            ->with('status', 'Đã gửi WhatsApp!');
    }

    protected function sendMessage(string $to, string $text): array
    {
        $token   = config('message-hub.whatsapp.access_token');
        $pnid    = config('message-hub.whatsapp.phone_number_id');
        $version = config('message-hub.whatsapp.graph_version', 'v24.0');

        $url = "https://graph.facebook.com/{$version}/{$pnid}/messages";

        $res = Http::withToken($token)->asJson()->post($url, [
            'messaging_product' => 'whatsapp',
            'to'   => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ]);

        if (!$res->ok()) {
            Log::error('WA send failed', ['body' => $res->body()]);
            return ['ok' => false, 'error' => $res->body()];
        }

        return [
            'ok' => true,
            'message_id' => $res->json('messages.0.id'),
            'raw' => $res->json(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('sdfsdfsda fasdfasdft', $request->all());
        // ===== VERIFY =====
        if ($request->isMethod('get')) {
            $verifyToken = config('message-hub.whatsapp.verify_token');

            $mode      = $request->query('hub_mode') ?? $request->query('hub.mode');
            $token     = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        // ===== RECEIVE MESSAGE =====
        $payload = $request->all();
        Log::info('WA webhook payload', $payload);

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['messages'] ?? []) as $msg) {
                    $this->storeMessage($value, $msg);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function storeMessage(array $value, array $msg): void
    {
        $from = $msg['from'] ?? null;           // số người nhắn (wa_id)
        $id   = $msg['id'] ?? null;             // wamid.xxx
        $type = $msg['type'] ?? null;

        if (!$from || !$id) return;

        $text = $msg['text']['body'] ?? '';
        $ts   = isset($msg['timestamp'])
            ? Carbon::createFromTimestamp((int)$msg['timestamp'])
            : now();

        ChannelMessage::query()->updateOrCreate(
            [
                'source'      => 'whatsapp',
                'external_id' => $id,
            ],
            [
                'thread_id' => $from, // group theo số điện thoại
                'sender'    => $from,
                'receiver'  => $value['metadata']['phone_number_id'] ?? null,
                'direction' => 'in',
                'subject'   => $type,
                'body'      => $text,
                'sent_at'   => $ts,
                'raw_json'  => json_encode($msg),
            ]
        );
    }
}

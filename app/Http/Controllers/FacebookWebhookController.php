<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class FacebookWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('get')) {
            $verifyToken = config('message-hub.facebook.verify_token');

            $mode        = $request->query('hub_mode') ?? $request->query('hub.mode');
            $token       = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge   = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }


        if ($request->isMethod('post')) {
            // Facebook gửi JSON dạng:
            // {
            //   "object": "page",
            //   "entry": [ { "id": "PAGE_ID", "messaging": [ ... ] } ]
            // }
            $payload = $request->all();
            Log::info('FB webhook payload', $payload);

            if (($payload['object'] ?? null) === 'page') {
                foreach ($payload['entry'] as $entry) {
                    $this->handleEntry($entry);
                }
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Method not allowed', 405);
    }

    protected function handleEntry(array $entry): void
    {
        if (!isset($entry['messaging'])) return;

        foreach ($entry['messaging'] as $event) {
            $senderId = $event['sender']['id'] ?? null;
            $recipientId = $event['recipient']['id'] ?? null;
            $text = $event['message']['text'] ?? null;
            $mid = $event['message']['mid'] ?? null;
            $timestamp = $event['timestamp'] ?? null;

            if (!$mid || !$text) continue;

            ChannelMessage::query()->updateOrCreate(
                [
                    'source'      => 'facebook',
                    'external_id' => $mid,
                ],
                [
                    'thread_id' => $senderId,
                    'sender'    => $senderId,
                    'receiver'  => $recipientId,
                    'direction' => 'in',
                    'subject'   => null,
                    'body'      => $text,
                    'sent_at'   => $timestamp ? \Carbon\Carbon::createFromTimestampMs($timestamp) : now(),
                    'raw_json'  => json_encode($event),
                ]
            );
        }
    }
}

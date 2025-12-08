<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1) Bước VERIFY (Facebook gọi GET)
        if ($request->isMethod('get')) {
            $verifyToken = config('message-hub.facebook.verify_token');

            $mode        = $request->query('hub_mode') ?? $request->query('hub.mode');
            $token       = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge   = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                // Trả về đúng challenge (text/plain)
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        // 2) Bước NHẬN EVENT (Facebook gửi POST)
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
        // Mỗi entry có thể chứa nhiều messaging
        if (!isset($entry['messaging'])) {
            return;
        }

        foreach ($entry['messaging'] as $event) {
            // Ở đây bạn dispatch job để lưu tin nhắn vào DB
            // Ví dụ: Dispatch một Job process tin nhắn
            // ProcessFacebookMessage::dispatch($event);

            // Test: log thử
            \Log::info('FB message event', $event);
        }
    }
}

<?php

namespace xuanhieu080\ChannelMessaging\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class WhatsAppMessageService
{
    protected string $graphUrl;
    protected string $accessToken;
    protected string $phoneNumberId;

    public function __construct()
    {
        $version = config('message-hub.whatsapp.graph_version', 'v24.0');

        $this->graphUrl      = "https://graph.facebook.com/{$version}";
        $this->accessToken   = (string) config('message-hub.whatsapp.access_token');
        $this->phoneNumberId = (string) config('message-hub.whatsapp.phone_number_id');
    }

    /**
     * Parse webhook payload (Cloud API) và lưu tất cả inbound messages vào DB.
     * Controller webhook chỉ cần gọi:
     *   app(WhatsAppMessageService::class)->ingestWebhookPayload($request->all());
     */
    public function ingestWebhookPayload(array $payload): void
    {
        // payload thường: entry[].changes[].value.messages[] (inbound)
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['messages'] ?? []) as $msg) {
                    $this->storeInboundMessage($value, $msg);
                }

                // statuses[]: delivered/read... (nếu muốn lưu thêm bạn mở rộng sau)
            }
        }
    }

    /**
     * Gửi tin nhắn text qua WhatsApp Cloud API.
     * Trả về: ['ok'=>bool, 'message_id'=>?, 'raw'=>..., 'error'=>?...]
     */
    public function sendText(string $to, string $text, bool $previewUrl = false): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            return [
                'ok'    => false,
                'error' => 'Missing WHATSAPP_ACCESS_TOKEN or WHATSAPP_PHONE_NUMBER_ID',
            ];
        }

        $url = "{$this->graphUrl}/{$this->phoneNumberId}/messages";

        $res = Http::withToken($this->accessToken)
            ->asJson()
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $to,
                'type'              => 'text',
                'text'              => [
                    'preview_url' => $previewUrl,
                    'body'        => $text,
                ],
            ]);

        if (!$res->ok()) {
            Log::error('WA sendText failed', [
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);

            return [
                'ok'    => false,
                'error' => $res->json('error.message') ?? $res->body(),
                'raw'   => $res->json(),
            ];
        }

        $messageId = $res->json('messages.0.id');

        return [
            'ok'         => true,
            'message_id' => $messageId,
            'raw'        => $res->json(),
        ];
    }

    /**
     * Lưu outbound message (sau khi sendText thành công).
     * Bạn có thể dùng trong UI reply để lưu vào channel_messages.
     */
    public function storeOutboundMessage(string $to, string $text, ?string $externalId = null, array $raw = []): ChannelMessage
    {
        $externalId = $externalId ?: ('wa-out-'.uniqid());

        return ChannelMessage::query()->updateOrCreate(
            [
                'source'      => 'whatsapp',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => $to,           // group theo số người nhận
                'sender'    => 'business',
                'receiver'  => $to,
                'direction' => 'out',
                'subject'   => 'text',
                'body'      => $text,
                'sent_at'   => now(),
                'raw_json'  => json_encode($raw),
            ]
        );
    }

    protected function storeInboundMessage(array $value, array $msg): void
    {
        $from      = $msg['from'] ?? null;   // wa_id người gửi (số)
        $id        = $msg['id'] ?? null;     // wamid...
        $type      = $msg['type'] ?? null;
        $timestamp = isset($msg['timestamp']) ? (int) $msg['timestamp'] : null;

        if (!$from || !$id) {
            return;
        }

        // text message
        $body = $msg['text']['body'] ?? '';

        // Nếu là attachment thì bạn có thể mở rộng:
        // image/audio/document => lưu caption hoặc json
        if ($type && $type !== 'text' && $body === '') {
            $body = "[{$type}]";
        }

        ChannelMessage::query()->updateOrCreate(
            [
                'source'      => 'whatsapp',
                'external_id' => $id,
            ],
            [
                'thread_id' => $from, // group theo số người gửi
                'sender'    => $from,
                'receiver'  => $value['metadata']['phone_number_id'] ?? null,
                'direction' => 'in',
                'subject'   => $type,
                'body'      => $body,
                'sent_at'   => $timestamp ? Carbon::createFromTimestamp($timestamp) : now(),
                'raw_json'  => json_encode($msg),
            ]
        );
    }
}

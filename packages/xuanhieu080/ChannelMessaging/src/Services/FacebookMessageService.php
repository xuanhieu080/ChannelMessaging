<?php

namespace xuanhieu080\ChannelMessaging\Services;

use xuanhieu080\ChannelMessaging\Models\ChannelMessage;
use Illuminate\Support\Facades\Http;

class FacebookMessageService
{
    protected string $graphUrl;
    protected string $pageToken;

    public function __construct()
    {
        $version         = config('message-hub.facebook.graph_version', 'v18.0');
        $this->graphUrl  = "https://graph.facebook.com/{$version}";
        $this->pageToken = config('message-hub.facebook.page_access_token');
    }

    public function syncMessages(): void
    {
        if (!$this->pageToken) {
            return;
        }

        $after = null;

        do {
            $query = [
                'access_token' => $this->pageToken,
                'fields'       => 'id,participants,updated_time',
                'limit'        => 50,
            ];
            if ($after) {
                $query['after'] = $after;
            }

            $response = Http::get("{$this->graphUrl}/me/conversations", $query);
            if (!$response->ok()) {
                break;
            }

            $data = $response->json();
            $conversations = $data['data'] ?? [];

            foreach ($conversations as $conv) {
                $this->syncConversationMessages($conv['id']);
            }

            $after = $data['paging']['cursors']['after'] ?? null;
        } while ($after);
    }

    protected function syncConversationMessages(string $conversationId): void
    {
        $after = null;

        do {
            $query = [
                'access_token' => $this->pageToken,
                'fields'       => 'id,from,to,message,created_time',
                'limit'        => 50,
            ];
            if ($after) {
                $query['after'] = $after;
            }

            $res = Http::get("{$this->graphUrl}/{$conversationId}/messages", $query);
            if (!$res->ok()) {
                break;
            }

            $data = $res->json();
            $messages = $data['data'] ?? [];

            foreach ($messages as $msg) {
                $this->storeMessage($conversationId, $msg);
            }

            $after = $data['paging']['cursors']['after'] ?? null;
        } while ($after);
    }

    protected function storeMessage(string $conversationId, array $msg): void
    {
        $externalId = $msg['id'] ?? null;
        if (!$externalId) {
            return;
        }

        $senderName   = $msg['from']['name'] ?? null;
        $receiverName = $msg['to']['data'][0]['name'] ?? null;
        $direction    = 'in'; // có thể cải tiến sau

        ChannelMessage::updateOrCreate(
            [
                'source'      => 'facebook',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => $conversationId,
                'sender'    => $senderName,
                'receiver'  => $receiverName,
                'direction' => $direction,
                'subject'   => null,
                'body'      => $msg['message'] ?? '',
                'sent_at'   => isset($msg['created_time'])
                    ? new \DateTime($msg['created_time'])
                    : null,
                'raw_json'  => json_encode($msg),
            ]
        );
    }
}

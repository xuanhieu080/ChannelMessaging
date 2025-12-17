<?php

namespace xuanhieu080\ChannelMessaging\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class EbayMessageService
{
    protected string $endpoint;
    protected string $token;
    protected int $siteId;
    protected string $compatLevel;

    public function __construct()
    {
        // production|sandbox
        $env = config('message-hub.ebay.env', 'production');
        $this->endpoint = $env === 'sandbox'
            ? 'https://api.sandbox.ebay.com/ws/api.dll'
            : 'https://api.ebay.com/ws/api.dll';

        // dùng 1 key thống nhất: ebay.auth_token (legacy) hoặc ebay.oauth_token
        $this->token       = (string) (config('message-hub.ebay.auth_token') ?: config('message-hub.ebay.oauth_token'));
        $this->siteId      = (int) config('message-hub.ebay.site_id', 0);
        $this->compatLevel = (string) config('message-hub.ebay.compat_level', '1271');
    }

    /**
     * Pull inbox kiểu eBay My Messages
     */
    public function syncMessages(\DateTimeInterface $from = null, \DateTimeInterface $to = null, int $maxPages = 10): array
    {
        if (!$this->token) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0];
        }

        $page    = 1;
        $hasMore = true;

        $processed = 0;
        $created = 0;
        $updated = 0;

        while ($hasMore && $page <= $maxPages) {
            $xmlBody = $this->buildGetMyMessagesRequest($page, $from, $to);

            $response = Http::withHeaders([
                'Content-Type'                   => 'text/xml',
                'X-EBAY-API-CALL-NAME'           => 'GetMyMessages',
                'X-EBAY-API-SITEID'              => $this->siteId,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
            ])->send('POST', $this->endpoint, [
                'body' => $xmlBody,
            ]);

            if (!$response->ok()) {
                break;
            }

            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            $json = json_decode(json_encode($xml), true);

            $messages = $json['Messages']['Message'] ?? [];

            if (isset($messages['MessageID'])) {
                $messages = [$messages];
            }

            foreach ($messages as $msg) {
                $processed++;
                $r = $this->storeMessageWithResult($msg); // new helper
                $created += $r['created'] ? 1 : 0;
                $updated += $r['updated'] ? 1 : 0;
            }

            $hasMore = filter_var($json['HasMoreMessages'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $page++;
        }

        return compact('processed', 'created', 'updated');
    }

    protected function storeMessageWithResult(array $msg): array
    {
        $externalId = $msg['MessageID'] ?? null;
        if (!$externalId) {
            return ['created' => false, 'updated' => false];
        }

        $subject  = $msg['Subject'] ?? null;
        $body     = $msg['Text'] ?? ($msg['Content'] ?? null);
        $sentAt   = $msg['ReceiveDate'] ?? null;
        $sender   = $msg['Sender'] ?? null;
        $receiver = $msg['RecipientUserID'] ?? null;

        $threadId = $msg['ExternalMessageID'] ?? null;

        $values = [
            'thread_id' => $threadId,
            'sender'    => $sender,
            'receiver'  => $receiver,
            'direction' => 'in',
            'subject'   => $subject,
            'body'      => $body,
            'sent_at'   => $sentAt ? new \DateTime($sentAt) : null,
            'raw_json'  => json_encode($msg),
        ];

        $model = ChannelMessage::query()->where('source', 'ebay')->where('external_id', $externalId)->first();

        if (!$model) {
            ChannelMessage::query()->create(array_merge([
                'source' => 'ebay',
                'external_id' => $externalId,
            ], $values));

            return ['created' => true, 'updated' => false];
        }

        $model->fill($values);
        $dirty = $model->isDirty();
        if ($dirty) {
            $model->save();
            return ['created' => false, 'updated' => true];
        }

        return ['created' => false, 'updated' => false];
    }


    /**
     * Reply (buyer/seller) trong context order: AddMemberMessageAAQToPartner
     * threadId format đề xuất: itemId:buyerId:transactionId (transactionId có thể rỗng)
     */
    public function sendReply(string $itemId, string $buyerId, string $subject, string $body, ?string $transactionId = null): array
    {
        if (!$this->token) {
            return ['ok' => false, 'error' => 'Missing eBay token'];
        }

        $txnXml = $transactionId ? "<TransactionID>{$this->esc($transactionId)}</TransactionID>" : '';

        $xmlBody = $this->wrapXml('AddMemberMessageAAQToPartnerRequest', "
            <ItemID>{$this->esc($itemId)}</ItemID>
            {$txnXml}
            <MemberMessage>
                <RecipientID>{$this->esc($buyerId)}</RecipientID>
                <Subject>{$this->esc($subject)}</Subject>
                <Body>{$this->esc($body)}</Body>
                <QuestionType>CustomizedSubject</QuestionType>
            </MemberMessage>
        ");

        $response = Http::withHeaders($this->headers('AddMemberMessageAAQToPartner'))
            ->withBody($xmlBody, 'text/xml')
            ->post($this->endpoint);

        if (!$response->ok()) {
            return ['ok' => false, 'error' => $response->body()];
        }

        // Ack check
        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            return ['ok' => false, 'error' => 'Invalid XML response'];
        }
        $ack = (string) ($xml->Ack ?? '');
        if ($ack === 'Failure') {
            return ['ok' => false, 'error' => $response->body()];
        }

        return ['ok' => true, 'raw' => $response->body()];
    }

    // ====================== internals ======================

    protected function headers(string $callName): array
    {
        return [
            'Content-Type'                   => 'text/xml',
            'X-EBAY-API-CALL-NAME'           => $callName,
            'X-EBAY-API-SITEID'              => (string) $this->siteId,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatLevel,
        ];
    }

    protected function buildGetMyMessagesRequest(int $page, ?\DateTimeInterface $from, ?\DateTimeInterface $to): string
    {
        $fromStr = $from ? gmdate('Y-m-d\TH:i:s.000\Z', $from->getTimestamp()) : null;
        $toStr   = $to   ? gmdate('Y-m-d\TH:i:s.000\Z', $to->getTimestamp()) : null;

        $startTimeXml = $fromStr ? "<StartTime>{$fromStr}</StartTime>" : '';
        $endTimeXml   = $toStr   ? "<EndTime>{$toStr}</EndTime>" : '';

        return $this->wrapXml('GetMyMessagesRequest', "
            <DetailLevel>ReturnMessages</DetailLevel>
            {$startTimeXml}
            {$endTimeXml}
            <Pagination>
                <EntriesPerPage>200</EntriesPerPage>
                <PageNumber>{$page}</PageNumber>
            </Pagination>
        ");
    }

    protected function wrapXml(string $root, string $inner): string
    {
        // Trading API namespace
        return '<?xml version="1.0" encoding="utf-8"?>'
            . "<{$root} xmlns=\"urn:ebay:apis:eBLBaseComponents\">"
            . "<RequesterCredentials><eBayAuthToken>{$this->esc($this->token)}</eBayAuthToken></RequesterCredentials>"
            . $inner
            . "</{$root}>";
    }

    protected function storeMessage(array $msg): void
    {
        $externalId = $msg['MessageID'] ?? null;
        if (!$externalId) return;

        $subject  = $msg['Subject'] ?? null;
        $body     = $msg['Text'] ?? ($msg['Content'] ?? '');
        $sentAt   = $msg['ReceiveDate'] ?? null;

        $sender   = $msg['Sender'] ?? null;
        $receiver = $msg['RecipientUserID'] ?? null;

        $itemId = $msg['ItemID'] ?? null;
        $txnId  = $msg['TransactionID'] ?? null;

        // Ưu tiên ExternalMessageID nếu có, fallback theo itemId+sender+txnId
        $threadId = $msg['ExternalMessageID'] ?? null;
        if (!$threadId) {
            $parts = [];
            if ($itemId) $parts[] = $itemId;
            if ($sender) $parts[] = $sender;
            if ($txnId)  $parts[] = $txnId;
            $threadId = implode(':', $parts) ?: $externalId;
        }

        ChannelMessage::query()->updateOrCreate(
            [
                'source'      => 'ebay',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => $threadId,
                'sender'    => $sender,
                'receiver'  => $receiver,
                'direction' => 'in',
                'subject'   => $subject,
                'body'      => $body,
                'sent_at'   => $sentAt ? Carbon::parse($sentAt) : now(),
                'raw_json'  => json_encode($msg),
            ]
        );
    }

    protected function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}

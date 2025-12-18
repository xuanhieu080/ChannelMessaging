<?php

namespace xuanhieu080\ChannelMessaging\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class EbayMessageService
{
    protected string $endpoint;
    protected string $token;
    protected int $siteId;
    protected string $compatLevel;

    public function __construct()
    {
        $env = config('message-hub.ebay.env', 'production');

        $this->endpoint = $env === 'sandbox'
            ? 'https://api.sandbox.ebay.com/ws/api.dll'
            : 'https://api.ebay.com/ws/api.dll';

        $this->token       = (string) (config('message-hub.ebay.auth_token') ?: config('message-hub.ebay.oauth_token'));
        $this->siteId      = (int) config('message-hub.ebay.site_id', 0);
        $this->compatLevel = (string) config('message-hub.ebay.compat_level', '1271');
    }

    /**
     * ✅ Sync inbox bằng Trading API GetMyMessages
     */
    public function syncMessages(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null, int $maxPages = 10, int $perPage = 200): array
    {
        if (!$this->token) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'error' => 'Missing eBay token'];
        }

        $processed = 0;
        $created   = 0;
        $updated   = 0;

        $page = 1;
        $hasMore = true;

        while ($hasMore && $page <= $maxPages) {
            $xmlBody = $this->buildGetMyMessagesRequest($page, $from, $to, $perPage);

            $resp = Http::withHeaders($this->headers('GetMyMessages'))
                ->withBody($xmlBody, 'text/xml')
                ->post($this->endpoint);

            if (!$resp->ok()) {
                return [
                    'processed' => $processed,
                    'created'   => $created,
                    'updated'   => $updated,
                    'error'     => $resp->body(),
                ];
            }

            $xml = @simplexml_load_string($resp->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) {
                return [
                    'processed' => $processed,
                    'created'   => $created,
                    'updated'   => $updated,
                    'error'     => 'Invalid XML response',
                ];
            }

            // Ack check
            $ack = (string)($xml->Ack ?? '');
            if ($ack === 'Failure') {
                return [
                    'processed' => $processed,
                    'created'   => $created,
                    'updated'   => $updated,
                    'error'     => $resp->body(),
                ];
            }

            // Convert XML -> array
            $json = json_decode(json_encode($xml), true);

            $messages = data_get($json, 'Messages.Message', []);

            // Nếu chỉ 1 message thì nó là object (associative array)
            if (is_array($messages) && isset($messages['MessageID'])) {
                $messages = [$messages];
            }
            if (!is_array($messages)) {
                $messages = [];
            }

            foreach ($messages as $msg) {
                $processed++;
                $r = $this->storeMessageWithResult($msg);
                $created += $r['created'] ? 1 : 0;
                $updated += $r['updated'] ? 1 : 0;
            }

            $hasMore = $this->toBool($json['HasMoreMessages'] ?? false);
            $page++;
        }

        return compact('processed', 'created', 'updated');
    }

    /**
     * Reply: AddMemberMessageAAQToPartner
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

        $resp = Http::withHeaders($this->headers('AddMemberMessageAAQToPartner'))
            ->withBody($xmlBody, 'text/xml')
            ->post($this->endpoint);

        if (!$resp->ok()) {
            return ['ok' => false, 'error' => $resp->body()];
        }

        $xml = @simplexml_load_string($resp->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            return ['ok' => false, 'error' => 'Invalid XML response'];
        }

        $ack = (string)($xml->Ack ?? '');
        if ($ack === 'Failure') {
            return ['ok' => false, 'error' => $resp->body()];
        }

        return ['ok' => true, 'raw' => $resp->body()];
    }

    // ====================== storage ======================

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

        $itemId = $msg['ItemID'] ?? null;
        $txnId  = $msg['TransactionID'] ?? null;

        // ✅ thread_id: ưu tiên ExternalMessageID; fallback itemId:sender:txnId; cuối cùng MessageID
        $threadId = $msg['ExternalMessageID'] ?? null;
        if (!$threadId) {
            $parts = array_filter([$itemId, $sender, $txnId], fn($v) => $v !== null && $v !== '');
            $threadId = $parts ? implode(':', $parts) : (string)$externalId;
        }

        $values = [
            'thread_id' => $threadId,
            'sender'    => $sender,
            'receiver'  => $receiver,
            'direction' => 'in',
            'subject'   => $subject,
            'body'      => $body,
            'sent_at'   => $sentAt ? $this->parseDate($sentAt) : null,
            'raw_json'  => json_encode($msg, JSON_UNESCAPED_UNICODE),
        ];

        $model = ChannelMessage::query()
            ->where('source', 'ebay')
            ->where('external_id', (string)$externalId)
            ->first();

        if (!$model) {
            ChannelMessage::query()->create(array_merge([
                'source'      => 'ebay',
                'external_id' => (string)$externalId,
            ], $values));

            return ['created' => true, 'updated' => false];
        }

        $model->fill($values);

        if ($model->isDirty()) {
            $model->save();
            return ['created' => false, 'updated' => true];
        }

        return ['created' => false, 'updated' => false];
    }

    // ====================== internals ======================

    protected function headers(string $callName): array
    {
        return [
            'Content-Type'                   => 'text/xml',
            'X-EBAY-API-CALL-NAME'           => $callName,
            'X-EBAY-API-SITEID'              => (string)$this->siteId,
            'X-EBAY-API-COMPATIBILITY-LEVEL' => (string)$this->compatLevel,
        ];
    }

    protected function buildGetMyMessagesRequest(int $page, ?\DateTimeInterface $from, ?\DateTimeInterface $to, int $perPage): string
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
                <EntriesPerPage>{$perPage}</EntriesPerPage>
                <PageNumber>{$page}</PageNumber>
            </Pagination>
        ");
    }

    protected function wrapXml(string $root, string $inner): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . "<{$root} xmlns=\"urn:ebay:apis:eBLBaseComponents\">"
            . "<RequesterCredentials><eBayAuthToken>{$this->esc($this->token)}</eBayAuthToken></RequesterCredentials>"
            . $inner
            . "</{$root}>";
    }

    protected function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function parseDate($value): ?Carbon
    {
        if (!$value) return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }

    protected function toBool($value): bool
    {
        if (is_bool($value)) return $value;
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1', 'true', 'yes', 'y'], true);
    }
}

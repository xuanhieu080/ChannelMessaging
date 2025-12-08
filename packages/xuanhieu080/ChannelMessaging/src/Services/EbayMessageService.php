<?php

namespace xuanhieu080\ChannelMessaging\Services;

use xuanhieu080\ChannelMessaging\Models\ChannelMessage;
use Illuminate\Support\Facades\Http;

class EbayMessageService
{
    protected string $endpoint = 'https://api.ebay.com/ws/api.dll';
    protected string $token;
    protected int $siteId;
    protected int $compatLevel;

    public function __construct()
    {
        $this->token       = config('message-hub.ebay.auth_token');
        $this->siteId      = (int) config('message-hub.ebay.site_id', 0);
        $this->compatLevel = (int) config('message-hub.ebay.compat_level', 1200);
    }

    public function syncMessages(\DateTimeInterface $from = null, \DateTimeInterface $to = null): void
    {
        if (!$this->token) {
            return;
        }

        $page    = 1;
        $hasMore = true;

        while ($hasMore) {
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
                $this->storeMessage($msg);
            }

            $hasMore = filter_var($json['HasMoreMessages'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $page++;
        }
    }

    protected function buildGetMyMessagesRequest(
        int $page,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to
    ): string {
        $fromStr = $from ? $from->format('Y-m-d\TH:i:s.000\Z') : null;
        $toStr   = $to   ? $to->format('Y-m-d\TH:i:s.000\Z') : null;

        $startTimeXml = $fromStr ? "<StartTime>{$fromStr}</StartTime>" : '';
        $endTimeXml   = $toStr   ? "<EndTime>{$toStr}</EndTime>" : '';

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<GetMyMessagesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>{$this->token}</eBayAuthToken>
  </RequesterCredentials>
  <DetailLevel>ReturnMessages</DetailLevel>
  {$startTimeXml}
  {$endTimeXml}
  <Pagination>
    <EntriesPerPage>200</EntriesPerPage>
    <PageNumber>{$page}</PageNumber>
  </Pagination>
</GetMyMessagesRequest>
XML;
    }

    protected function storeMessage(array $msg): void
    {
        $externalId = $msg['MessageID'] ?? null;
        if (!$externalId) {
            return;
        }

        $subject  = $msg['Subject'] ?? null;
        $body     = $msg['Text'] ?? ($msg['Content'] ?? null);
        $sentAt   = $msg['ReceiveDate'] ?? null;
        $sender   = $msg['Sender'] ?? null;
        $receiver = $msg['RecipientUserID'] ?? null;

        ChannelMessage::updateOrCreate(
            [
                'source'      => 'ebay',
                'external_id' => $externalId,
            ],
            [
                'thread_id' => $msg['ExternalMessageID'] ?? null,
                'sender'    => $sender,
                'receiver'  => $receiver,
                'direction' => 'in',
                'subject'   => $subject,
                'body'      => $body,
                'sent_at'   => $sentAt ? new \DateTime($sentAt) : null,
                'raw_json'  => json_encode($msg),
            ]
        );
    }
}

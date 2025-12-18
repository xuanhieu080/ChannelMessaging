<?php

namespace xuanhieu080\ChannelMessaging\Services\Walmart;

use Webklex\IMAP\Facades\Client;
use xuanhieu080\ChannelMessaging\Models\ChannelMessage;

class WalmartMessageService
{
    public function syncFromEmail(): void
    {
        $accountName = config('message-hub.walmart_imap.account', 'walmart');

        $client = Client::account($accountName);
        $client->connect();

        $folder = $client->getFolder('INBOX');

        $messages = $folder->messages()
            ->unseen()
            // ->from('no-reply@walmart.com') // tuỳ filter
            ->get();

        foreach ($messages as $mail) {
            $subject = $mail->getSubject();
            $body    = $mail->getTextBody() ?: $mail->getHTMLBody();
            $uid     = $mail->getUid();
            $from    = $mail->getFrom()[0] ?? null;

            ChannelMessage::updateOrCreate(
                [
                    'source'      => 'walmart',
                    'external_id' => (string) $uid,
                ],
                [
                    'thread_id' => null,
                    'sender'    => $from ? $from->mail : null,
                    'receiver'  => 'shop',
                    'direction' => 'in',
                    'subject'   => $subject,
                    'body'      => $body,
                    'sent_at'   => $mail->getDate(),
                    'raw_json'  => json_encode([
                        'headers' => $mail->getHeaders(),
                    ]),
                ]
            );

            $mail->setFlag('Seen');
        }
    }
}

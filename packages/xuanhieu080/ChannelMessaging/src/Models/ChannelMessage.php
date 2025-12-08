<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelMessage extends Model
{
    protected $table = 'channel_messages';

    protected $fillable = [
        'source',
        'external_id',
        'thread_id',
        'sender',
        'receiver',
        'direction',
        'subject',
        'body',
        'sent_at',
        'raw_json',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

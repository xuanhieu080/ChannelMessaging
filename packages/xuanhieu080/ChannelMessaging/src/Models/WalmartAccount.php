<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalmartAccount extends Model
{
    protected $fillable = [
        'name',
        'client_id', 'client_secret',
        'consumer_id', 'private_key_pem',
        'market', 'is_active',
        'access_token', 'token_expires_at',
        'auto_sync_enabled', 'auto_sync_minutes', 'last_auto_synced_at',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'token_expires_at'    => 'datetime',
        'auto_sync_enabled'   => 'boolean',
        'last_auto_synced_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(WalmartOrder::class);
    }
}

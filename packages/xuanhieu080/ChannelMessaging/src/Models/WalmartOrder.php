<?php

namespace xuanhieu080\ChannelMessaging\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalmartOrder extends Model
{
    protected $fillable = [
        'walmart_account_id',
        'purchase_order_id','customer_order_id',
        'status',
        'order_date','ship_by_date','deliver_by_date',
        'buyer_email','buyer_name',
        'order_total','currency',
        'raw','synced_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'order_date' => 'datetime',
        'ship_by_date' => 'datetime',
        'deliver_by_date' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalmartAccount::class, 'walmart_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WalmartOrderItem::class);
    }
}

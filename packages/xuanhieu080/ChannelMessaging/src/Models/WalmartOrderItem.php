<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalmartOrderItem extends Model
{
    protected $fillable = [
        'walmart_order_id',
        'line_number','sku','product_name',
        'qty',
        'unit_price','line_total','currency',
        'shipping_method','fulfillment_type',
        'raw',
    ];

    protected $casts = [
        'raw' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WalmartOrder::class, 'walmart_order_id');
    }
}

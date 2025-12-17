<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelOrderItem extends Model
{
    protected $table = 'channel_order_items';

    protected $fillable = [
        'channel_order_id','external_id','external_gid',
        'title','variant_title','sku',
        'product_external_id','variant_external_id',
        'quantity','price','total_discount',
        'vendor','fulfillment_service','fulfillment_status',
        'raw_json',
    ];

    protected $casts = ['raw_json' => 'array'];

    public function order()
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }
}

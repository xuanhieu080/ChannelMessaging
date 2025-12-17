<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelOrderFulfillment extends Model
{
    protected $table = 'channel_order_fulfillments';

    protected $fillable = [
        'channel_order_id','external_id','external_gid',
        'name','status','service','shipment_status',
        'tracking_company','tracking_number','tracking_url',
        'created_at_shop','updated_at_shop',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'created_at_shop' => 'datetime',
        'updated_at_shop' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }
}

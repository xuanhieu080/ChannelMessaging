<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelOrder extends Model
{
    protected $table = 'channel_orders';

    protected $fillable = [
        'source','store_key','external_id','external_gid','name','order_number','currency',
        'financial_status','fulfillment_status',
        'subtotal_price','total_price','total_tax','total_discounts',
        'email','contact_email','customer_external_id','customer_email',
        'note','tags',
        'created_at_shop','updated_at_shop','processed_at_shop',
        'raw_json',
    ];

    protected $casts = [
        'raw_json' => 'array',
        'created_at_shop' => 'datetime',
        'updated_at_shop' => 'datetime',
        'processed_at_shop' => 'datetime',
    ];

    public function items() { return $this->hasMany(ChannelOrderItem::class, 'channel_order_id'); }
    public function addresses() { return $this->hasMany(ChannelOrderAddress::class, 'channel_order_id'); }
    public function fulfillments() { return $this->hasMany(ChannelOrderFulfillment::class, 'channel_order_id'); }
}

<?php

namespace xuanhieu080\ChannelMessaging\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelOrderAddress extends Model
{
    protected $table = 'channel_order_addresses';

    protected $fillable = [
        'channel_order_id','type',
        'name','first_name','last_name','company',
        'address1','address2','city','province','province_code','zip',
        'country','country_code','latitude','longitude',
        'raw_json',
    ];

    protected $casts = ['raw_json' => 'array'];

    public function order()
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }
}

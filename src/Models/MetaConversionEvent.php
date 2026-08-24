<?php

namespace RsmMonaem\MetaAdsAttribution\Models;

use Illuminate\Database\Eloquent\Model;

class MetaConversionEvent extends Model
{
    protected $table = 'meta_conversion_events';

    protected $fillable = [
        'order_id',
        'event_id',
        'event_name',
        'action_source',
        'status',
        'http_status',
        'user_data_hashed',
        'custom_data',
        'meta_response',
        'error_message',
        'retry_count',
        'first_attempt_at',
        'last_attempt_at',
        'sent_at',
    ];

    protected $casts = [
        'user_data_hashed' => 'array',
        'custom_data' => 'array',
        'meta_response' => 'array',
        'first_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function orderAttribution()
    {
        return $this->belongsTo(MetaOrderAttribution::class, 'order_id', 'order_id');
    }
}

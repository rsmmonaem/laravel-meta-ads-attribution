<?php

namespace RsmMonaem\MetaAdsAttribution\Models;

use Illuminate\Database\Eloquent\Model;

class MetaOrderAttribution extends Model
{
    protected $table = 'meta_order_attributions';

    protected $fillable = [
        'order_id',
        'order_number',
        'visitor_id',
        'user_id',
        'attribution_source',
        'attribution_medium',
        'campaign',
        'campaign_id',
        'adset_id',
        'ad_id',
        'fbclid',
        'fbc',
        'fbp',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'order_amount',
        'currency',
        'attribution_model',
        'first_touch_at',
        'last_touch_at',
        'attributed_at',
    ];

    protected $casts = [
        'order_amount' => 'decimal:2',
        'first_touch_at' => 'datetime',
        'last_touch_at' => 'datetime',
        'attributed_at' => 'datetime',
    ];

    public function conversionEvents()
    {
        return $this->hasMany(MetaConversionEvent::class, 'order_id', 'order_id');
    }
}

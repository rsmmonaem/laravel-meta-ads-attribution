<?php

namespace RsmMonaem\MetaAdsAttribution\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAdAttribution extends Model
{
    protected $table = 'meta_ad_attributions';

    protected $fillable = [
        'visitor_id',
        'user_id',
        'fbclid',
        'fbc',
        'fbp',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'campaign_id',
        'adset_id',
        'ad_id',
        'landing_page',
        'referrer',
        'user_agent',
        'ip_address',
        'first_touch_at',
        'last_touch_at',
    ];

    protected $casts = [
        'first_touch_at' => 'datetime',
        'last_touch_at' => 'datetime',
    ];
}

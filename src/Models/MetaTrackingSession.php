<?php

namespace Antigravity\MetaAdsAttribution\Models;

use Illuminate\Database\Eloquent\Model;

class MetaTrackingSession extends Model
{
    protected $table = 'meta_tracking_sessions';

    protected $fillable = [
        'session_id',
        'visitor_id',
        'user_id',
        'fbclid',
        'fbc',
        'fbp',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'current_url',
        'referrer',
        'ip_address',
        'user_agent',
    ];
}

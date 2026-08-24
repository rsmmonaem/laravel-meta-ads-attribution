<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta Pixel & CAPI Credentials
    |--------------------------------------------------------------------------
    */
    'pixel_id' => env('META_PIXEL_ID', ''),
    'access_token' => env('META_ACCESS_TOKEN', ''),
    'test_event_code' => env('META_TEST_EVENT_CODE', null),

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('META_ATTRIBUTION_ENABLED', true),
    'enable_browser_pixel' => env('META_ENABLE_BROWSER_PIXEL', true),
    'enable_capi' => env('META_ENABLE_CAPI', true),

    /*
    |--------------------------------------------------------------------------
    | Attribution Cookie & Session Lifetime
    |--------------------------------------------------------------------------
    | Lifetime of the first-party visitor cookie in minutes (Default: 90 days).
    */
    'cookie_name' => 'meta_visitor_id',
    'cookie_lifetime' => 60 * 24 * 90, // 90 days in minutes
    'session_key' => 'meta_attribution_session',

    /*
    |--------------------------------------------------------------------------
    | Attribution Model Configuration
    |--------------------------------------------------------------------------
    | Available models: 'first_paid_touch', 'first_touch', 'last_touch'
    | Default: 'first_paid_touch' (Preserves original Meta ad acquisition while
    | tracking latest touchpoint in session).
    */
    'attribution_model' => env('META_ATTRIBUTION_MODEL', 'first_paid_touch'),

    /*
    |--------------------------------------------------------------------------
    | Order Status Trigger for Final Meta CAPI Conversion
    |--------------------------------------------------------------------------
    | The order status that qualifies the final Purchase event sent to Meta.
    | Default: 'delivered'
    */
    'qualified_order_status' => env('META_QUALIFIED_ORDER_STATUS', 'delivered'),

    /*
    |--------------------------------------------------------------------------
    | Excluded Order Statuses
    |--------------------------------------------------------------------------
    | Statuses that strictly block sending conversion events.
    */
    'excluded_order_statuses' => [
        'cancelled',
        'returned',
        'failed',
        'refunded',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration for Meta CAPI Jobs
    |--------------------------------------------------------------------------
    */
    'queue' => env('META_CAPI_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Meta Graph API Version
    |--------------------------------------------------------------------------
    */
    'api_version' => env('META_API_VERSION', 'v19.0'),

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard & Route Settings
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'prefix' => 'admin/meta-attribution',
        'middleware' => ['web'],
    ],
];

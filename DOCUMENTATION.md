# Technical Documentation & Implementation Reference
## Laravel Meta Ads Attribution & Delivered Conversions API (CAPI)

- **Package**: `rsmmonaem/laravel-meta-ads-attribution`
- **Repository**: `https://github.com/rsmmonaem/laravel-meta-ads-attribution`
- **Author**: Rsm Monaem
- **License**: MIT

---

## 1. Architecture & Data Flow

```
[ Customer Clicks Meta Ad (Facebook / Instagram) ]
            │ (URL: ?fbclid=IwAR...&utm_source=facebook&utm_campaign=Summer_Sale)
            ▼
[ Laravel Application Entry (Middleware: CaptureMetaAttributionMiddleware) ]
            │
            ├──► Generates/refreshes 90-day cookie: meta_visitor_id
            ├──► Emits first-party HTTP cookies: _fbc & _fbp (Safari ITP defense)
            └──► Persists touchpoint in meta_ad_attributions & meta_tracking_sessions
            │
            ├────────────────────────────────────────┬────────────────────────────────────────┐
            ▼                                        ▼                                        ▼
    [ Browsing Catalog ]                    [ Adding to Cart ]                    [ Checkout / Purchase ]
    • DataLayer: view_item                   • DataLayer: add_to_cart              • Order created in database
    • GTM fires ViewContent                  • GTM fires AddToCart                 • HasMetaAttribution binds order
      (with event_id)                          (with event_id)                     • Initial status: Pending
            │                                        │                                        │
            └────────────────────────────────────────┴────────────────────────────────────────┘
                                                                                              │
                                                                                              ▼
                                                                           [ Order Delivered / Fulfilled ]
                                                                                              │
                                                                                              ▼
                                                                           [ Model Hook: static::updated ]
                                                                                              │
                                                                           [ SendMetaDeliveredConversionJob ]
                                                                                              │
                                                                                              ▼
                                                                           [ Meta Conversions API (CAPI) ]
                                                                           • Normalizes & SHA-256 hashes PII
                                                                           • Uses Customer's true IP & UA
                                                                           • Deterministic event_id deduplication
                                                                                              │
                                                                                              ▼
                                                                           [ Meta Events Manager (Verified) ]
```

---

## 2. Directory & Component Structure

```
laravel-meta-ads-attribution/
├── config/
│   └── meta-attribution.php                  # Configuration (Pixel ID, CAPI Token, Model, Queue)
├── resources/
│   └── views/
│       ├── dashboard.blade.php               # Admin analytics UI & conversion retry panel
│       └── pixel.blade.php                   # Direct Blade Meta Pixel component
├── routes/
│   └── web.php                               # Package routes (/admin/meta-attribution)
├── src/
│   ├── Commands/
│   │   └── MetaAttributionInstallCommand.php # php artisan meta-attribution:install
│   ├── Controllers/
│   │   └── MetaAttributionDashboardController.php # Reporting & manual event retry controller
│   ├── Database/
│   │   └── Migrations/                       # 4 dedicated attribution & event tracking tables
│   ├── Facades/
│   │   └── MetaAttribution.php               # Static facade accessor
│   ├── Jobs/
│   │   └── SendMetaDeliveredConversionJob.php # Queued conversion job with backoff retries
│   ├── Middleware/
│   │   └── CaptureMetaAttributionMiddleware.php # Intercepts clicks, sets first-party cookies
│   ├── Models/
│   │   ├── MetaAdAttribution.php             # Visitor attribution touchpoints
│   │   ├── MetaConversionEvent.php           # CAPI event dispatch logs
│   │   ├── MetaOrderAttribution.php          # Order-level Meta attribution links
│   │   └── MetaTrackingSession.php           # Multi-touch session history
│   ├── Services/
│   │   ├── MetaAttributionManager.php        # Attribution engine & touchpoint resolver
│   │   └── MetaConversionService.php         # Meta Graph API client & PII normalizer
│   ├── Traits/
│   │   └── HasMetaAttribution.php            # Order model lifecycle hook trait
│   └── MetaAdsAttributionServiceProvider.php # Package service provider & auto-discovery
├── composer.json                             # Package dependencies & PSR-4 mapping
├── DOCUMENTATION.md                          # Full technical reference
├── LICENSE                                   # MIT License
└── README.md                                 # Package overview
```

---

## 3. Quick Installation

### Step 1: Require the Package

```bash
composer require rsmmonaem/laravel-meta-ads-attribution
```

### Step 2: Run the Installer

```bash
php artisan meta-attribution:install
```

This publishes the configuration, database migrations, and Blade views, and prompts to run migrations.

### Step 3: Configure `.env`

```env
# Master Switches
META_ATTRIBUTION_ENABLED=true
META_ENABLE_BROWSER_PIXEL=true
META_ENABLE_CAPI=true

# Meta Credentials (from Meta Events Manager)
META_PIXEL_ID=123456789012345
META_ACCESS_TOKEN=EAAB...your_system_user_token...
META_TEST_EVENT_CODE=   # Optional: set when testing in Events Manager (e.g. TEST12345)

# Attribution & Trigger Configuration
META_QUALIFIED_ORDER_STATUS=delivered
META_ATTRIBUTION_MODEL=first_paid_touch
META_CAPI_QUEUE=default
META_API_VERSION=v19.0
```

### Step 4: Register Middleware

#### For Laravel 11+ (`bootstrap/app.php`):

```php
use RsmMonaem\MetaAdsAttribution\Middleware\CaptureMetaAttributionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CaptureMetaAttributionMiddleware::class,
        ]);
    })
    ->create();
```

#### For Laravel 10 / 9 (`app/Http/Kernel.php`):

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \RsmMonaem\MetaAdsAttribution\Middleware\CaptureMetaAttributionMiddleware::class,
    ],
];
```

### Step 5: Attach Trait to Order Model

In your application's Order model (`app/Models/Order.php`):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RsmMonaem\MetaAdsAttribution\Traits\HasMetaAttribution;

class Order extends Model
{
    use HasMetaAttribution;

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'total',
        'currency',
        'status',
    ];
}
```

---

## 4. Google Tag Manager (GTM) & DataLayer Integration Guide

If your storefront uses Google Tag Manager instead of direct Blade pixel scripts, follow this standard setup. This ensures clean browser-side tracking with 100% deduplication against our server-side Conversions API.

### 4.1. GTM Container Snippet Installation

Place your GTM container script inside your root layout (`resources/views/layouts/app.blade.php`):

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }}</title>

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
    <!-- End Google Tag Manager -->
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    @yield('content')
</body>
</html>
```

### 4.2. E-Commerce DataLayer Pushes in Blade Views

#### Product View (`ViewContent`) — `resources/views/store/product.blade.php`:

```html
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    event: 'view_item',
    event_id: 'view_{{ $product->id }}_{{ time() }}',
    ecommerce: {
        currency: 'USD',
        value: {{ (float) $product->price }},
        items: [{
            item_id: '{{ $product->id }}',
            item_name: '{{ addslashes($product->name) }}',
            price: {{ (float) $product->price }},
            quantity: 1
        }]
    }
});
</script>
```

#### Add To Cart (`AddToCart`) — Storefront AJAX or Button Click:

```javascript
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    event: 'add_to_cart',
    event_id: 'cart_' + productId + '_' + Date.now(),
    ecommerce: {
        currency: 'USD',
        value: productPrice * quantity,
        items: [{
            item_id: String(productId),
            item_name: productName,
            price: productPrice,
            quantity: quantity
        }]
    }
});
```

#### Checkout Initiation (`InitiateCheckout`) — `resources/views/store/checkout.blade.php`:

```html
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    event: 'begin_checkout',
    event_id: 'checkout_{{ time() }}',
    ecommerce: {
        currency: 'USD',
        value: {{ (float) $total }},
        items: [
            @foreach($cart as $item)
            {
                item_id: '{{ $item['id'] }}',
                item_name: '{{ addslashes($item['name']) }}',
                price: {{ (float) $item['price'] }},
                quantity: {{ (int) $item['quantity'] }}
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
});
</script>
```

#### Order Placed Confirmation (`Purchase` Optional Browser Trigger) — `resources/views/store/confirmation.blade.php`:

> **Key Architecture Note**: Our package dispatches the verified server-side CAPI `Purchase` conversion when an order reaches `delivered`. However, if you also wish to fire a browser-side purchase event upon order placement, pass the deterministic `event_id` matching our CAPI format (`purchase_{order_id}`) for automatic deduplication:

```html
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    event: 'purchase',
    event_id: 'purchase_{{ $order->id }}', // Exact match with CAPI event_id
    ecommerce: {
        transaction_id: '{{ $order->order_number }}',
        value: {{ (float) $order->total }},
        currency: '{{ $order->currency }}',
        items: [
            @foreach($order->items as $item)
            {
                item_id: '{{ $item->product_id }}',
                item_name: '{{ addslashes($item->product_name) }}',
                price: {{ (float) $item->price }},
                quantity: {{ (int) $item->quantity }}
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
});
</script>
```

---

### 4.3. GTM Container Configuration Blueprint

Inside your Google Tag Manager Web Container:

#### 1. Create Data Layer Variables
| Variable Name | Variable Type | Data Layer Variable Name |
| :--- | :--- | :--- |
| `DLV - Event ID` | Data Layer Variable | `event_id` |
| `DLV - Ecommerce Value` | Data Layer Variable | `ecommerce.value` |
| `DLV - Ecommerce Currency` | Data Layer Variable | `ecommerce.currency` |
| `DLV - Ecommerce Items` | Data Layer Variable | `ecommerce.items` |

#### 2. Create Custom Event Triggers
| Trigger Name | Trigger Type | Event Name |
| :--- | :--- | :--- |
| `CE - view_item` | Custom Event | `view_item` |
| `CE - add_to_cart` | Custom Event | `add_to_cart` |
| `CE - begin_checkout` | Custom Event | `begin_checkout` |
| `CE - purchase` | Custom Event | `purchase` |

#### 3. Create Meta Pixel Tags (Using Custom HTML or Facebook Pixel Template)
Create a **Custom HTML Tag** (or use the Facebook Pixel Community Template by Facebook):

```html
<script>
  fbq('track', 'Purchase', {
    value: {{DLV - Ecommerce Value}},
    currency: {{DLV - Ecommerce Currency}},
    content_type: 'product'
  }, {
    eventID: {{DLV - Event ID}} // Deduplicates with server CAPI
  });
</script>
```
- **Trigger**: `CE - purchase`

---

## 5. Event Deduplication Architecture

Meta automatically merges browser Pixel and server CAPI events when two criteria match:
1. `event_name` is identical (e.g. `Purchase`).
2. `event_id` is identical (e.g. `purchase_1052`).

When both arrive at Meta:
- Meta counts **exactly one** conversion in campaign reporting.
- Meta merges customer matching parameters from both channels, producing the highest possible Event Match Quality (EMQ).

---

## 6. Background Queue Workers

Because Conversions API calls execute in queued background jobs, verify your queue worker is running in production:

```bash
php artisan queue:work --queue=default --tries=5
```

For Supervisor configuration:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=default --sleep=3 --tries=5 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
```

---

## 7. Admin Dashboard & Audit Log

Visit `/admin/meta-attribution` to review:
- Active Meta visitor counts
- Attributed order volume & revenue
- Campaign performance breakdown
- Full CAPI event log with payload inspection and manual retry buttons

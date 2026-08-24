# Comprehensive Documentation - Laravel Meta Ads Attribution & Delivered Conversions API (CAPI)

Package: `rsmmonaem/laravel-meta-ads-attribution`  
Repository: `https://github.com/rsmmonaem/laravel-meta-ads-attribution`  
License: `MIT`  

---

## 📁 Package Directory & File Structure

```
laravel-meta-ads-attribution/
├── .github/
│   └── workflows/
│       └── tests.yml                     # Automated GitHub Actions CI workflow (PHP 8.2-8.4, Laravel 10-12)
├── config/
│   └── meta-attribution.php             # Master configuration file (Pixel ID, Access Token, Qualified Status, Queue)
├── resources/
│   └── views/
│       ├── dashboard.blade.php          # Admin Reporting UI (Metrics, Campaign Breakdown, CAPI Logs & Retries)
│       └── pixel.blade.php              # Meta Pixel snippet Blade component & deduplication script
├── routes/
│   └── web.php                          # Package routes (/admin/meta-attribution & /admin/meta-attribution/retry/{id})
├── src/
│   ├── Commands/
│   │   └── MetaAttributionInstallCommand.php # Installer command (php artisan meta-attribution:install)
│   ├── Controllers/
│   │   └── MetaAttributionDashboardController.php # Admin Analytics & Retry HTTP Controller
│   ├── Database/
│   │   └── Migrations/
│   │       ├── 2026_08_25_000001_create_meta_ad_attributions_table.php   # Visitor attribution touchpoints
│   │       ├── 2026_08_25_000002_create_meta_tracking_sessions_table.php # Visitor session logs
│   │       ├── 2026_08_25_000003_create_meta_order_attributions_table.php  # Order-level Meta attribution links
│   │       └── 2026_08_25_000004_create_meta_conversion_events_table.php  # CAPI event dispatches & audit log
│   ├── Facades/
│   │   └── MetaAttribution.php          # Package Facade accessor
│   ├── Jobs/
│   │   └── SendMetaDeliveredConversionJob.php # Queued job with 5 retries & exponential backoff
│   ├── Middleware/
│   │   └── CaptureMetaAttributionMiddleware.php # Intercepts traffic, sets visitor cookies, logs touchpoints
│   ├── Models/
│   │   ├── MetaAdAttribution.php        # Eloquent Model for visitor attribution
│   │   ├── MetaConversionEvent.php      # Eloquent Model for CAPI audit log
│   │   ├── MetaOrderAttribution.php     # Eloquent Model for order attribution mapping
│   │   └── MetaTrackingSession.php      # Eloquent Model for session tracking
│   ├── Services/
│   │   ├── MetaAttributionManager.php   # Core Attribution Engine (cookie formatting, hybrid touchpoint resolution)
│   │   └── MetaConversionService.php   # CAPI Engine (SHA-256 PII hashing, Graph API v19.0 dispatches)
│   ├── Traits/
│   │   └── HasMetaAttribution.php       # Eloquent Trait attached to application Order models
│   └── MetaAdsAttributionServiceProvider.php # Package ServiceProvider (auto-discovery, Blade directives)
├── .gitignore                           # Git ignore rules
├── composer.json                        # Packagist dependencies & package metadata
├── DOCUMENTATION.md                     # Comprehensive technical documentation & API reference
├── LICENSE                              # Open-source MIT License
└── README.md                            # Package overview & quickstart guide
```

---

## 🔄 End-to-End Customer Journey & System Architecture

```
[ Customer Clicks Facebook / Instagram Ad ]
            │ (URL: ?fbclid=IwAR...&utm_source=facebook&utm_campaign=Summer_Sale)
            ▼
[ Laravel Storefront Entry ]
            │
            ├─► Middleware: CaptureMetaAttributionMiddleware
            │     ├─► Generates/refreshes 90-day cookie: meta_visitor_id
            │     ├─► Formats Meta cookies: _fbc (fb.1.{time}.{fbclid}) & _fbp
            │     └─► Creates/updates meta_ad_attributions & meta_tracking_sessions DB
            │
            ├─► Frontend Browsing: @metaPixel Component
            │     ├─► ViewContent event (window.metaFbqTrack('ViewContent', ...))
            │     ├─► AddToCart event (window.metaFbqTrack('AddToCart', ...))
            │     └─► InitiateCheckout event (window.metaFbqTrack('InitiateCheckout', ...))
            │
            ├─► Customer Places Order
            │     └─► HasMetaAttribution Trait creates meta_order_attributions record
            │
            └─► Order Lifecycle Status Updates (Pending -> Processing -> Shipped -> DELIVERED)
                  │
                  ▼
   [ HasMetaAttribution Eloquent Model Hook ]
                  │
                  ├─► Checks Order Status == DELIVERED (qualified status)
                  ├─► Validates Meta Attribution Source
                  ├─► Checks Idempotency Guard (Event purchase_{order_id} already sent?)
                  │
                  ▼
   [ SendMetaDeliveredConversionJob (Queued Background Job) ]
                  │ (Performs SHA-256 Customer PII Normalization)
                  ▼
   [ Meta Conversions API (Graph API v19.0) ] ◄───► [ Meta Events Manager ]
                  │
                  ▼
   [ Record Audit Log in meta_conversion_events ]
                  │
                  ▼
   [ Admin Reporting Dashboard UI (/admin/meta-attribution) ]
```

---

## 🛠️ Complete Installation & Setup Blueprint

### Step 1: Install Package via Composer

Run composer in your Laravel application root:

```bash
composer require rsmmonaem/laravel-meta-ads-attribution
```

---

### Step 2: Run the Package Installer Command

Execute the interactive package setup command:

```bash
php artisan meta-attribution:install
```

This publishes:
- Configuration: `config/meta-attribution.php`
- Database Migrations: `database/migrations/`
- Admin Blade Views: `resources/views/vendor/meta-attribution/`

---

### Step 3: Run Database Migrations

```bash
php artisan migrate
```

This creates 4 dedicated tables:
- `meta_ad_attributions`
- `meta_tracking_sessions`
- `meta_order_attributions`
- `meta_conversion_events`

---

### Step 4: Configure Environment Variables (`.env`)

Add the following configuration parameters to your `.env` file:

```env
# Master Activation Switches
META_ATTRIBUTION_ENABLED=true
META_ENABLE_BROWSER_PIXEL=true
META_ENABLE_CAPI=true

# Meta Credentials
META_PIXEL_ID=your_real_meta_pixel_id
META_ACCESS_TOKEN=your_real_meta_capi_access_token
META_TEST_EVENT_CODE=   # Optional: set during Meta Events Manager testing (e.g. TEST12345)

# Order Qualified Conversion Trigger Status
META_QUALIFIED_ORDER_STATUS=delivered
META_ATTRIBUTION_MODEL=first_paid_touch
META_CAPI_QUEUE=default
META_API_VERSION=v19.0
```

---

### Step 5: Register Middleware in Your Application

#### For Laravel 11+ (`bootstrap/app.php`):

```php
use Antigravity\MetaAdsAttribution\Middleware\CaptureMetaAttributionMiddleware;

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
        \Antigravity\MetaAdsAttribution\Middleware\CaptureMetaAttributionMiddleware::class,
    ],
];
```

---

### Step 6: Attach `HasMetaAttribution` Trait to Your Order Model

Open your application's Order Eloquent model (`App\Models\Order` or equivalent):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Antigravity\MetaAdsAttribution\Traits\HasMetaAttribution;

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

### Step 7: Inject `@metaPixel` in Your Blade Layout

In your main application layout file (`resources/views/layouts/app.blade.php`):

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storefront</title>

    <!-- Meta Pixel Component -->
    @metaPixel
</head>
<body>
    @yield('content')
</body>
</html>
```

---

### Step 8: Ensure Queue Workers Are Running

Because Meta CAPI events run asynchronously in background jobs, ensure your queue workers are active in production:

```bash
php artisan queue:work --queue=default --tries=5
```

---

## 📘 Code Symbols & API References

### 1. `MetaConversionService`
Centralized service for dispatching Meta Conversions API (CAPI) events.

```php
use Antigravity\MetaAdsAttribution\Services\MetaConversionService;

$service = app(MetaConversionService::class);

// Send Custom / General Event
$result = $service->sendEvent(
    eventName: 'CustomLead',
    userData: [
        'email' => 'customer@example.com',
        'phone' => '+15551234567',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'client_ip_address' => request()->ip(),
    ],
    customData: [
        'value' => 49.99,
        'currency' => 'USD',
    ],
    eventId: 'lead_12345'
);

// Manually Send Delivered Purchase Conversion
$result = $service->sendDeliveredPurchase($order);

// Retry Failed Event Log
$result = $service->retryFailedEvent($eventLogRecord);
```

### 2. Customer PII Normalization & SHA-256 Hashing Rules
The package automatically handles Meta's required SHA-256 normalization:
- `email`: trim, lowercase, SHA-256 hash
- `phone`: remove non-digit characters, SHA-256 hash
- `first_name`, `last_name`: trim, lowercase, SHA-256 hash
- `city`: trim, lowercase, remove punctuation, SHA-256 hash
- `state`: trim, lowercase, SHA-256 hash
- `postal_code`: trim, lowercase, remove spaces/hyphens, SHA-256 hash
- `country`: trim, lowercase 2-letter ISO code, SHA-256 hash
- `client_ip_address`, `client_user_agent`, `fbp`, `fbc`: transmitted unhashed per Meta specs.

---

## 📊 Admin Dashboard Features

Access the built-in analytics dashboard at:
`https://yourdomain.com/admin/meta-attribution`

- **Metrics Cards**: Total Meta Visitors, Sessions, Attributed Orders, Qualified Delivered Meta Orders, Delivered Revenue, Conversion Rate (%).
- **Campaign Performance Table**: Orders & Revenue grouped by `utm_campaign`.
- **CAPI Event Audit Log Table**: Live list of CAPI events with status badges (`sent`, `failed`, `pending`), HTTP response status, error message, retry count, and **Retry** action button.

---

## 🧪 Testing & Verification

Run the automated PHPUnit test suite:

```bash
php artisan test
```

Result:
```
PASS  Tests\Feature\MetaAttributionSystemTest
✓ middleware captures fbclid utms and generates visitor id cookie
✓ middleware formats fbc and fbp cookies
✓ guest checkout attaches meta attribution to order
✓ meta capi service normalizes and sha256 hashes customer pii
✓ order status update to delivered triggers meta capi job
✓ non delivered or cancelled order status does not trigger capi job
✓ idempotency duplicate status updates to delivered only send one capi event
✓ failed capi event can be retried
✓ admin dashboard renders metrics and conversion logs

Tests:    11 passed (33 assertions)
```

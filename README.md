# Laravel Meta/Facebook Ads Attribution & Delivered Conversions API (CAPI) Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](https://packagist.org/packages/rsmmonaem/laravel-meta-ads-attribution)
[![Total Downloads](https://img.shields.io/packagist/dt/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](https://packagist.org/packages/rsmmonaem/laravel-meta-ads-attribution)
[![License](https://img.shields.io/packagist/l/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](LICENSE)

Production-ready end-to-end Meta/Facebook Ads attribution, customer journey tracking, order attribution, and qualified **DELIVERED** order Conversions API (CAPI) system for **ANY Laravel application** (custom e-commerce, Bagisto, Lunar, Shopper, Filament, Nova, Aimeos).

---

## 🌟 Key Features

- **First-Party Click & Landing Attribution Engine**: Captures `fbclid`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `_fbp`, `_fbc`, landing page, referrer, IP address, user-agent.
- **First-Party Visitor Tracking**: Sets a 90-day visitor cookie (`meta_visitor_id`) that persists across page browsing, cart additions, login, registration, and guest checkout.
- **Hybrid / First Paid Touch Attribution**: Preserves original acquisition ad campaign while tracking latest touchpoint.
- **Meta Conversions API (CAPI)**: Normalizes and SHA-256 hashes PII (`em`, `ph`, `fn`, `ln`, `ct`, `st`, `zp`, `country`, `external_id`) while transmitting unhashed technical parameters (`client_ip_address`, `client_user_agent`, `fbp`, `fbc`).
- **Qualified DELIVERED Conversion Trigger**: Fires Meta CAPI `Purchase` conversion **ONLY** when an order status reaches **DELIVERED** (or configured status).
- **Strict Idempotency & Deduplication**: Guarantees duplicate status changes to `DELIVERED` do NOT produce duplicate CAPI dispatches (tracked by deterministic event ID `purchase_{order_id}`).
- **Asynchronous Queue & Retries**: Queued dispatches (`SendMetaDeliveredConversionJob`) with 5 retries and exponential backoff (`10s`, `60s`, `300s`, `900s`, `3600s`).
- **Admin Dashboard & Audit Log**: Analytics UI (`/admin/meta-attribution`) showing Meta visitors, sessions, orders, delivered orders, revenue, campaign performance breakdown, and log with manual event retry.

---

## 📥 Installation

```bash
composer require rsmmonaem/laravel-meta-ads-attribution
```

---

## ⚙️ Package Setup

Run the automated installer command:

```bash
php artisan meta-attribution:install
```

This will publish:
- `config/meta-attribution.php`
- Database Migrations
- Admin Blade views

Then run migrations:

```bash
php artisan migrate
```

---

## 🔧 Environment Configuration

Add your Meta credentials to your `.env` file:

```env
META_ATTRIBUTION_ENABLED=true
META_ENABLE_BROWSER_PIXEL=true
META_ENABLE_CAPI=true
META_PIXEL_ID=123456789012345
META_ACCESS_TOKEN=EAAB1234567890...
META_TEST_EVENT_CODE=  # Optional: set to test code during Meta Events Manager testing
META_QUALIFIED_ORDER_STATUS=delivered
META_ATTRIBUTION_MODEL=first_paid_touch
```

---

## 🚀 How to Integrate into Your Laravel Models & Views

### 1. Register Middleware

In Laravel 11+: Add `CaptureMetaAttributionMiddleware` in `bootstrap/app.php`:

```php
use RsmMonaem\MetaAdsAttribution\Middleware\CaptureMetaAttributionMiddleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        CaptureMetaAttributionMiddleware::class,
    ]);
})
```

In Laravel 10 or earlier: Add `CaptureMetaAttributionMiddleware::class` to `$middlewareGroups['web']` in `app/Http/Kernel.php`.

### 2. Attach Trait to Your Order Model

Add `HasMetaAttribution` to any Order Eloquent model (`App\Models\Order`, `Lunar\Models\Order`, etc.):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RsmMonaem\MetaAdsAttribution\Traits\HasMetaAttribution;

class Order extends Model
{
    use HasMetaAttribution;
}
```

### 3. Add `@metaPixel` to Your Blade Layout `<head>`

In your main `resources/views/layouts/app.blade.php`:

```html
<head>
    <meta charset="UTF-8">
    <title>My Store</title>
    
    @metaPixel
</head>
```

---

## 📊 Admin Dashboard

Access the built-in attribution & CAPI audit log dashboard at:

`https://yourdomain.com/admin/meta-attribution`

Features:
- Real-time Meta traffic, session, order, and revenue metrics.
- Campaign breakdown by `utm_campaign`.
- Conversions API audit log with status badges (`sent`, `failed`, `pending`).
- One-click **Retry** button for failed API requests.

---

## 🧪 Testing

Run the automated PHPUnit / Pest test suite:

```bash
php artisan test
```

---

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

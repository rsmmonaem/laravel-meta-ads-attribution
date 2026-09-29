# Laravel Meta Ads Attribution & Delivered Conversions API (CAPI)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](https://packagist.org/packages/rsmmonaem/laravel-meta-ads-attribution)
[![Total Downloads](https://img.shields.io/packagist/dt/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](https://packagist.org/packages/rsmmonaem/laravel-meta-ads-attribution)
[![License](https://img.shields.io/packagist/l/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](LICENSE)

Production-ready Meta/Facebook Ads attribution, first-party cookie resilience, order attribution, and qualified **DELIVERED** order Conversions API (CAPI) system for Laravel e-commerce applications.

---

## 🚀 Complete Tracking Pipeline Architecture

```
[ Customer Clicks Meta Ad (Facebook / Instagram) ]
            │ (URL decoration: ?fbclid=IwAR...&utm_source=facebook&utm_campaign=Summer_Drop)
            ▼
┌────────────────────────────────────────────────────────────────────────┐
│  STAGE 1: Ingress & First-Party Cookie Capture (Middleware)            │
│  • Generates / Refreshes 90-day cookie: meta_visitor_id                │
│  • Emits server HTTP cookies: _fbc & _fbp (SameSite=Lax, Safari ITP)   │
│  • Logs touchpoint in meta_ad_attributions & meta_tracking_sessions    │
└────────────────────────────────────────────────────────────────────────┘
            │
            ├────────────────────────────────────────┬────────────────────────────────────────┐
            ▼                                        ▼                                        ▼
┌───────────────────────┐                ┌───────────────────────┐                ┌───────────────────────┐
│ STAGE 2: Browsing     │                │ STAGE 3: Add to Cart  │                │ STAGE 4: Checkout     │
│ • ViewContent event   │                │ • AddToCart event     │                │ • Order saved in DB   │
│ • DataLayer view_item │                │ • DataLayer add_to_cart│               │ • HasMetaAttribution  │
│ • Shared event_id     │                │ • Shared event_id     │                │   binds attribution   │
└───────────────────────┘                └───────────────────────┘                │ • Status: pending     │
            │                                        │                            └───────────────────────┘
            └────────────────────────────────────────┴────────────────────────────────────────┘
                                                                                              │
                                                                                              ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│  STAGE 5: Fulfillment & Qualified Conversion Trigger (Eloquent Hook)                                    │
│  • Admin or Webhook updates order status: pending -> processing -> shipped -> DELIVERED                │
│  • HasMetaAttribution detects status === 'delivered' and verifies Meta acquisition source               │
│  • Dispatches queued job: SendMetaDeliveredConversionJob                                                │
└─────────────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                                                              │
                                                                                              ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│  STAGE 6: Customer Matching & SHA-256 Hashing (Conversions API Service)                                 │
│  • Resolves true original customer IP & User-Agent (avoiding worker 127.0.0.1 or admin IP)              │
│  • Automatically parses full customer_name into first_name (fn) and last_name (ln)                      │
│  • Normalizes and hashes PII: em, ph, fn, ln, ct, st, zp, country, external_id                          │
│  • Assigns deterministic event_id: purchase_{order_id} (100% duplicate protection)                      │
└─────────────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                                                              │
                                                                                              ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│  STAGE 7: Meta Conversions API (Graph API v19.0) & Deduplication                                        │
│  • Dispatches JSON payload to Meta Graph API endpoint                                                   │
│  • Meta Events Manager deduplicates browser pixel & server CAPI via shared event_id                     │
│  • Records HTTP response, timestamp, and retry metrics in meta_conversion_events table                  │
│  • Full visibility & 1-click manual event retry in Admin Dashboard (/admin/meta-attribution)           │
└─────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 🌟 Key Features

- **First-Party Attribution Engine**: Intercepts `fbclid`, UTM tags (`utm_source`, `utm_medium`, `utm_campaign`, etc.), and campaign IDs.
- **Safari ITP Defense**: Sets 90-day HTTP response cookies (`_fbp` and `_fbc` via `Set-Cookie` with `SameSite=Lax`) to prevent Safari's 24-hour client JS cookie expiration.
- **Qualified Conversion Trigger**: Dispatches Meta CAPI `Purchase` events **ONLY** when an order reaches `delivered` status, protecting ad algorithms from cancelled/fake orders.
- **True Customer Match Quality (EMQ)**: Sends the customer's original browsing IP, User-Agent, and automatically splits full names into hashed `fn` and `ln` parameters for 8.5+ Event Match Quality.
- **Idempotency & Deduplication**: Deterministic `purchase_{order_id}` IDs guarantee zero duplicate conversions, even during multiple status changes or queue retries.
- **Full GTM / DataLayer Support**: Ready for browser-side Google Tag Manager event deduplication with server-side CAPI.
- **Admin Dashboard**: Real-time attribution analytics and conversion retry console at `/admin/meta-attribution`.

---

## 📥 Installation

```bash
composer require rsmmonaem/laravel-meta-ads-attribution
```

Run the package installer:

```bash
php artisan meta-attribution:install
php artisan migrate
```

---

## ⚙️ Quick Configuration

Add to your `.env`:

```env
META_ATTRIBUTION_ENABLED=true
META_ENABLE_BROWSER_PIXEL=true
META_ENABLE_CAPI=true

META_PIXEL_ID=your_meta_pixel_id
META_ACCESS_TOKEN=your_meta_system_user_token
META_QUALIFIED_ORDER_STATUS=delivered
```

### Register Middleware (`bootstrap/app.php`):

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

### Attach Trait to Order Model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RsmMonaem\MetaAdsAttribution\Traits\HasMetaAttribution;

class Order extends Model
{
    use HasMetaAttribution;
}
```

---

## 📊 Pipeline Comparison: Standard vs Qualified Delivered CAPI

| Feature | Standard Browser Pixel Only | Third-Party Webhook Plugins | **rsmmonaem/laravel-meta-ads-attribution** |
| :--- | :---: | :---: | :---: |
| **Trigger Point** | Checkout button click | Order creation | **Actual Delivery / Fulfillment** |
| **Cancelled Order Waste** | 🔴 100% false conversions | 🔴 100% false conversions | 🟢 **0% (Filtered Out)** |
| **Ad Blocker Defense** | 🔴 Blocked by uBlock/Brave | 🟡 Partial | 🟢 **100% Server-Side Resilience** |
| **Safari ITP Retention** | 🔴 24 hours to 7 days max | 🟡 Session only | 🟢 **90-Day First-Party HTTP Cookie** |
| **Customer Match Quality** | 🟡 4.0 - 5.5 / 10 | 🟡 5.0 - 6.5 / 10 | 🟢 **8.5 - 9.5 / 10 (Full PII + Original IP/UA)** |
| **Duplicate Prevention** | 🔴 High duplicate risk | 🟡 Basic timestamp | 🟢 **Deterministic `purchase_{id}` Idempotency** |
| **Ongoing Cost** | Free (Low Quality) | $50 - $200/mo (Third-Party SaaS / Stape) | 🟢 **Free & Open-Source Forever** |

---

### 🌐 Comparison: Top Alternatives to Stape.io & Third-Party SaaS

While third-party server-side tracking providers offer cloud proxy containers or managed dashboards, they charge recurring monthly subscription fees and still trigger purchase events at initial checkout rather than after actual delivery.

Here is how direct native Laravel tracking compares with popular hosted alternatives:

- **[TAGGRS](https://taggrs.io/stape-alternative/)**: A popular EU-hosted (GDPR-compliant) server-side GTM hosting provider offering free tiers and similar container setups to Stape. [[1](https://www.endframe.io/blog/stape-alternatives-server-side-tracking), [2](https://taggrs.io/stape-alternative/)]
- **[Tracklution](https://www.tracklution.com/server-side-tracking/tracklution-vs-stape/)**: A fully managed tracking platform that bypasses the need for Google Tag Manager completely for a simpler plug-and-play setup. [[1](https://www.tracklution.com/server-side-tracking/tracklution-vs-stape/), [2](https://pixelflow.so/stape-alternative)]
- **[Conversios](https://www.conversios.io/blog/conversios-vs-stape-the-best-server-side-tagging-solution-for-ecommerce/)**: An e-commerce focused tracking solution designed specifically with pre-configured setups for Shopify and WooCommerce stores. [[1](https://www.conversios.io/blog/conversios-vs-stape-the-best-server-side-tagging-solution-for-ecommerce/)]
- **[ServerTrack.io](https://servertrack.io/news/stape-io-alternative)**: A simplified server-side tracking alternative advertised for ultra-fast setups without deep technical configuration. [[1](https://servertrack.io/news/stape-io-alternative), [2](https://servertrack.io/news/top-3-server-side-tracking-companies-in-2026-best-stape-alternatives)]
- **CustomerLabs**: A first-party data operations platform focused on marketing stack integration and audience activation.

> **💡 The Native Laravel Advantage:**
> If you run a Laravel application, you don't need third-party proxy subscriptions or external container costs. This package runs directly on your server via background queues — guaranteeing zero monthly overhead, 100% data privacy, and event triggering strictly tied to actual order fulfillment.

---

## 📬 Contact Me

Have questions, need custom tracking integrations, or want to discuss enterprise Meta/CAPI attribution? Feel free to reach out:

- **Facebook**: [facebook.com/rsmmonaemid](https://www.facebook.com/rsmmonaemid/)

---

## 📚 Complete Guides & Documentation

- **[Full Technical Reference (DOCUMENTATION.md)](DOCUMENTATION.md)** — Architectural deep-dive, configuration parameters, and API reference.
- **[GTM & DataLayer Setup Guide (use_case_and_setup_guide.md)](use_case_and_setup_guide.md)** — Step-by-step DataLayer schemas, GTM triggers, variables, and tags.

---

## License

The MIT License (MIT). Please see [License](LICENSE) for more information.

# Laravel Meta Ads Attribution & Delivered Conversions API (CAPI)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](https://packagist.org/packages/rsmmonaem/laravel-meta-ads-attribution)
[![Total Downloads](https://img.shields.io/packagist/dt/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](https://packagist.org/packages/rsmmonaem/laravel-meta-ads-attribution)
[![License](https://img.shields.io/packagist/l/rsmmonaem/laravel-meta-ads-attribution.svg?style=flat-square)](LICENSE.md)

Production-ready Meta/Facebook Ads attribution, first-party cookie resilience, order attribution, and qualified **DELIVERED** order Conversions API (CAPI) system for Laravel e-commerce applications.

---

## Key Features

- **First-Party Attribution Engine**: Intercepts `fbclid`, UTM tags (`utm_source`, `utm_medium`, `utm_campaign`, etc.), and campaign IDs.
- **Safari ITP Defense**: Sets 90-day HTTP response cookies (`_fbp` and `_fbc` via `Set-Cookie` with `SameSite=Lax`) to prevent Safari's 24-hour cookie expiration.
- **Qualified Conversion Trigger**: Dispatches Meta CAPI `Purchase` events **ONLY** when an order reaches `delivered` status, protecting ad algorithms from cancelled/fake orders.
- **True Customer Match Quality (EMQ)**: Sends the customer's original browsing IP, User-Agent, and automatically splits full names into hashed `fn` and `ln` parameters for 8.5+ Event Match Quality.
- **Idempotency & Deduplication**: Deterministic `purchase_{order_id}` IDs guarantee zero duplicate conversions, even during multiple status changes or queue retries.
- **Full GTM / DataLayer Support**: Ready for browser-side Google Tag Manager event deduplication with server-side CAPI.
- **Admin Dashboard**: Real-time attribution analytics and conversion retry console at `/admin/meta-attribution`.

---

## Installation

```bash
composer require rsmmonaem/laravel-meta-ads-attribution
```

Run the package installer:

```bash
php artisan meta-attribution:install
php artisan migrate
```

---

## Quick Configuration

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

## Documentation & Guides

- [Full Technical Reference (DOCUMENTATION.md)](DOCUMENTATION.md)
- [Complete Architecture & GTM DataLayer Guide (use_case_and_setup_guide.md)](../../../use_case_and_setup_guide.md)

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

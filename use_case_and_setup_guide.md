# Complete Setup & Architecture Guide
## Laravel Meta Ads Attribution & Delivered Conversions API (CAPI)

A complete reference on **business use cases**, **technical architecture**, **step-by-step installation**, and **Google Tag Manager (GTM) DataLayer integration** for `rsmmonaem/laravel-meta-ads-attribution`.

Works with any Laravel setup (Custom E-Commerce, Bagisto, Lunar, Shopper, Filament, Nova, Aimeos).

---

## 📋 Table of Contents

1. [Core Business Use Cases](#1-core-business-use-cases)
2. [End-to-End Tracking Architecture](#2-end-to-end-tracking-architecture)
3. [Step-by-Step Installation](#3-step-by-step-installation)
4. [Google Tag Manager (GTM) & DataLayer Setup Guide](#4-google-tag-manager-gtm--datalayer-setup-guide)
5. [Meta Events Manager Verification & Testing](#5-meta-events-manager-verification--testing)
6. [Platform Integrations (Filament, Nova, Custom)](#6-platform-integrations-filament-nova-custom)
7. [Troubleshooting & FAQ](#7-troubleshooting--faq)

---

## 1. Core Business Use Cases

### 🎯 1. Paid Ad Attribution Tracking (Facebook & Instagram)
- **Scenario**: A customer taps an Instagram story ad or Facebook feed ad (`?fbclid=IwAR...&utm_source=facebook&utm_campaign=Summer_Drop_2026`).
- **Action**: Middleware intercepts the request, assigns a 90-day first-party cookie (`meta_visitor_id`), formats `_fbc` & `_fbp` cookies, and logs the touchpoint.
- **Outcome**: The customer's identity and origin are locked to the specific Meta ad campaign across multi-page browsing, add-to-carts, user registration, and checkout.

### 🛡️ 2. Qualified "Delivered Order" Conversion Safeguard
- **Problem**: Standard setups fire `Purchase` events right at checkout submission. This sends false conversion data for unpaid, cancelled, or returned orders, misguiding Meta's bidding algorithms.
- **Solution**: This package waits until an order status reaches **DELIVERED** (or your chosen business fulfillment status) before sending the final CAPI purchase event.
- **Outcome**: Meta's machine learning optimizes strictly for real, kept revenue.

### ⚡ 3. Multi-Touch Retention & First Paid Touch Model
- **Scenario**: A buyer finds your store through an ad, leaves, and later returns directly or via email to complete the purchase.
- **Action**: The `first_paid_touch` model attributes the conversion to the initial acquiring Meta campaign while logging intermediate visits.
- **Outcome**: No more conversions falsely credited as "Direct".

### 🔒 4. Bulletproof Deduplication & Safari ITP Defense
- Browser pixel cookies set by client JavaScript often expire in 24 hours under Safari ITP.
- This package emits HTTP response headers (`Set-Cookie`) for `_fbp` and `_fbc`, preserving first-party status for up to 90 days.
- When both browser and server conversions are used, identical `event_id` keys prevent double counting.

---

## 2. End-to-End Tracking Architecture

```
[ Customer Clicks Meta Ad ]
            │ (fbclid, UTM parameters)
            ▼
[ Laravel Ingress Middleware ]
            │
            ├──► Generates/refreshes 90-day first-party cookie: meta_visitor_id
            ├──► Sets HTTP response cookies: _fbp & _fbc (SameSite=Lax, Safari ITP safe)
            └──► Logs touchpoint in meta_ad_attributions & meta_tracking_sessions
            │
            ├────────────────────────────────────────┬────────────────────────────────────────┐
            ▼                                        ▼                                        ▼
    [ Product Page ]                        [ Cart Additions ]                      [ Order Creation ]
    • DataLayer: view_item                   • DataLayer: add_to_cart              • Order saved in DB
    • GTM fires ViewContent                  • GTM fires AddToCart                 • HasMetaAttribution binds order
      (with event_id)                          (with event_id)                     • Initial status: Pending
            │                                        │                                        │
            └────────────────────────────────────────┴────────────────────────────────────────┘
                                                                                              │
                                                                                              ▼
                                                                           [ Order Delivered / Fulfilled ]
                                                                                              │
                                                                                              ▼
                                                                           [ Eloquent Hook: status == delivered ]
                                                                                              │
                                                                                              ▼
                                                                           [ SendMetaDeliveredConversionJob ]
                                                                                              │
                                                                                              ▼
                                                                           [ Meta Conversions API (Graph API) ]
                                                                           • Normalizes & SHA-256 hashes PII
                                                                           • Uses Customer's true original IP/UA
                                                                           • Deterministic purchase_{id} deduplication
                                                                                              │
                                                                                              ▼
                                                                           [ Meta Events Manager (Verified) ]
```

---

## 3. Step-by-Step Installation

### Step 1: Install via Composer

```bash
composer require rsmmonaem/laravel-meta-ads-attribution
```

### Step 2: Run the Installer

```bash
php artisan meta-attribution:install
```

### Step 3: Run Database Migrations

```bash
php artisan migrate
```

Creates four dedicated tables:
- `meta_ad_attributions`
- `meta_tracking_sessions`
- `meta_order_attributions`
- `meta_conversion_events`

### Step 4: Configure `.env`

```env
META_ATTRIBUTION_ENABLED=true
META_ENABLE_BROWSER_PIXEL=true
META_ENABLE_CAPI=true

META_PIXEL_ID=123456789012345
META_ACCESS_TOKEN=EAAB...your_system_user_token...
META_TEST_EVENT_CODE=   # Optional: set when testing in Events Manager (e.g. TEST12345)

META_QUALIFIED_ORDER_STATUS=delivered
META_ATTRIBUTION_MODEL=first_paid_touch
META_CAPI_QUEUE=default
META_API_VERSION=v19.0
```

### Step 5: Register Middleware

#### Laravel 11+ (`bootstrap/app.php`):

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

### Step 6: Attach Model Trait

In `app/Models/Order.php`:

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

## 4. Google Tag Manager (GTM) & DataLayer Setup Guide

If your storefront uses Google Tag Manager to orchestrate tags, follow this standard pattern for seamless client-side tracking and server-side CAPI deduplication.

### 4.1. GTM Script Placement in Root Layout

Add the standard GTM container code to your main layout (`resources/views/layouts/app.blade.php`):

```html
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
    <!-- End Google Tag Manager -->
</head>
<body>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
```

---

### 4.2. Blade Storefront DataLayer Implementations

#### A. Product Detail Page (`ViewContent`)

```html
<!-- Inside resources/views/store/product.blade.php -->
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

#### B. Add To Cart Button / AJAX Response

```javascript
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    event: 'add_to_cart',
    event_id: 'cart_' + product.id + '_' + Date.now(),
    ecommerce: {
        currency: 'USD',
        value: Number(product.price) * quantity,
        items: [{
            item_id: String(product.id),
            item_name: product.name,
            price: Number(product.price),
            quantity: quantity
        }]
    }
});
```

#### C. Checkout Page (`InitiateCheckout`)

```html
<!-- Inside resources/views/store/checkout.blade.php -->
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

#### D. Order Confirmation (`Purchase` Optional Browser Trigger)

```html
<!-- Inside resources/views/store/confirmation.blade.php -->
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
    event: 'purchase',
    // Must match the server-side event_id format: purchase_{order_id}
    event_id: 'purchase_{{ $order->id }}',
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

### 4.3. Configuring Google Tag Manager Dashboard

1. **Variables**:
   Create four **Data Layer Variables**:
   - `DLV - event_id` → Data Layer Variable Name: `event_id`
   - `DLV - ecommerce.value` → Data Layer Variable Name: `ecommerce.value`
   - `DLV - ecommerce.currency` → Data Layer Variable Name: `ecommerce.currency`
   - `DLV - ecommerce.items` → Data Layer Variable Name: `ecommerce.items`

2. **Triggers**:
   Create **Custom Event Triggers**:
   - Trigger Name: `Custom Event - view_item` (Event name: `view_item`)
   - Trigger Name: `Custom Event - add_to_cart` (Event name: `add_to_cart`)
   - Trigger Name: `Custom Event - begin_checkout` (Event name: `begin_checkout`)
   - Trigger Name: `Custom Event - purchase` (Event name: `purchase`)

3. **Tags**:
   Create Meta Pixel tags:
   - For Base Pixel: Trigger on `All Pages`.
   - For Events: Trigger on matching custom event, and configure `{ eventID: {{DLV - event_id}} }`.

When Meta receives the browser event and the server-side conversion job dispatches upon order delivery, Meta matches both events on `event_name: 'Purchase'` and `event_id: 'purchase_{id}'`, deduplicating seamlessly.

---

## 5. Meta Events Manager Verification & Testing

1. In Meta Business Manager, open **Events Manager** → **Test Events**.
2. Copy your **Test Event Code** (e.g. `TEST54321`).
3. Set in `.env`:
   ```env
   META_TEST_EVENT_CODE=TEST54321
   ```
4. Visit your storefront via test ad link:
   ```
   http://yourstore.test/?fbclid=IwAR_TEST12345&utm_source=facebook&utm_campaign=Audit_Test
   ```
5. Place an order, then transition the order to `delivered` in your admin panel.
6. Open Meta Events Manager: you will see the `Purchase` event marked as **Server**, with green verification checkmarks and all hashed customer parameters matched.

---

## 6. Background Worker in Production

Always run Laravel queue workers with adequate retries:

```bash
php artisan queue:work --queue=default --tries=5
```

---

## 7. Troubleshooting & FAQ

- **Q: What if an order is cancelled or refunded?**  
  Since the trigger is set to `delivered`, cancelled or refunded orders never fire the CAPI purchase conversion.
- **Q: Are customer IP addresses and User-Agents accurate when queues process later?**  
  Yes. The package pulls the customer's original browsing IP and User-Agent captured during their storefront session, avoiding worker localhost IPs.

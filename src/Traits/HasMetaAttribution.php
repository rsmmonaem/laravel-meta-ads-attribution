<?php

namespace RsmMonaem\MetaAdsAttribution\Traits;

use RsmMonaem\MetaAdsAttribution\Models\MetaOrderAttribution;
use RsmMonaem\MetaAdsAttribution\Services\MetaAttributionManager;
use RsmMonaem\MetaAdsAttribution\Jobs\SendMetaDeliveredConversionJob;
use Illuminate\Support\Facades\Log;

trait HasMetaAttribution
{
    public static function bootHasMetaAttribution(): void
    {
        static::created(function ($order) {
            try {
                $manager = app(MetaAttributionManager::class);
                $amount = (float) ($order->total ?? $order->grand_total ?? $order->amount ?? 0.0);
                $currency = $order->currency ?? 'USD';
                $orderNumber = $order->order_number ?? $order->number ?? (string)$order->id;
                $userId = $order->user_id ?? $order->customer_id ?? null;

                $orderAttribution = $manager->attachAttributionToOrder($order->id, $orderNumber, $amount, $currency, $userId);

                // If order was created directly in qualified delivered status (e.g. digital goods / POS)
                $currentStatus = static::resolveOrderStatusString($order);
                $qualifiedStatus = strtolower((string) config('meta-attribution.qualified_order_status', 'delivered'));

                if ($currentStatus && $currentStatus === $qualifiedStatus) {
                    static::dispatchMetaDeliveredConversion($order, $orderAttribution);
                }
            } catch (\Throwable $e) {
                Log::error("Failed to attach Meta attribution on order creation: " . $e->getMessage());
            }
        });

        static::updated(function ($order) {
            try {
                $statusKey = isset($order->status) ? 'status' : (isset($order->order_status) ? 'order_status' : null);
                if (!$statusKey || !$order->isDirty($statusKey)) {
                    return;
                }

                $newStatus = static::resolveOrderStatusString($order);
                $qualifiedStatus = strtolower((string) config('meta-attribution.qualified_order_status', 'delivered'));

                if ($newStatus && $newStatus === $qualifiedStatus) {
                    $orderAttribution = MetaOrderAttribution::where('order_id', $order->id)->first();
                    static::dispatchMetaDeliveredConversion($order, $orderAttribution);
                }
            } catch (\Throwable $e) {
                Log::error("Failed to process Meta attribution status update: " . $e->getMessage());
            }
        });
    }

    public static function resolveOrderStatusString($order): ?string
    {
        $statusKey = isset($order->status) ? 'status' : (isset($order->order_status) ? 'order_status' : null);
        if (!$statusKey || !isset($order->{$statusKey})) {
            return null;
        }

        $val = $order->{$statusKey};
        if ($val instanceof \BackedEnum) {
            return strtolower((string) $val->value);
        }
        if (is_object($val) && enum_exists(get_class($val))) {
            return strtolower((string) ($val->value ?? $val->name));
        }

        return strtolower((string) $val);
    }

    protected static function dispatchMetaDeliveredConversion($order, ?MetaOrderAttribution $orderAttribution): void
    {
        if (!$orderAttribution) {
            $orderAttribution = MetaOrderAttribution::where('order_id', $order->id)->first();
        }

        $isMetaAttributed = $orderAttribution && (
            $orderAttribution->attribution_source === 'facebook' ||
            !empty($orderAttribution->fbclid) ||
            in_array(strtolower((string)$orderAttribution->utm_source), ['facebook', 'meta', 'instagram', 'ig', 'fb'])
        );

        if ($isMetaAttributed) {
            Log::info("Order #{$order->id} qualified for Meta conversion. Dispatching SendMetaDeliveredConversionJob.");
            SendMetaDeliveredConversionJob::dispatch($order);
        } else {
            Log::info("Order #{$order->id} status is qualified, but was not attributed to Meta. Skipping CAPI event.");
        }
    }

    public function metaAttribution()
    {
        return $this->hasOne(MetaOrderAttribution::class, 'order_id', 'id');
    }
}

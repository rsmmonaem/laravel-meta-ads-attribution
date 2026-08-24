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

                $manager->attachAttributionToOrder($order->id, $orderNumber, $amount, $currency, $userId);
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

                $newStatus = strtolower((string) $order->{$statusKey});
                $qualifiedStatus = strtolower((string) config('meta-attribution.qualified_order_status', 'delivered'));

                if ($newStatus === $qualifiedStatus) {
                    $orderAttribution = MetaOrderAttribution::where('order_id', $order->id)->first();

                    // Verify Meta attribution (or if attributed)
                    $isMetaAttributed = $orderAttribution && (
                        $orderAttribution->attribution_source === 'facebook' ||
                        !empty($orderAttribution->fbclid) ||
                        in_array(strtolower((string)$orderAttribution->utm_source), ['facebook', 'meta', 'instagram', 'ig', 'fb'])
                    );

                    if ($isMetaAttributed) {
                        Log::info("Order #{$order->id} status changed to {$newStatus}. Dispatching Meta Delivered Conversion Job.");
                        SendMetaDeliveredConversionJob::dispatch($order);
                    } else {
                        Log::info("Order #{$order->id} status changed to {$newStatus}, but was not attributed to Meta. Skipping CAPI event.");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Failed to process Meta attribution status update: " . $e->getMessage());
            }
        });
    }

    public function metaAttribution()
    {
        return $this->hasOne(MetaOrderAttribution::class, 'order_id', 'id');
    }
}

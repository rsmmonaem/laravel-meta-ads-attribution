<?php

namespace Antigravity\MetaAdsAttribution\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Antigravity\MetaAdsAttribution\Services\MetaConversionService;
use Antigravity\MetaAdsAttribution\Models\MetaOrderAttribution;

class SendMetaDeliveredConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900, 3600];

    protected mixed $order;
    protected ?array $customerInfo;

    public function __construct(mixed $order, ?array $customerInfo = null)
    {
        $this->order = $order;
        $this->customerInfo = $customerInfo;
        $this->queue = config('meta-attribution.queue', 'default');
    }

    public function handle(MetaConversionService $conversionService): void
    {
        $orderId = is_object($this->order) ? $this->order->id : (int) $this->order;

        Log::info("Processing SendMetaDeliveredConversionJob for Order #{$orderId}");

        $result = $conversionService->sendDeliveredPurchase($this->order, $this->customerInfo);

        if (!$result['success'] && ($result['status'] ?? '') === 'failed') {
            Log::warning("Meta CAPI Delivered Purchase job failed for Order #{$orderId}. Error: " . ($result['error'] ?? 'Unknown error'));
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 600);
            }
        }
    }
}

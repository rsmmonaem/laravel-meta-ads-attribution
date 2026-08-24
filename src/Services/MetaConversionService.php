<?php

namespace RsmMonaem\MetaAdsAttribution\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RsmMonaem\MetaAdsAttribution\Models\MetaConversionEvent;
use RsmMonaem\MetaAdsAttribution\Models\MetaOrderAttribution;

class MetaConversionService
{
    protected function getPixelId(): string
    {
        return (string) config('meta-attribution.pixel_id', '');
    }

    protected function getAccessToken(): string
    {
        return (string) config('meta-attribution.access_token', '');
    }

    protected function getTestEventCode(): ?string
    {
        return config('meta-attribution.test_event_code', null);
    }

    protected function getApiVersion(): string
    {
        return (string) config('meta-attribution.api_version', 'v19.0');
    }

    protected function isEnabled(): bool
    {
        return (bool) config('meta-attribution.enabled', true) && (bool) config('meta-attribution.enable_capi', true);
    }

    /**
     * Send general CAPI Event
     */
    public function sendEvent(
        string $eventName,
        array $userData,
        array $customData = [],
        ?string $eventId = null,
        ?string $eventSourceUrl = null,
        ?int $orderId = null
    ): array {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'status' => 'disabled',
                'message' => 'Meta attribution or CAPI is disabled in configuration.',
            ];
        }

        $eventId = $eventId ?? (string) \Illuminate\Support\Str::uuid();
        $eventSourceUrl = $eventSourceUrl ?? request()->fullUrl();

        // 1. Prepare normalized & hashed user_data
        $hashedUserData = $this->normalizeAndHashUserData($userData);

        // 2. Prepare payload
        $eventData = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'event_source_url' => $eventSourceUrl,
            'action_source' => 'website',
            'user_data' => $hashedUserData,
            'custom_data' => $customData,
        ];

        // 3. Create or find conversion event log record
        $eventLog = MetaConversionEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'order_id' => $orderId,
                'event_name' => $eventName,
                'action_source' => 'website',
                'status' => 'pending',
                'user_data_hashed' => $hashedUserData,
                'custom_data' => $customData,
                'first_attempt_at' => now(),
                'retry_count' => 0,
            ]
        );

        // If already successfully sent, block duplicate delivery (IDEMPOTENCY)
        if ($eventLog->status === 'sent') {
            Log::info("Meta CAPI event {$eventId} already sent. Skipping duplicate dispatch.");
            return [
                'success' => true,
                'status' => 'already_sent',
                'event_id' => $eventId,
                'message' => 'Event already sent successfully.',
            ];
        }

        // 4. Construct request to Meta Graph API
        $payload = [
            'data' => [$eventData],
        ];

        $testCode = $this->getTestEventCode();
        if (!empty($testCode)) {
            $payload['test_event_code'] = $testCode;
        }

        $apiVersion = $this->getApiVersion();
        $pixelId = $this->getPixelId();
        $accessToken = $this->getAccessToken();
        $endpoint = "https://graph.facebook.com/{$apiVersion}/{$pixelId}/events";

        $eventLog->update([
            'last_attempt_at' => now(),
            'retry_count' => $eventLog->retry_count + 1,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$endpoint}?access_token={$accessToken}", $payload);

            $statusCode = $response->status();
            $responseData = $response->json() ?? [];

            if ($response->successful()) {
                $eventLog->update([
                    'status' => 'sent',
                    'http_status' => $statusCode,
                    'meta_response' => $responseData,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);

                return [
                    'success' => true,
                    'status' => 'sent',
                    'http_status' => $statusCode,
                    'event_id' => $eventId,
                    'meta_response' => $responseData,
                ];
            } else {
                $errorMessage = $responseData['error']['message'] ?? $response->body();
                $eventLog->update([
                    'status' => 'failed',
                    'http_status' => $statusCode,
                    'meta_response' => $responseData,
                    'error_message' => $errorMessage,
                ]);

                return [
                    'success' => false,
                    'status' => 'failed',
                    'http_status' => $statusCode,
                    'event_id' => $eventId,
                    'error' => $errorMessage,
                ];
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $eventLog->update([
                'status' => 'failed',
                'http_status' => 500,
                'error_message' => $errorMessage,
            ]);

            Log::error("Meta CAPI Exception for event {$eventId}: " . $errorMessage);

            return [
                'success' => false,
                'status' => 'failed',
                'http_status' => 500,
                'event_id' => $eventId,
                'error' => $errorMessage,
            ];
        }
    }

    /**
     * Send Qualified Delivered Purchase Event
     */
    public function sendDeliveredPurchase(
        $order,
        ?array $customerInfo = null,
        ?MetaOrderAttribution $orderAttribution = null
    ): array {
        $orderId = is_object($order) ? $order->id : (int) $order;
        $orderNumber = is_object($order) ? ($order->order_number ?? $order->number ?? (string)$orderId) : (string)$orderId;
        $orderAmount = is_object($order) ? (float)($order->total ?? $order->grand_total ?? $order->amount ?? 0.0) : 0.0;
        $currency = is_object($order) ? ($order->currency ?? 'USD') : 'USD';

        if (!$orderAttribution) {
            $orderAttribution = MetaOrderAttribution::where('order_id', $orderId)->first();
        }

        if ($orderAttribution && $orderAttribution->order_amount > 0) {
            $orderAmount = (float) $orderAttribution->order_amount;
            $currency = $orderAttribution->currency;
        }

        // Deterministic unique event_id for deduplication
        $eventId = "purchase_{$orderId}";

        // Check if event already sent
        $existing = MetaConversionEvent::where('event_id', $eventId)->where('status', 'sent')->first();
        if ($existing) {
            return [
                'success' => true,
                'status' => 'already_sent',
                'event_id' => $eventId,
                'message' => 'Delivered purchase conversion event already sent.',
            ];
        }

        // Gather Customer Matching data
        $userData = [
            'email' => $customerInfo['email'] ?? (is_object($order) ? ($order->email ?? $order->customer_email ?? null) : null),
            'phone' => $customerInfo['phone'] ?? (is_object($order) ? ($order->phone ?? $order->customer_phone ?? null) : null),
            'first_name' => $customerInfo['first_name'] ?? (is_object($order) ? ($order->first_name ?? null) : null),
            'last_name' => $customerInfo['last_name'] ?? (is_object($order) ? ($order->last_name ?? null) : null),
            'city' => $customerInfo['city'] ?? (is_object($order) ? ($order->city ?? null) : null),
            'state' => $customerInfo['state'] ?? (is_object($order) ? ($order->state ?? null) : null),
            'postal_code' => $customerInfo['postal_code'] ?? (is_object($order) ? ($order->postal_code ?? $order->zip ?? null) : null),
            'country' => $customerInfo['country'] ?? (is_object($order) ? ($order->country ?? null) : null),
            'external_id' => (string) (is_object($order) ? ($order->user_id ?? $order->customer_id ?? $orderId) : $orderId),
            'client_ip_address' => request()->ip(),
            'client_user_agent' => (string) request()->userAgent(),
            'fbp' => $orderAttribution ? $orderAttribution->fbp : request()->cookie('_fbp'),
            'fbc' => $orderAttribution ? $orderAttribution->fbc : request()->cookie('_fbc'),
        ];

        // Gather Custom Data
        $customData = [
            'value' => $orderAmount,
            'currency' => strtoupper($currency),
            'content_type' => 'product',
            'order_id' => (string) $orderNumber,
        ];

        // Include item breakdown if order has items
        if (is_object($order) && isset($order->items)) {
            $contents = [];
            foreach ($order->items as $item) {
                $contents[] = [
                    'id' => (string) ($item->product_id ?? $item->sku ?? $item->id),
                    'quantity' => (int) ($item->quantity ?? $item->qty ?? 1),
                    'item_price' => (float) ($item->price ?? $item->unit_price ?? 0),
                ];
            }
            if (!empty($contents)) {
                $customData['contents'] = $contents;
            }
        }

        return $this->sendEvent(
            eventName: 'Purchase',
            userData: $userData,
            customData: $customData,
            eventId: $eventId,
            eventSourceUrl: request()->fullUrl() ?? config('app.url'),
            orderId: $orderId
        );
    }

    /**
     * Retry a failed conversion event log
     */
    public function retryFailedEvent(MetaConversionEvent $eventLog): array
    {
        if ($eventLog->status === 'sent') {
            return ['success' => true, 'message' => 'Event already sent successfully.'];
        }

        $orderAttribution = $eventLog->order_id ? MetaOrderAttribution::where('order_id', $eventLog->order_id)->first() : null;

        $userData = $eventLog->user_data_hashed ?? [];
        $customData = $eventLog->custom_data ?? [];

        $apiVersion = $this->getApiVersion();
        $pixelId = $this->getPixelId();
        $accessToken = $this->getAccessToken();
        $testCode = $this->getTestEventCode();

        // Build CAPI payload directly using stored hashed user_data
        $endpoint = "https://graph.facebook.com/{$apiVersion}/{$pixelId}/events";
        $eventData = [
            'event_name' => $eventLog->event_name,
            'event_time' => time(),
            'event_id' => $eventLog->event_id,
            'event_source_url' => config('app.url'),
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => $customData,
        ];

        $payload = ['data' => [$eventData]];
        if (!empty($testCode)) {
            $payload['test_event_code'] = $testCode;
        }

        $eventLog->update([
            'last_attempt_at' => now(),
            'retry_count' => $eventLog->retry_count + 1,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$endpoint}?access_token={$accessToken}", $payload);

            if ($response->successful()) {
                $eventLog->update([
                    'status' => 'sent',
                    'http_status' => $response->status(),
                    'meta_response' => $response->json(),
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
                return ['success' => true, 'status' => 'sent', 'meta_response' => $response->json()];
            } else {
                $eventLog->update([
                    'status' => 'failed',
                    'http_status' => $response->status(),
                    'meta_response' => $response->json(),
                    'error_message' => $response->json()['error']['message'] ?? $response->body(),
                ]);
                return ['success' => false, 'status' => 'failed', 'error' => $response->body()];
            }
        } catch (\Throwable $e) {
            $eventLog->update([
                'status' => 'failed',
                'http_status' => 500,
                'error_message' => $e->getMessage(),
            ]);
            return ['success' => false, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalize & SHA-256 Hash Customer Data according to Meta Guidelines
     */
    public function normalizeAndHashUserData(array $userData): array
    {
        $hashed = [];

        // Email (em)
        if (!empty($userData['email'])) {
            $email = strtolower(trim((string)$userData['email']));
            $hashed['em'] = hash('sha256', $email);
        } elseif (!empty($userData['em'])) {
            $hashed['em'] = strlen((string)$userData['em']) === 64 ? $userData['em'] : hash('sha256', strtolower(trim((string)$userData['em'])));
        }

        // Phone (ph)
        if (!empty($userData['phone'])) {
            $phone = preg_replace('/[^\d]/', '', (string)$userData['phone']);
            $hashed['ph'] = hash('sha256', $phone);
        } elseif (!empty($userData['ph'])) {
            $hashed['ph'] = strlen((string)$userData['ph']) === 64 ? $userData['ph'] : hash('sha256', preg_replace('/[^\d]/', '', (string)$userData['ph']));
        }

        // First Name (fn)
        if (!empty($userData['first_name'])) {
            $hashed['fn'] = hash('sha256', strtolower(trim((string)$userData['first_name'])));
        } elseif (!empty($userData['fn'])) {
            $hashed['fn'] = strlen((string)$userData['fn']) === 64 ? $userData['fn'] : hash('sha256', strtolower(trim((string)$userData['fn'])));
        }

        // Last Name (ln)
        if (!empty($userData['last_name'])) {
            $hashed['ln'] = hash('sha256', strtolower(trim((string)$userData['last_name'])));
        } elseif (!empty($userData['ln'])) {
            $hashed['ln'] = strlen((string)$userData['ln']) === 64 ? $userData['ln'] : hash('sha256', strtolower(trim((string)$userData['ln'])));
        }

        // City (ct)
        if (!empty($userData['city'])) {
            $city = preg_replace('/[^a-z]/', '', strtolower(trim((string)$userData['city'])));
            $hashed['ct'] = hash('sha256', $city);
        } elseif (!empty($userData['ct'])) {
            $hashed['ct'] = strlen((string)$userData['ct']) === 64 ? $userData['ct'] : hash('sha256', preg_replace('/[^a-z]/', '', strtolower(trim((string)$userData['ct']))));
        }

        // State (st)
        if (!empty($userData['state'])) {
            $state = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$userData['state'])));
            $hashed['st'] = hash('sha256', $state);
        } elseif (!empty($userData['st'])) {
            $hashed['st'] = strlen((string)$userData['st']) === 64 ? $userData['st'] : hash('sha256', preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$userData['st']))));
        }

        // Postal Code (zp)
        if (!empty($userData['postal_code'])) {
            $zp = preg_replace('/[\s-]/', '', strtolower(trim((string)$userData['postal_code'])));
            $hashed['zp'] = hash('sha256', $zp);
        } elseif (!empty($userData['zp'])) {
            $hashed['zp'] = strlen((string)$userData['zp']) === 64 ? $userData['zp'] : hash('sha256', preg_replace('/[\s-]/', '', strtolower(trim((string)$userData['zp']))));
        }

        // Country (country)
        if (!empty($userData['country'])) {
            $country = strtolower(trim((string)$userData['country']));
            $hashed['country'] = hash('sha256', $country);
        }

        // External ID
        if (!empty($userData['external_id'])) {
            $hashed['external_id'] = hash('sha256', (string)$userData['external_id']);
        }

        // Unhashed parameters (IP, User Agent, fbp, fbc)
        if (!empty($userData['client_ip_address'])) {
            $hashed['client_ip_address'] = $userData['client_ip_address'];
        }
        if (!empty($userData['client_user_agent'])) {
            $hashed['client_user_agent'] = $userData['client_user_agent'];
        }
        if (!empty($userData['fbp'])) {
            $hashed['fbp'] = $userData['fbp'];
        }
        if (!empty($userData['fbc'])) {
            $hashed['fbc'] = $userData['fbc'];
        }

        return $hashed;
    }
}

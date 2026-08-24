<?php

namespace RsmMonaem\MetaAdsAttribution\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RsmMonaem\MetaAdsAttribution\Models\MetaAdAttribution;
use RsmMonaem\MetaAdsAttribution\Models\MetaTrackingSession;
use RsmMonaem\MetaAdsAttribution\Models\MetaOrderAttribution;

class MetaAttributionManager
{
    public function processRequestAttribution(Request $request, string $visitorId): MetaAdAttribution
    {
        $now = now();
        $userId = Auth::id();

        // 1. Extract query parameters
        $fbclid = $request->query('fbclid');
        $utmSource = $request->query('utm_source');
        $utmMedium = $request->query('utm_medium');
        $utmCampaign = $request->query('utm_campaign');
        $utmTerm = $request->query('utm_term');
        $utmContent = $request->query('utm_content');

        // Extract campaign/ad IDs if passed via UTM or custom query
        $campaignId = $request->query('campaign_id') ?? $request->query('ad_campaign_id');
        $adsetId = $request->query('adset_id') ?? $request->query('ad_set_id');
        $adId = $request->query('ad_id');

        // 2. Resolve Meta Cookies (_fbp, _fbc)
        $fbp = $request->cookie('_fbp') ?? Session::get('meta_fbp');
        if (!$fbp) {
            $fbp = 'fb.1.' . time() . '.' . rand(100000000, 999999999);
            Session::put('meta_fbp', $fbp);
        }

        $fbc = $request->cookie('_fbc') ?? Session::get('meta_fbc');
        if ($fbclid && !$fbc) {
            $fbc = 'fb.1.' . time() . '.' . $fbclid;
            Session::put('meta_fbc', $fbc);
        }

        $landingPage = $request->fullUrl();
        $referrer = $request->header('referer');
        $userAgent = substr((string) $request->userAgent(), 0, 500);
        $ipAddress = $request->ip();

        // 3. Find or Create Visitor Record
        $attribution = MetaAdAttribution::where('visitor_id', $visitorId)->first();

        $isMetaTraffic = !empty($fbclid) || in_array(strtolower((string) $utmSource), ['facebook', 'meta', 'instagram', 'ig', 'fb']);

        if (!$attribution) {
            $attribution = MetaAdAttribution::create([
                'visitor_id' => $visitorId,
                'user_id' => $userId,
                'fbclid' => $fbclid,
                'fbc' => $fbc,
                'fbp' => $fbp,
                'utm_source' => $utmSource ?? ($isMetaTraffic ? 'facebook' : 'direct'),
                'utm_medium' => $utmMedium ?? ($isMetaTraffic ? 'cpc' : null),
                'utm_campaign' => $utmCampaign,
                'utm_term' => $utmTerm,
                'utm_content' => $utmContent,
                'campaign_id' => $campaignId,
                'adset_id' => $adsetId,
                'ad_id' => $adId,
                'landing_page' => $landingPage,
                'referrer' => $referrer,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
                'first_touch_at' => $now,
                'last_touch_at' => $now,
            ]);
        } else {
            // Update logic according to First Paid Touch / Hybrid Model
            $updateData = [
                'last_touch_at' => $now,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ];

            if ($userId && !$attribution->user_id) {
                $updateData['user_id'] = $userId;
            }

            if ($fbp) {
                $updateData['fbp'] = $fbp;
            }

            // If incoming traffic is Meta traffic or contains new click ID, update paid attribution
            if ($isMetaTraffic || $fbclid) {
                if ($fbclid) {
                    $updateData['fbclid'] = $fbclid;
                    $updateData['fbc'] = $fbc;
                }
                if ($utmSource) $updateData['utm_source'] = $utmSource;
                if ($utmMedium) $updateData['utm_medium'] = $utmMedium;
                if ($utmCampaign) $updateData['utm_campaign'] = $utmCampaign;
                if ($utmTerm) $updateData['utm_term'] = $utmTerm;
                if ($utmContent) $updateData['utm_content'] = $utmContent;
                if ($campaignId) $updateData['campaign_id'] = $campaignId;
                if ($adsetId) $updateData['adset_id'] = $adsetId;
                if ($adId) $updateData['ad_id'] = $adId;
            }

            $attribution->update($updateData);
        }

        // 4. Log Session Touchpoint
        $sessionKey = config('meta-attribution.session_key', 'meta_attribution_session');
        $sessionId = Session::getId();

        MetaTrackingSession::create([
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'user_id' => $userId,
            'fbclid' => $fbclid ?? $attribution->fbclid,
            'fbc' => $fbc ?? $attribution->fbc,
            'fbp' => $fbp ?? $attribution->fbp,
            'utm_source' => $utmSource ?? $attribution->utm_source,
            'utm_medium' => $utmMedium ?? $attribution->utm_medium,
            'utm_campaign' => $utmCampaign ?? $attribution->utm_campaign,
            'current_url' => $landingPage,
            'referrer' => $referrer,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Store active visitor ID in session for easy lookup during order placement
        Session::put($sessionKey, [
            'visitor_id' => $visitorId,
            'fbclid' => $attribution->fbclid,
            'fbc' => $attribution->fbc,
            'fbp' => $attribution->fbp,
            'utm_source' => $attribution->utm_source,
            'utm_medium' => $attribution->utm_medium,
            'utm_campaign' => $attribution->utm_campaign,
        ]);

        return $attribution;
    }

    public function attachAttributionToOrder(int $orderId, ?string $orderNumber, float $amount, string $currency = 'USD', ?int $userId = null): MetaOrderAttribution
    {
        $cookieName = config('meta-attribution.cookie_name', 'meta_visitor_id');
        $visitorId = request()->cookie($cookieName) ?? Session::get('meta_attribution_session.visitor_id');

        $attribution = null;
        if ($visitorId) {
            $attribution = MetaAdAttribution::where('visitor_id', $visitorId)->first();
        }

        if (!$attribution && $userId) {
            $attribution = MetaAdAttribution::where('user_id', $userId)->latest('last_touch_at')->first();
        }

        $isMeta = false;
        $source = 'direct';

        if ($attribution) {
            $source = $attribution->utm_source ?? ($attribution->fbclid ? 'facebook' : 'direct');
            $isMeta = !empty($attribution->fbclid) || in_array(strtolower((string) $source), ['facebook', 'meta', 'instagram', 'ig', 'fb']);
        }

        return MetaOrderAttribution::updateOrCreate(
            ['order_id' => $orderId],
            [
                'order_number' => $orderNumber ?? (string) $orderId,
                'visitor_id' => $visitorId ?? ($attribution ? $attribution->visitor_id : null),
                'user_id' => $userId ?? Auth::id() ?? ($attribution ? $attribution->user_id : null),
                'attribution_source' => $isMeta ? 'facebook' : $source,
                'attribution_medium' => $attribution ? $attribution->utm_medium : null,
                'campaign' => $attribution ? $attribution->utm_campaign : null,
                'campaign_id' => $attribution ? $attribution->campaign_id : null,
                'adset_id' => $attribution ? $attribution->adset_id : null,
                'ad_id' => $attribution ? $attribution->ad_id : null,
                'fbclid' => $attribution ? $attribution->fbclid : null,
                'fbc' => $attribution ? $attribution->fbc : null,
                'fbp' => $attribution ? $attribution->fbp : null,
                'utm_source' => $attribution ? $attribution->utm_source : null,
                'utm_medium' => $attribution ? $attribution->utm_medium : null,
                'utm_campaign' => $attribution ? $attribution->utm_campaign : null,
                'utm_term' => $attribution ? $attribution->utm_term : null,
                'utm_content' => $attribution ? $attribution->utm_content : null,
                'order_amount' => $amount,
                'currency' => $currency,
                'attribution_model' => config('meta-attribution.attribution_model', 'first_paid_touch'),
                'first_touch_at' => $attribution ? $attribution->first_touch_at : now(),
                'last_touch_at' => $attribution ? $attribution->last_touch_at : now(),
                'attributed_at' => now(),
            ]
        );
    }
}

<?php

namespace Antigravity\MetaAdsAttribution\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Antigravity\MetaAdsAttribution\Services\MetaAttributionManager;
use Symfony\Component\HttpFoundation\Response;

class CaptureMetaAttributionMiddleware
{
    protected MetaAttributionManager $manager;

    public function __construct(MetaAttributionManager $manager)
    {
        $this->manager = $manager;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('meta-attribution.enabled', true)) {
            return $next($request);
        }

        // Process attribution details from request & cookies
        $cookieName = config('meta-attribution.cookie_name', 'meta_visitor_id');
        $visitorId = $request->cookie($cookieName);

        if (!$visitorId) {
            $visitorId = (string) Str::uuid();
        }

        $attribution = $this->manager->processRequestAttribution($request, $visitorId);

        $response = $next($request);

        // Attach first-party cookie if new or refreshing lifetime (90 days)
        $cookieLifetime = config('meta-attribution.cookie_lifetime', 60 * 24 * 90); // minutes
        if ($response instanceof Response) {
            $response->headers->setCookie(
                Cookie::make($cookieName, $visitorId, $cookieLifetime, '/', null, false, false)
            );
        }

        return $response;
    }
}

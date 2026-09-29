<?php

namespace RsmMonaem\MetaAdsAttribution\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use RsmMonaem\MetaAdsAttribution\Services\MetaAttributionManager;
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

        // Attach first-party cookies (90-day retention, Safari ITP resilience)
        $cookieLifetime = (int) config('meta-attribution.cookie_lifetime', 60 * 24 * 90); // minutes
        $isSecure = $request->isSecure();

        if ($response instanceof Response) {
            // 1. Primary Visitor Identifier
            $response->headers->setCookie(
                Cookie::make($cookieName, $visitorId, $cookieLifetime, '/', null, $isSecure, false, false, 'Lax')
            );

            // 2. Server-side Meta Browser Cookie (_fbp)
            if (!$request->hasCookie('_fbp') && !empty($attribution->fbp)) {
                $response->headers->setCookie(
                    Cookie::make('_fbp', $attribution->fbp, $cookieLifetime, '/', null, $isSecure, false, false, 'Lax')
                );
            }

            // 3. Server-side Meta Click Identifier Cookie (_fbc)
            if (!$request->hasCookie('_fbc') && !empty($attribution->fbc)) {
                $response->headers->setCookie(
                    Cookie::make('_fbc', $attribution->fbc, $cookieLifetime, '/', null, $isSecure, false, false, 'Lax')
                );
            }
        }

        return $response;
    }
}

<?php

namespace Antigravity\MetaAdsAttribution\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Antigravity\MetaAdsAttribution\Models\MetaAdAttribution;
use Antigravity\MetaAdsAttribution\Models\MetaTrackingSession;
use Antigravity\MetaAdsAttribution\Models\MetaOrderAttribution;
use Antigravity\MetaAdsAttribution\Models\MetaConversionEvent;
use Antigravity\MetaAdsAttribution\Services\MetaConversionService;

class MetaAttributionDashboardController extends Controller
{
    public function index(Request $request)
    {
        $metaVisitorsCount = MetaAdAttribution::where('utm_source', 'facebook')
            ->orWhereNotNull('fbclid')
            ->count();

        $metaSessionsCount = MetaTrackingSession::where('utm_source', 'facebook')
            ->orWhereNotNull('fbclid')
            ->count();

        $metaOrdersQuery = MetaOrderAttribution::where('attribution_source', 'facebook')
            ->orWhereNotNull('fbclid');

        $totalMetaOrders = (clone $metaOrdersQuery)->count();

        // Get delivered orders joined with orders table if available or using CAPI sent events
        $deliveredEvents = MetaConversionEvent::where('event_name', 'Purchase')
            ->where('status', 'sent')
            ->pluck('order_id')
            ->filter();

        $deliveredMetaOrdersCount = (clone $metaOrdersQuery)
            ->whereIn('order_id', $deliveredEvents)
            ->count();

        $deliveredMetaRevenue = (clone $metaOrdersQuery)
            ->whereIn('order_id', $deliveredEvents)
            ->sum('order_amount');

        $conversionRate = $metaVisitorsCount > 0 ? round(($deliveredMetaOrdersCount / $metaVisitorsCount) * 100, 2) : 0.0;

        // Campaign breakdown
        $campaigns = MetaOrderAttribution::select(
                DB::raw('COALESCE(utm_campaign, campaign, "Unassigned") as campaign_name'),
                DB::raw('COUNT(DISTINCT order_id) as total_orders'),
                DB::raw('SUM(order_amount) as total_revenue')
            )
            ->where(function($q) {
                $q->where('attribution_source', 'facebook')->orWhereNotNull('fbclid');
            })
            ->groupBy('campaign_name')
            ->get();

        // Conversion event logs
        $conversionLogs = MetaConversionEvent::latest()->paginate(15);

        return view('meta-attribution::dashboard', compact(
            'metaVisitorsCount',
            'metaSessionsCount',
            'totalMetaOrders',
            'deliveredMetaOrdersCount',
            'deliveredMetaRevenue',
            'conversionRate',
            'campaigns',
            'conversionLogs'
        ));
    }

    public function retryEvent(int $id, MetaConversionService $conversionService)
    {
        $eventLog = MetaConversionEvent::findOrFail($id);
        $result = $conversionService->retryFailedEvent($eventLog);

        if ($result['success']) {
            return redirect()->back()->with('success', "Event {$eventLog->event_id} retried successfully!");
        } else {
            return redirect()->back()->with('error', "Retry failed: " . ($result['error'] ?? 'Unknown error'));
        }
    }
}

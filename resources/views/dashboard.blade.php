<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meta Ads Attribution & CAPI Dashboard</title>
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; }
        body { background-color: #0f172a; color: #f8fafc; padding: 24px; }
        .container { max-width: 1280px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #1e293b; }
        .header h1 { font-size: 1.75rem; font-weight: 700; background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .badge { background-color: #1e293b; color: #38bdf8; padding: 6px 12px; border-radius: 9999px; font-size: 0.85rem; border: 1px solid #0369a1; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .card .title { font-size: 0.875rem; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
        .card .value { font-size: 1.875rem; font-weight: 700; color: #f8fafc; }
        .card .subtext { font-size: 0.75rem; color: #64748b; margin-top: 4px; }
        .card-green .value { color: #4ade80; }
        .card-blue .value { color: #60a5fa; }
        .card-purple .value { color: #c084fc; }
        .table-container { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .table-title { padding: 16px 20px; font-size: 1.1rem; font-weight: 600; background-color: #0f172a; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #1e293b; padding: 12px 16px; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid #334155; }
        td { padding: 14px 16px; font-size: 0.9rem; border-bottom: 1px solid #334155; }
        tr:hover { background-color: #334155; }
        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-sent { background-color: #065f46; color: #34d399; }
        .status-failed { background-color: #881337; color: #f43f5e; }
        .status-pending { background-color: #78350f; color: #fbbf24; }
        .btn-retry { background-color: #2563eb; color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; text-decoration: none; display: inline-block; transition: background-color 0.2s; }
        .btn-retry:hover { background-color: #1d4ed8; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .alert-success { background-color: #065f46; color: #a7f3d0; border: 1px solid #059669; }
        .alert-error { background-color: #881337; color: #fecdd3; border: 1px solid #e11d48; }
        .pagination { padding: 12px 16px; display: flex; justify-content: flex-end; gap: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Meta Ads Attribution & CAPI Dashboard</h1>
                <p style="color: #94a3b8; font-size: 0.9rem; margin-top: 4px;">End-to-End Customer Journey & Delivered Order Conversions Tracking</p>
            </div>
            <div>
                <span class="badge">Mode: {{ config('meta-attribution.enabled') ? 'Active' : 'Disabled' }}</span>
                <span class="badge">Trigger: {{ strtoupper(config('meta-attribution.qualified_order_status', 'DELIVERED')) }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <!-- Metrics Overview -->
        <div class="grid-4">
            <div class="card card-blue">
                <div class="title">Meta Visitors</div>
                <div class="value">{{ number_format($metaVisitorsCount) }}</div>
                <div class="subtext">{{ number_format($metaSessionsCount) }} Total Sessions</div>
            </div>
            <div class="card card-purple">
                <div class="title">Meta Attributed Orders</div>
                <div class="value">{{ number_format($totalMetaOrders) }}</div>
                <div class="subtext">From Ad Clicks / UTMs</div>
            </div>
            <div class="card card-green">
                <div class="title">Delivered Meta Orders</div>
                <div class="value">{{ number_format($deliveredMetaOrdersCount) }}</div>
                <div class="subtext">Qualified CAPI Conversions</div>
            </div>
            <div class="card card-green">
                <div class="title">Delivered Revenue</div>
                <div class="value">${{ number_format($deliveredMetaRevenue, 2) }}</div>
                <div class="subtext">Conversion Rate: {{ $conversionRate }}%</div>
            </div>
        </div>

        <!-- Campaign Performance Table -->
        <div class="table-container">
            <div class="table-title">
                <span>Campaign Attribution Performance</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Campaign Name</th>
                        <th>Total Orders</th>
                        <th>Attributed Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td style="font-weight: 600; color: #38bdf8;">{{ $campaign->campaign_name }}</td>
                            <td>{{ number_format($campaign->total_orders) }}</td>
                            <td style="color: #4ade80; font-weight: 600;">${{ number_format($campaign->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align: center; color: #64748b; padding: 24px;">No campaign attribution data recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Meta CAPI Conversion Event Logs -->
        <div class="table-container">
            <div class="table-title">
                <span>Meta Conversions API (CAPI) Audit Log</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Event ID</th>
                        <th>Order ID</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>HTTP</th>
                        <th>Retries</th>
                        <th>Attempted At</th>
                        <th>Details / Error</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conversionLogs as $log)
                        <tr>
                            <td style="font-family: monospace; color: #f8fafc;">{{ $log->event_id }}</td>
                            <td>#{{ $log->order_id }}</td>
                            <td>{{ $log->event_name }}</td>
                            <td>
                                <span class="status-badge status-{{ $log->status }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td>{{ $log->http_status ?? '-' }}</td>
                            <td>{{ $log->retry_count }}</td>
                            <td style="font-size: 0.8rem; color: #94a3b8;">
                                {{ $log->last_attempt_at ? $log->last_attempt_at->format('Y-m-d H:i:s') : '-' }}
                            </td>
                            <td style="max-width: 250px; font-size: 0.8rem; color: #cbd5e1; word-break: break-word;">
                                @if($log->error_message)
                                    <span style="color: #f43f5e;">{{ $log->error_message }}</span>
                                @elseif($log->meta_response && isset($log->meta_response['events_received']))
                                    <span style="color: #34d399;">Received: {{ $log->meta_response['events_received'] }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($log->status === 'failed')
                                    <form action="{{ route('meta-attribution.retry', $log->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-retry">Retry</button>
                                    </form>
                                @else
                                    <span style="color: #64748b;">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; color: #64748b; padding: 24px;">No CAPI conversion events logged yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

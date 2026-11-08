<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\FieldworkSchedule;
use App\Models\Portfolio;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        $stats = [
            'totalPortfolioAccounts' => Portfolio::count(),
            'totalAgents' => Agent::count(),
            'totalAssignedAccounts' => Portfolio::whereNotNull('assigned_agent_id')->count(),
            'totalUnassignedAccounts' => Portfolio::whereNull('assigned_agent_id')->count(),
            'totalActiveAccounts' => Portfolio::where('is_active', true)->count(),
            'totalAccountsNoUpdates' => Portfolio::whereNull('last_updated_at')->orWhere('last_updated_at', '<', now()->subDays(30))->count(),
            'totalScheduledFieldwork' => FieldworkSchedule::count(),
            'completedFieldwork' => FieldworkSchedule::where('status', 'completed')->count(),
            'pendingFieldwork' => FieldworkSchedule::where('status', 'pending')->count(),
            'cancelledFieldwork' => FieldworkSchedule::where('status', 'cancelled')->count(),
        ];

        $clusterDistribution = Portfolio::select('cluster', DB::raw('count(*) as total'))
            ->whereNotNull('cluster')
            ->groupBy('cluster')
            ->orderByDesc('total')
            ->get();

        $productDistribution = Portfolio::select('product_line', DB::raw('count(*) as total'))
            ->whereNotNull('product_line')
            ->groupBy('product_line')
            ->orderByDesc('total')
            ->get();

        $serviceDistribution = Portfolio::select('service_type', DB::raw('count(*) as total'))
            ->whereNotNull('service_type')
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->get();

        $agentPerformance = Agent::withCount('portfolios')
            ->orderByDesc('portfolios_count')
            ->limit(6)
            ->get();

        $monthlyMonitoring = Portfolio::select(
            DB::raw("strftime('%Y-%m', created_at) as month"),
            DB::raw('count(*) as total')
        )
            ->groupBy(DB::raw("strftime('%Y-%m', created_at)"))
            ->orderBy('month')
            ->limit(12)
            ->get();

        $fieldworkStatus = FieldworkSchedule::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $recentActivities = [
            [
                'title' => 'Portfolio import processed',
                'detail' => 'Latest import imported 14 portfolios successfully.',
                'timestamp' => now()->subHours(3)->toDateTimeString(),
            ],
            [
                'title' => 'Fieldwork scheduled',
                'detail' => '3 new field visits assigned to the NCR cluster.',
                'timestamp' => now()->subHours(7)->toDateTimeString(),
            ],
            [
                'title' => 'Portfolio review flagged',
                'detail' => '2 accounts were marked as NEEDS VERIFICATION.',
                'timestamp' => now()->subDay()->toDateTimeString(),
            ],
        ];

        return response()->json([
            'stats' => $stats,
            'clusterDistribution' => $clusterDistribution,
            'productDistribution' => $productDistribution,
            'serviceDistribution' => $serviceDistribution,
            'agentPerformance' => $agentPerformance,
            'monthlyMonitoring' => $monthlyMonitoring,
            'fieldworkStatus' => $fieldworkStatus,
            'recentActivities' => $recentActivities,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\FieldworkSchedule;
use App\Models\ImportRecord;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function dashboard(Request $request)
    {
        $portfolios = $this->scopedPortfolios($request->user());
        $scheduleQuery = FieldworkSchedule::query();
        $this->scopeSchedules($scheduleQuery, $request->user());

        $total = (clone $portfolios)->count();
        $byCluster = (clone $portfolios)
            ->select('cluster as name', DB::raw('count(*) as total'))
            ->whereNotNull('cluster')->groupBy('cluster')->orderByDesc('total')->get();
        $byProduct = (clone $portfolios)
            ->select('product_line as name', DB::raw('count(*) as total'))
            ->whereNotNull('product_line')->groupBy('product_line')->orderByDesc('total')->get();
        $byStatus = (clone $portfolios)
            ->select('latest_status as name', DB::raw('count(*) as total'))
            ->whereNotNull('latest_status')->groupBy('latest_status')->orderByDesc('total')->get();
        $agentPerformance = Agent::query()
            ->withCount(['portfolios' => fn (Builder $query) => $query->where('is_current', true)])
            ->when(!$request->user()->isAdmin(), fn (Builder $query) => $query->whereIn('id', $request->user()->agents()->select('agents.id')))
            ->orderByDesc('portfolios_count')->limit(8)->get();
        $recentImports = $request->user()->isAdmin()
            ? ImportRecord::query()->where('status', 'completed')->latest()->limit(5)->get()
            : collect();
        $latestUpdates = (clone $portfolios)->with('agent:id,agent_name')
            ->whereNotNull('last_updated_at')->latest('last_updated_at')->limit(6)->get();
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', last_updated_at)"
            : "DATE_FORMAT(last_updated_at, '%Y-%m')";
        $scheduleMonthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', schedule_date)"
            : "DATE_FORMAT(schedule_date, '%Y-%m')";

        return response()->json([
            'stats' => [
                'totalPortfolioAccounts' => $total,
                'totalAgents' => Agent::query()->when(!$request->user()->isAdmin(), fn (Builder $query) => $query->whereIn('id', $request->user()->agents()->select('agents.id')))->count(),
                'totalAssignedAccounts' => (clone $portfolios)->whereNotNull('assigned_agent_id')->count(),
                'totalUnassignedAccounts' => (clone $portfolios)->whereNull('assigned_agent_id')->count(),
                'totalAccountsNoUpdates' => (clone $portfolios)->whereRaw('UPPER(TRIM(latest_status)) = ?', ['NO UPDATES YET'])->count(),
                'totalScheduledFieldwork' => (clone $scheduleQuery)->whereDate('schedule_date', '>=', today())->count(),
            ],
            'clusterDistribution' => $byCluster,
            'productDistribution' => $byProduct,
            'statusDistribution' => $byStatus,
            'monthlyMonitoring' => (clone $portfolios)
                ->select(DB::raw("$monthExpression as month"), DB::raw('count(*) as total'))
                ->whereNotNull('last_updated_at')->groupBy(DB::raw($monthExpression))->orderBy('month')->limit(12)->get(),
            'monthlyFieldwork' => (clone $scheduleQuery)
                ->select(DB::raw("$scheduleMonthExpression as month"), DB::raw('count(*) as total'))
                ->groupBy(DB::raw($scheduleMonthExpression))->orderBy('month')->limit(12)->get(),
            'agentPerformance' => $agentPerformance,
            'recentActivities' => $recentImports->map(fn (ImportRecord $record) => [
                'title' => 'Portfolio workbook imported',
                'detail' => "{$record->successful_rows} accounts updated from {$record->file_name}.",
                'timestamp' => $record->updated_at,
            ]),
            'upcomingFieldwork' => (clone $scheduleQuery)->with(['agent', 'portfolio'])->whereDate('schedule_date', '>=', today())->orderBy('schedule_date')->limit(5)->get(),
            'latestUpdates' => $latestUpdates,
        ]);
    }

    public function noUpdates(Request $request)
    {
        $query = $this->filteredPortfolios($request)->whereRaw('UPPER(TRIM(latest_status)) = ?', ['NO UPDATES YET']);
        return response()->json($query->with('agent:id,agent_name,agent_id')->orderBy('cluster')->orderBy('account_name')->paginate($this->perPage($request)));
    }

    public function agentByCluster(Request $request)
    {
        $query = $this->filteredPortfolios($request);
        return response()->json($query->with('agent:id,agent_name,agent_id')->orderBy('cluster')->orderBy('account_name')->paginate($this->perPage($request)));
    }

    public function areaMonitoring(Request $request)
    {
        $query = $this->filteredPortfolios($request);
        $clusterCounts = (clone $query)->select('cluster', DB::raw('count(*) as total'))
            ->whereNotNull('cluster')->groupBy('cluster')->orderBy('cluster')->get();
        $provinceLabel = "COALESCE(NULLIF(TRIM(province), ''), 'Needs location review')";
        $cityLabel = "COALESCE(NULLIF(TRIM(city), ''), 'Needs location review')";
        $provinceCounts = (clone $query)->select('cluster', DB::raw("$provinceLabel as province"), DB::raw('count(*) as total'))
            ->groupBy('cluster', DB::raw($provinceLabel))->orderBy('cluster')->orderByDesc('total')->get();
        $cityCounts = (clone $query)->select('cluster', DB::raw("$cityLabel as city"), DB::raw('count(*) as total'))
            ->groupBy('cluster', DB::raw($cityLabel))->orderBy('cluster')->orderByDesc('total')->get();
        $products = (clone $query)->select('product_line', DB::raw('count(*) as total'))
            ->whereNotNull('product_line')->groupBy('product_line')->orderByDesc('total')->get();
        $services = (clone $query)->select('service_type', DB::raw('count(*) as total'))
            ->whereNotNull('service_type')->groupBy('service_type')->orderByDesc('total')->get();

        return response()->json([
            'total' => (clone $query)->count(),
            'clusters' => $clusterCounts,
            'provinces' => $provinceCounts,
            'cities' => $cityCounts,
            'products' => $products,
            'services' => $services,
        ]);
    }

    public function filters(Request $request)
    {
        $query = $this->scopedPortfolios($request->user());

        return response()->json([
            'clusters' => (clone $query)->whereNotNull('cluster')->distinct()->orderBy('cluster')->pluck('cluster'),
            'agents' => Agent::query()
                ->when(!$request->user()->isAdmin(), fn (Builder $builder) => $builder->whereIn('id', $request->user()->agents()->select('agents.id')))
                ->orderBy('agent_name')->get(['id', 'agent_name', 'assigned_cluster']),
            'productLines' => (clone $query)->whereNotNull('product_line')->distinct()->orderBy('product_line')->pluck('product_line'),
            'serviceTypes' => (clone $query)->whereNotNull('service_type')->distinct()->orderBy('service_type')->pluck('service_type'),
            'classifications' => (clone $query)->whereNotNull('classification')->distinct()->orderBy('classification')->pluck('classification'),
            'statuses' => (clone $query)->whereNotNull('latest_status')->distinct()->orderBy('latest_status')->pluck('latest_status'),
        ]);
    }

    public function reports(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = $this->filteredPortfolios($request);
        $from = $request->date('from');
        $to = $request->date('to');
        if ($from) {
            $query->whereDate('last_updated_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('last_updated_at', '<=', $to);
        }

        return response()->json([
            'total' => (clone $query)->count(),
            'noUpdates' => (clone $query)->whereRaw('UPPER(TRIM(latest_status)) = ?', ['NO UPDATES YET'])->count(),
            'unassigned' => (clone $query)->whereNull('assigned_agent_id')->count(),
            'byCluster' => (clone $query)->select('cluster', DB::raw('count(*) as total'))->groupBy('cluster')->orderBy('cluster')->get(),
            'byAgent' => (clone $query)->select('assigned_agent_id', DB::raw('count(*) as total'))
                ->with('agent:id,agent_name')->whereNotNull('assigned_agent_id')->groupBy('assigned_agent_id')->get(),
            'fieldwork' => $this->scopedSchedules($request->user())->whereBetween('schedule_date', [$from ?? now()->startOfMonth(), $to ?? now()->endOfMonth()])->count(),
            'imports' => $request->user()->isAdmin()
                ? ImportRecord::query()->where('status', 'completed')->latest()->limit(10)->get()
                : [],
        ]);
    }

    private function filteredPortfolios(Request $request): Builder
    {
        $query = $this->scopedPortfolios($request->user());
        foreach (['cluster', 'province', 'city', 'product_line', 'service_type', 'classification', 'assignment_status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }
        if ($request->filled('agent_id')) {
            $query->where('assigned_agent_id', $request->integer('agent_id'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('account_number', 'like', $search)
                ->orWhere('account_name', 'like', $search)
                ->orWhere('address', 'like', $search)
                ->orWhere('source_agent_name', 'like', $search));
        }
        return $query;
    }

    private function scopedPortfolios(User $user): Builder
    {
        return Portfolio::query()->where('is_current', true)
            ->when(!$user->isAdmin(), fn (Builder $query) => $query->whereIn('assigned_agent_id', $user->agents()->select('agents.id')));
    }

    private function scopedSchedules(User $user): Builder
    {
        return FieldworkSchedule::query()
            ->when(!$user->isAdmin(), fn (Builder $query) => $query->whereIn('agent_id', $user->agents()->select('agents.id')));
    }

    private function scopeSchedules(Builder $query, User $user): void
    {
        if (!$user->isAdmin()) {
            $query->whereIn('agent_id', $user->agents()->select('agents.id'));
        }
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 25), 1), 100);
    }
}

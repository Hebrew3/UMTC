<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredPortfolios($request)->with('agent');

        $sortField = in_array($request->input('sortField', 'account_number'), ['account_number', 'account_name', 'last_updated_at', 'latest_status'], true)
            ? $request->input('sortField', 'account_number')
            : 'account_number';

        $sortDirection = in_array($request->input('sortDirection', 'asc'), ['asc', 'desc'], true)
            ? $request->input('sortDirection', 'asc')
            : 'asc';

        $items = $query->orderBy($sortField, $sortDirection)
            ->paginate(min(max($request->integer('per_page', 12), 1), 100));

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:255', 'unique:portfolios,account_number'],
            'account_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:5000'],
            'cluster' => ['nullable', Rule::in(['NCR', 'NORTH', 'SOUTH', 'VISAYAS', 'MINDANAO'])],
            'assigned_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'product_line' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', 'string', 'max:255'],
            'classification' => ['nullable', 'string', 'max:255'],
            'latest_status' => ['nullable', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
        ]);
        if (!empty($validated['assigned_agent_id']) && !empty($validated['cluster'])) {
            $agentMatchesCluster = Agent::query()
                ->whereKey($validated['assigned_agent_id'])
                ->where('assigned_cluster', $validated['cluster'])
                ->exists();
            if (!$agentMatchesCluster) {
                throw ValidationException::withMessages([
                    'assigned_agent_id' => ['The selected agent is not assigned to the selected cluster.'],
                ]);
            }
        }

        $attributes = array_merge([
            'latest_status' => 'ACTIVE',
            'verification_status' => 'MANUAL',
            'assignment_status' => 'manual',
            'is_current' => true,
            'is_active' => true,
            'last_updated_at' => now(),
        ], $validated);
        $attributes['latest_status'] = $validated['latest_status'] ?? 'ACTIVE';
        $portfolio = Portfolio::query()->create($attributes);

        return response()->json($portfolio->load('agent'), 201);
    }

    public function export(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = $this->filteredPortfolios($request)->with('agent');
        if ($request->filled('from')) {
            $query->whereDate('last_updated_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('last_updated_at', '<=', $request->date('to'));
        }
        $fileName = 'umtc-portfolio-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Account Number', 'Account Name', 'Field Agent', 'Product Line', 'Service Type', 'Classification', 'Cluster', 'Address', 'Latest Status', 'Assignment Status']);
            $query->chunkById(500, function ($portfolios) use ($output): void {
                foreach ($portfolios as $portfolio) {
                    $values = [
                        $portfolio->account_number,
                        $portfolio->account_name,
                        $portfolio->agent?->agent_name ?? $portfolio->source_agent_name,
                        $portfolio->product_line,
                        $portfolio->service_type,
                        $portfolio->classification,
                        $portfolio->cluster,
                        $portfolio->address,
                        $portfolio->latest_status,
                        $portfolio->assignment_status,
                    ];
                    fputcsv($output, array_map(fn ($value) => $this->spreadsheetSafeValue($value), $values));
                }
            });
            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, Portfolio $portfolio)
    {
        abort_unless($portfolio->is_current && ($request->user()->isAdmin() || $request->user()->agents()->whereKey($portfolio->assigned_agent_id)->exists()), 404);
        return response()->json($portfolio->load('agent'));
    }

    private function filteredPortfolios(Request $request): Builder
    {
        $query = Portfolio::query()->where('is_current', true)->with('agent')
            ->when(!$request->user()->isAdmin(), fn (Builder $builder) => $builder->whereIn('assigned_agent_id', $request->user()->agents()->select('agents.id')));
        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('account_number', 'like', $term)
                ->orWhere('account_name', 'like', $term)
                ->orWhere('address', 'like', $term)
                ->orWhere('client', 'like', $term));
        }
        foreach (['cluster', 'product_line', 'service_type', 'classification', 'latest_status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }
        if ($request->filled('agent_id')) {
            $query->where('assigned_agent_id', $request->integer('agent_id'));
        }

        return $query;
    }

    private function spreadsheetSafeValue(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}

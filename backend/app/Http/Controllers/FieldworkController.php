<?php

namespace App\Http\Controllers;

use App\Models\FieldworkSchedule;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldworkController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,completed,cancelled'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'cluster' => ['nullable', 'in:NCR,NORTH,SOUTH,VISAYAS,MINDANAO'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = $this->scopedQuery($request)->with(['portfolio', 'agent']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }
        if ($request->filled('cluster')) {
            $query->whereHas('agent', fn ($agentQuery) => $agentQuery->where('assigned_cluster', $request->input('cluster')));
        }
        if ($request->filled('from')) {
            $query->whereDate('schedule_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('schedule_date', '<=', $request->date('to'));
        }

        return response()->json($query->orderBy('schedule_date', 'asc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'portfolio_ids' => ['required', 'array', 'min:1'],
            'portfolio_ids.*' => ['required', 'integer', 'distinct', 'exists:portfolios,id'],
            'schedule_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:pending,completed,cancelled'],
        ]);
        $this->authorizeAgent($request, (int) $validated['agent_id']);
        $portfolios = $this->authorizedPortfolios($request, $validated['portfolio_ids'], (int) $validated['agent_id']);

        $schedules = DB::transaction(function () use ($validated, $portfolios, $request) {
            return $portfolios->map(fn (Portfolio $portfolio) => FieldworkSchedule::query()->create([
                'title' => 'Fieldwork: '.$portfolio->account_name,
                'portfolio_id' => $portfolio->id,
                'agent_id' => $validated['agent_id'],
                'schedule_date' => $validated['schedule_date'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'location' => $validated['location'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]))->load(['portfolio', 'agent']);
        });

        return response()->json($schedules, 201);
    }

    public function update(Request $request, FieldworkSchedule $schedule)
    {
        abort_unless($this->scopedQuery($request)->whereKey($schedule->id)->exists(), 404);
        $validated = $request->validate([
            'agent_id' => ['sometimes', 'required', 'integer', 'exists:agents,id'],
            'portfolio_id' => ['sometimes', 'required', 'integer', 'exists:portfolios,id'],
            'schedule_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', 'in:pending,completed,cancelled'],
        ]);

        $agentId = (int) ($validated['agent_id'] ?? $schedule->agent_id);
        $this->authorizeAgent($request, $agentId);
        $portfolioId = (int) ($validated['portfolio_id'] ?? $schedule->portfolio_id);
        $this->authorizedPortfolios($request, [$portfolioId], $agentId);
        $schedule->update($validated);

        return response()->json($schedule->refresh()->load(['portfolio', 'agent']));
    }

    public function destroy(Request $request, FieldworkSchedule $schedule)
    {
        abort_unless($this->scopedQuery($request)->whereKey($schedule->id)->exists(), 404);
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }

    private function scopedQuery(Request $request)
    {
        return FieldworkSchedule::query()->when(
            !$request->user()->isAdmin(),
            fn ($query) => $query->whereIn('agent_id', $request->user()->agents()->select('agents.id')),
        );
    }

    private function authorizeAgent(Request $request, int $agentId): void
    {
        abort_unless(
            $request->user()->isAdmin() || $request->user()->agents()->whereKey($agentId)->exists(),
            403,
        );
    }

    private function authorizedPortfolios(Request $request, array $ids, int $agentId)
    {
        $query = Portfolio::query()->where('is_current', true)->whereIn('id', $ids);
        if (!$request->user()->isAdmin()) {
            $query->whereIn('assigned_agent_id', $request->user()->agents()->select('agents.id'));
        }
        if ($query->count() !== count(array_unique($ids))) {
            abort(422, 'One or more accounts are unavailable or outside your assigned scope.');
        }
        $portfolios = $query->get();
        if ($portfolios->contains(fn (Portfolio $portfolio) => $portfolio->assigned_agent_id && $portfolio->assigned_agent_id !== $agentId)) {
            abort(422, 'A selected account is assigned to a different Field Agent.');
        }

        return $portfolios;
    }
}

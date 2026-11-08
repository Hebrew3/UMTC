<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $query = Agent::query()
            ->withCount(['portfolios' => fn ($portfolioQuery) => $portfolioQuery->where('is_current', true)])
            ->when(!$request->user()->isAdmin(), fn ($agentQuery) => $agentQuery->whereIn('id', $request->user()->agents()->select('agents.id')));

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn ($builder) => $builder->where('agent_name', 'like', $term)
                ->orWhere('agent_id', 'like', $term)
                ->orWhere('assigned_cluster', 'like', $term)
                ->orWhere('assigned_area', 'like', $term));
        }

        if ($request->filled('cluster')) {
            $query->where('assigned_cluster', $request->input('cluster'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->orderBy('agent_name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'string', 'max:255', 'unique:agents,agent_id'],
            'agent_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'assigned_cluster' => ['nullable', Rule::in(['NCR', 'NORTH', 'SOUTH', 'VISAYAS', 'MINDANAO'])],
            'assigned_area' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        return response()->json(Agent::query()->create($validated), 201);
    }

    public function update(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'agent_id' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('agents', 'agent_id')->ignore($agent->id)],
            'agent_name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'assigned_cluster' => ['nullable', Rule::in(['NCR', 'NORTH', 'SOUTH', 'VISAYAS', 'MINDANAO'])],
            'assigned_area' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
        $agent->update($validated);

        return response()->json($agent->refresh());
    }
}

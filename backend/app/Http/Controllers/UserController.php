<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::query()
            ->where('role', 'senior_agent')
            ->with('agents:id,agent_id,agent_name,assigned_cluster,assigned_area')
            ->orderBy('name')
            ->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'agent_ids' => ['sometimes', 'array'],
            'agent_ids.*' => ['integer', 'distinct', 'exists:agents,id'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
                'role' => 'senior_agent',
                'is_active' => true,
            ]);
            $user->agents()->sync($validated['agent_ids'] ?? []);

            return $user;
        });

        return response()->json($user->load('agents'), 201);
    }

    public function assignAgents(Request $request, User $user)
    {
        if ($user->role !== 'senior_agent') {
            return response()->json(['message' => 'Agent assignments are only available for Senior Agent accounts.'], 422);
        }

        $validated = $request->validate([
            'agent_ids' => ['present', 'array'],
            'agent_ids.*' => ['integer', 'distinct', 'exists:agents,id'],
        ]);

        $user->agents()->sync($validated['agent_ids']);

        return response()->json($user->load('agents'));
    }
}

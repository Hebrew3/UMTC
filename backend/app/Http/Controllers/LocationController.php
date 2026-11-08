<?php

namespace App\Http\Controllers;

use App\Models\ClusterReference;
use App\Services\AddressLocationResolver;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function resolve(Request $request, AddressLocationResolver $locations)
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:5000'],
        ]);
        $references = ClusterReference::query()->get([
            'cluster',
            'location',
            'province',
            'region',
            'assigned_agent_name',
        ]);
        if ($references->isEmpty()) {
            return response()->json([
                'message' => 'Location reference data is unavailable. Import a monitoring workbook first.',
            ], 503);
        }

        return response()->json($locations->resolve($validated['address'], $references));
    }
}

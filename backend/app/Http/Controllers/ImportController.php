<?php

namespace App\Http\Controllers;

use App\Models\ImportRecord;
use App\Services\SpreadsheetImportService;
use Illuminate\Http\Request;
use RuntimeException;

class ImportController extends Controller
{
    public function __construct(private readonly SpreadsheetImportService $imports)
    {
    }

    public function index()
    {
        $this->imports->expireOldPreviews();
        return response()->json(
            ImportRecord::query()->with('user:id,name,email')->latest()->paginate(20),
        );
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            return response()->json($this->imports->preview($validated['file'], $request->user()), 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function confirm(Request $request, ImportRecord $import)
    {
        try {
            return response()->json($this->imports->confirm($import, $request->user()));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}

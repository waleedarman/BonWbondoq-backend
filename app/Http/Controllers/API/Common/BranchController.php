<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Active branches fetched successfully.',
            'data' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('branches', 'code')],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $branch = Branch::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'location' => $validated['location'] ?? $validated['name'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'data' => $branch,
        ], 201);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $branch->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully.',
            'data' => null,
        ]);
    }
}

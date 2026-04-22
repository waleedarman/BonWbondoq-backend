<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

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
}

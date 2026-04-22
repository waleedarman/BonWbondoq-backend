<?php

namespace App\Http\Controllers\API\Manager;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeRequest::query()
            ->with(['user.role', 'user.branch', 'reviewer'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee requests fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function show(EmployeeRequest $employeeRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Employee request fetched successfully.',
            'data' => $employeeRequest->load(['user.role', 'user.branch', 'reviewer']),
        ]);
    }

    public function approve(Request $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        if ($employeeRequest->status !== EmployeeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending employee requests can be approved.',
            ], 422);
        }

        $role = Role::findOrFail($data['role_id']);

        if (in_array($role->slug ?? $role->name, [Role::MANAGER], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Public join requests cannot be approved directly as manager accounts.',
            ], 422);
        }

        DB::transaction(function () use ($request, $employeeRequest, $data): void {
            $employeeRequest->user->forceFill([
                'role_id' => $data['role_id'],
                'branch_id' => $data['branch_id'],
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ])->save();

            $employeeRequest->forceFill([
                'status' => EmployeeRequest::STATUS_ACCEPTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee request approved successfully.',
            'data' => $employeeRequest->fresh(['user.role', 'user.branch', 'reviewer']),
        ]);
    }

    public function reject(Request $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($employeeRequest->status !== EmployeeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending employee requests can be rejected.',
            ], 422);
        }

        $employeeRequest->forceFill([
            'status' => EmployeeRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Employee request rejected successfully.',
            'data' => $employeeRequest->fresh(['user.role', 'user.branch', 'reviewer']),
        ]);
    }
}

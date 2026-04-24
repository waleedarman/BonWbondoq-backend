<?php

namespace App\Http\Controllers\API\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\ApproveEmployeeRequest;
use App\Http\Requests\Manager\RejectEmployeeRequest;
use App\Models\EmployeeRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeRequest::query()
            ->with(['user.role', 'user.branch', 'reviewer'])
            ->whereHas('user', fn ($query) => $query->where('branch_id', $request->user()->branch_id))
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
        $this->abortUnlessCurrentBranch($employeeRequest->user?->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Employee request fetched successfully.',
            'data' => $employeeRequest->load(['user.role', 'user.branch', 'reviewer']),
        ]);
    }

    public function approve(ApproveEmployeeRequest $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($employeeRequest->user?->branch_id);

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Employee requests can only be approved within your branch.',
            ], 422);
        }

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
                'branch_id' => $request->user()->branch_id,
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

    public function reject(RejectEmployeeRequest $request, EmployeeRequest $employeeRequest): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($employeeRequest->user?->branch_id);

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

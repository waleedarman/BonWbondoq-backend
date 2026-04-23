<?php

namespace App\Http\Controllers\API\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\AssignUserRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['role', 'branch', 'approvedBy'])->latest();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->integer('role_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Users fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully.',
            'data' => $user->load(['role', 'branch', 'employeeRequest', 'approvedBy']),
        ]);
    }

    public function assignRole(AssignUserRoleRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $user->forceFill([
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'User role assigned successfully.',
            'data' => $user->fresh(['role', 'branch']),
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $user->forceFill([
            'is_active' => true,
            'approved_at' => $user->approved_at ?? now(),
            'approved_by' => $user->approved_by ?? $request->user()->id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully.',
            'data' => $user->fresh(['role', 'branch']),
        ]);
    }

    public function deactivate(User $user): JsonResponse
    {
        $user->forceFill(['is_active' => false])->save();

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully.',
            'data' => $user->fresh(['role', 'branch']),
        ]);
    }
}

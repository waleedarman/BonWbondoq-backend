<?php

namespace App\Http\Controllers\API\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\AssignUserRoleRequest;
use App\Http\Requests\Manager\StoreManagedUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['role', 'branch', 'approvedBy'])
            ->where('branch_id', $request->user()->branch_id)
            ->latest();

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

        if ($request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only list users from your branch.',
            ], 403);
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

    public function store(StoreManagedUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Users can only be created inside your branch.',
            ], 422);
        }

        $role = Role::findOrFail($data['role_id']);

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'branch_id' => $request->user()->branch_id,
            'is_active' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load(['role', 'branch', 'approvedBy']),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->abortUnlessCurrentBranch($user->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully.',
            'data' => $user->load(['role', 'branch', 'employeeRequest', 'approvedBy']),
        ]);
    }

    public function assignRole(AssignUserRoleRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($user->branch_id);

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Users can only be assigned inside your branch.',
            ], 422);
        }

        $user->forceFill([
            'role_id' => $data['role_id'],
            'branch_id' => $request->user()->branch_id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'User role assigned successfully.',
            'data' => $user->fresh(['role', 'branch']),
        ]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        $this->abortUnlessCurrentBranch($user->branch_id);

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
        $this->abortUnlessCurrentBranch($user->branch_id);

        $user->forceFill(['is_active' => false])->save();

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully.',
            'data' => $user->fresh(['role', 'branch']),
        ]);
    }
}

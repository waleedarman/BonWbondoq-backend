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

    public function show(Request $request, User $user): JsonResponse
    {
        $this->abortUnlessCurrentBranch($user->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'User fetched successfully.',
            'data' => $user->load(['role', 'branch', 'employeeRequest', 'approvedBy']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $data['branch_id'] = $request->user()->branch_id;
        $data['is_active'] = true;
        $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        $data['approved_at'] = now();
        $data['approved_by'] = $request->user()->id;

        $user = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load(['role', 'branch']),
        ], 201);
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

    public function update(Request $request, User $user): JsonResponse
    {
        $this->abortUnlessCurrentBranch($user->branch_id);

        $data = $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Users can only be updated inside your branch.',
            ], 422);
        }

        $changes = [];

        if (array_key_exists('role_id', $data)) {
            $changes['role_id'] = $data['role_id'];
        }

        if (array_key_exists('is_active', $data)) {
            $changes['is_active'] = $data['is_active'];

            if ($data['is_active']) {
                $changes['approved_at'] = $user->approved_at ?? now();
                $changes['approved_by'] = $user->approved_by ?? $request->user()->id;
            }
        }

        if ($changes) {
            $changes['branch_id'] = $request->user()->branch_id;
            $user->forceFill($changes)->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
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

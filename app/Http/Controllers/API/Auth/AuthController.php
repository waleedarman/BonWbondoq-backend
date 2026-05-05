<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Branch;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchId = $data['branch_id'] ?? Branch::query()
            ->where('code', $data['branch_code'])
            ->value('id');

        $result = DB::transaction(function () use ($data, $branchId): array {
            $user = new User();
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => false,
                'role_id' => null,
                'branch_id' => $branchId,
            ])->save();

            $employeeRequest = new EmployeeRequest();
            $employeeRequest->forceFill([
                'user_id' => $user->id,
                'status' => EmployeeRequest::STATUS_PENDING,
            ])->save();

            app(SystemNotificationService::class)->notifyNewEmployeeRequest($user);

            return [
                'user' => $user->load(['role', 'branch']),
                'employee_request' => $employeeRequest,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee registration request submitted successfully.',
            'data' => $result,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::with(['role', 'branch'])->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if (! $user->is_active || ! $user->role_id) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending manager approval.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'token' => $user->createToken('mobile-app')->plainTextToken,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user fetched successfully.',
            'data' => $request->user()?->load(['role', 'branch']),
        ]);
    }
}

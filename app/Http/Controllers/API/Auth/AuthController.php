<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\EmployeeRequest;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data): array {
            $user = new User();
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => false,
                'role_id' => null,
                'branch_id' => $data['branch_id'],
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
            'message' => 'تم إرسال طلب إنشاء الحساب بنجاح.',
            'data' => $result,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::with(['role', 'branch'])->where('email', $credentials['email'])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني غير موجود أو غير مسجل.',
            ], 422);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
            ], 422);
        }

        if (! $user->is_active || ! $user->role_id) {
            return response()->json([
                'success' => false,
                'message' => 'حسابك غير مفعل بعد. يرجى انتظار موافقة الإدارة.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data' => [
                'token' => $user->createToken('mobile-app')->plainTextToken,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني غير موجود أو غير مسجل.',
            ], 422);
        }

        $code = (string) random_int(100000, 999999);

        PasswordResetCode::where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        PasswordResetCode::create([
            'user_id' => $user->id,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::raw(
            "رمز استعادة كلمة المرور الخاص بك هو: {$code}\n\nينتهي هذا الرمز خلال 10 دقائق.",
            function ($message) use ($email): void {
                $message->to($email)->subject('رمز استعادة كلمة المرور');
            },
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
            'data' => null,
        ]);
    }

    public function verifyResetCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $resetCode = $this->findValidResetCode(Str::lower(trim($data['email'])), $data['code']);
        $resetCode->forceFill(['verified_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'تم التحقق من رمز استعادة كلمة المرور بنجاح.',
            'data' => null,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower(trim($data['email']));
        $resetCode = $this->findValidResetCode($email, $data['code']);

        if (! $resetCode->verified_at) {
            $resetCode->forceFill(['verified_at' => now()])->save();
        }

        $resetCode->user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $resetCode->forceFill(['used_at' => now()])->save();
        $resetCode->user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
            'data' => null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المستخدم بنجاح.',
            'data' => $request->user()?->load(['role', 'branch']),
        ]);
    }

    private function findValidResetCode(string $email, string $code): PasswordResetCode
    {
        $resetCodes = PasswordResetCode::with('user')
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(5)
            ->get();

        $resetCode = $resetCodes->first(
            fn (PasswordResetCode $resetCode): bool => Hash::check($code, $resetCode->code_hash),
        );

        if (! $resetCode) {
            throw ValidationException::withMessages([
                'code' => ['رمز استعادة كلمة المرور غير صحيح أو منتهي الصلاحية.'],
            ]);
        }

        return $resetCode;
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Models\Branch;
use App\Models\EmployeeRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TestingAuthController extends TestingBaseController
{
    public function login(): View
    {
        return view('testing.auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
        ];

        if (! User::where('email', $credentials['email'])->exists()) {
            return back()
                ->withErrors(['email' => 'البريد الإلكتروني غير موجود أو غير مسجل.'])
                ->onlyInput('email');
        }

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'بيانات الدخول غير صحيحة.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user()->load('role');

        if (! $user->is_active || ! $user->role) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'هذا الحساب غير مفعل أو ما زال بانتظار موافقة المدير.',
            ]);
        }

        return redirect()
            ->route($this->homeRouteFor($user))
            ->with('status', 'أهلًا وسهلًا، تم تسجيل الدخول بنجاح.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('testing.login')
            ->with('status', 'تم تسجيل الخروج بنجاح.');
    }

    public function register(): View
    {
        return view('testing.auth.register', [
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_type' => ['required', 'in:employee,manager'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50', 'unique:users,phone'],
            'branch_id' => ['required', 'exists:branches,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'branch_id.required' => 'اختيار الفرع مطلوب للتجربة.',
            'terms.accepted' => 'يجب الموافقة على شروط الاستخدام الداخلي.',
        ]);

        $data['email'] = strtolower(trim($data['email']));

        DB::transaction(function () use ($data): void {
            $managerRole = Role::firstOrCreate(
                ['slug' => Role::MANAGER],
                ['name' => 'Manager', 'description' => 'Full system access']
            );

            $isManager = $data['account_type'] === 'manager';

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'branch_id' => $data['branch_id'],
                'role_id' => $isManager ? $managerRole->id : null,
                'password' => $data['password'],
                'is_active' => $isManager,
                'approved_at' => $isManager ? now() : null,
            ]);

            if (! $isManager) {
                EmployeeRequest::create([
                    'user_id' => $user->id,
                    'status' => EmployeeRequest::STATUS_PENDING,
                ]);

                app(SystemNotificationService::class)->notifyNewEmployeeRequest($user);
            }
        });

        $message = $data['account_type'] === 'manager'
            ? 'تم إنشاء حساب المدير التجريبي. يمكنك تسجيل الدخول الآن.'
            : 'تم إرسال طلب الانضمام. انتظر موافقة المدير وتحديد الدور.';

        return redirect()->route('testing.login')->with('status', $message);
    }
}

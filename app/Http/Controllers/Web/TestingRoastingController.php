<?php

namespace App\Http\Controllers\Web;

use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestingRoastingController extends TestingBaseController
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $filter = $request->query('filter', 'active');
        $query = RoastingRequest::with(['product', 'branch', 'assignedEmployee'])->latest();

        match ($filter) {
            'pending' => $query->where('status', RoastingRequest::STATUS_PENDING),
            'completed' => $query->where('status', RoastingRequest::STATUS_COMPLETED),
            default => $query->whereIn('status', [
                RoastingRequest::STATUS_ASSIGNED,
                RoastingRequest::STATUS_IN_PROGRESS,
            ]),
        };

        return view('testing.roasting.index', [
            'requests' => $query->get(),
            'filter' => $filter,
            'employees' => $this->usersByRole(Role::ROASTING_EMPLOYEE),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        return view('testing.roasting.create', [
            'products' => Product::with('branch')->where('is_active', true)->orderBy('name')->get(),
            'employees' => $this->usersByRole(Role::ROASTING_EMPLOYEE),
            'priorities' => RoastingRequest::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::in(RoastingRequest::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $status = $data['assigned_to'] ? RoastingRequest::STATUS_ASSIGNED : RoastingRequest::STATUS_PENDING;

        $roastingRequest = DB::transaction(function () use ($data, $product, $status): RoastingRequest {
            $roastingRequest = RoastingRequest::create([
                'code' => $this->generateRoastingCode(),
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
                'priority' => $data['priority'],
                'status' => $status,
                'created_by' => Auth::id(),
                'assigned_to' => $data['assigned_to'] ?? null,
                'branch_id' => $product->branch_id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logRoastingStatus($roastingRequest, $status, 'تم إنشاء عملية التحميص من واجهة الاختبار.');

            return $roastingRequest;
        });

        return redirect()
            ->route('testing.roasting.show', $roastingRequest)
            ->with('status', 'تم إنشاء عملية التحميص.');
    }

    public function show(RoastingRequest $roastingRequest): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::ROASTING_EMPLOYEE])) {
            return $redirect;
        }

        $user = Auth::user()->loadMissing('role');

        if ($user->role?->slug === Role::ROASTING_EMPLOYEE && $roastingRequest->assigned_to !== $user->id) {
            return redirect()->route('testing.roasting.tasks')->withErrors(['task' => 'هذه المهمة غير مخصصة لك.']);
        }

        return view('testing.roasting.show', [
            'request' => $roastingRequest->load(['product', 'branch', 'creator', 'assignedEmployee', 'statusLogs.changer']),
            'employees' => $this->usersByRole(Role::ROASTING_EMPLOYEE),
            'statuses' => RoastingRequest::STATUSES,
        ]);
    }

    public function assign(Request $request, RoastingRequest $roastingRequest): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $roastingRequest->update([
            'assigned_to' => $data['assigned_to'],
            'status' => RoastingRequest::STATUS_ASSIGNED,
        ]);

        $this->logRoastingStatus($roastingRequest, RoastingRequest::STATUS_ASSIGNED, 'تم تعيين موظف تحميص للعملية.');

        return back()->with('status', 'تم تعيين موظف التحميص.');
    }

    public function updateStatus(Request $request, RoastingRequest $roastingRequest): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::ROASTING_EMPLOYEE])) {
            return $redirect;
        }

        $user = Auth::user()->loadMissing('role');

        if ($user->role?->slug === Role::ROASTING_EMPLOYEE && $roastingRequest->assigned_to !== $user->id) {
            return back()->withErrors(['task' => 'هذه المهمة غير مخصصة لك.']);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(RoastingRequest::STATUSES)],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $updates = ['status' => $data['status']];

        if ($data['status'] === RoastingRequest::STATUS_IN_PROGRESS && ! $roastingRequest->started_at) {
            $updates['started_at'] = now();
        }

        if ($data['status'] === RoastingRequest::STATUS_COMPLETED) {
            $updates['completed_at'] = now();
        }

        $roastingRequest->update($updates);
        $this->logRoastingStatus($roastingRequest, $data['status'], $data['note'] ?? 'تم تحديث حالة التحميص.');

        return back()->with('status', 'تم تحديث حالة عملية التحميص.');
    }

    public function tasks(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::ROASTING_EMPLOYEE])) {
            return $redirect;
        }

        return view('testing.roasting.tasks', [
            'tasks' => RoastingRequest::with(['product', 'branch'])
                ->where('assigned_to', Auth::id())
                ->latest()
                ->get(),
        ]);
    }

    public function startTask(RoastingRequest $roastingRequest): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::ROASTING_EMPLOYEE])) {
            return $redirect;
        }

        if ($roastingRequest->assigned_to !== Auth::id()) {
            return back()->withErrors(['task' => 'هذه المهمة غير مخصصة لك.']);
        }

        $roastingRequest->update([
            'status' => RoastingRequest::STATUS_IN_PROGRESS,
            'started_at' => $roastingRequest->started_at ?? now(),
        ]);

        $this->logRoastingStatus($roastingRequest, RoastingRequest::STATUS_IN_PROGRESS, 'بدأ الموظف تنفيذ المهمة.');

        return back()->with('status', 'تم بدء مهمة التحميص.');
    }

    public function completeTask(RoastingRequest $roastingRequest): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::ROASTING_EMPLOYEE])) {
            return $redirect;
        }

        if ($roastingRequest->assigned_to !== Auth::id()) {
            return back()->withErrors(['task' => 'هذه المهمة غير مخصصة لك.']);
        }

        $roastingRequest->update([
            'status' => RoastingRequest::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->logRoastingStatus($roastingRequest, RoastingRequest::STATUS_COMPLETED, 'أنهى الموظف مهمة التحميص.');

        return back()->with('status', 'تم إنهاء مهمة التحميص.');
    }

    private function generateRoastingCode(): string
    {
        do {
            $code = 'ROAST-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        } while (RoastingRequest::where('code', $code)->exists());

        return $code;
    }
}

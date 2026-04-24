<?php

namespace App\Http\Controllers\Web;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\Role;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TestingRoastingController extends TestingBaseController
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $filter = $request->query('filter', 'active');
        $query = RoastingRequest::with(['product', 'branch', 'assignedEmployee'])
            ->where('branch_id', $this->currentBranchId())
            ->latest();

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
            'products' => Product::with('branch')
                ->where('branch_id', $this->currentBranchId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
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

        $product = Product::where('branch_id', $this->currentBranchId())->findOrFail($data['product_id']);
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
            ->with('status', 'تم إنشاء عملية التحميص بنجاح.');
    }

    public function show(RoastingRequest $roastingRequest): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::ROASTING_EMPLOYEE])) {
            return $redirect;
        }

        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

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

        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);
        abort_unless(
            $this->usersByRole(Role::ROASTING_EMPLOYEE)->contains('id', (int) $data['assigned_to']),
            422,
            'الموظف يجب أن يكون من نفس الفرع.'
        );

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

        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        $updates = ['status' => $data['status']];

        if ($data['status'] === RoastingRequest::STATUS_IN_PROGRESS && ! $roastingRequest->started_at) {
            $updates['started_at'] = now();
        }

        if ($data['status'] === RoastingRequest::STATUS_COMPLETED) {
            $updates['completed_at'] = now();
        }

        DB::transaction(function () use ($roastingRequest, $data, $updates): void {
            $previousStatus = $roastingRequest->status;

            $this->consumeStockIfNeeded($roastingRequest, $data['status']);
            $roastingRequest->update($updates);
            $this->logRoastingStatus($roastingRequest, $data['status'], $data['note'] ?? 'تم تحديث حالة التحميص.');

            if ($data['status'] === RoastingRequest::STATUS_COMPLETED && $previousStatus !== RoastingRequest::STATUS_COMPLETED) {
                app(SystemNotificationService::class)->notifyRoastingCompleted($roastingRequest);
            }
        });

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
                ->where('branch_id', $this->currentBranchId())
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

        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        DB::transaction(function () use ($roastingRequest): void {
            $this->consumeStockIfNeeded($roastingRequest, RoastingRequest::STATUS_IN_PROGRESS);
            $roastingRequest->update([
                'status' => RoastingRequest::STATUS_IN_PROGRESS,
                'started_at' => $roastingRequest->started_at ?? now(),
            ]);

            $this->logRoastingStatus($roastingRequest, RoastingRequest::STATUS_IN_PROGRESS, 'بدأ الموظف تنفيذ المهمة.');
        });

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

        $this->abortUnlessCurrentBranch($roastingRequest->branch_id);

        DB::transaction(function () use ($roastingRequest): void {
            $previousStatus = $roastingRequest->status;

            $this->consumeStockIfNeeded($roastingRequest, RoastingRequest::STATUS_COMPLETED);
            $roastingRequest->update([
                'status' => RoastingRequest::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $this->logRoastingStatus($roastingRequest, RoastingRequest::STATUS_COMPLETED, 'أنهى الموظف مهمة التحميص.');

            if ($previousStatus !== RoastingRequest::STATUS_COMPLETED) {
                app(SystemNotificationService::class)->notifyRoastingCompleted($roastingRequest);
            }
        });

        return back()->with('status', 'تم إنهاء مهمة التحميص.');
    }

    private function generateRoastingCode(): string
    {
        do {
            $code = 'ROAST-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        } while (RoastingRequest::where('code', $code)->exists());

        return $code;
    }

    private function consumeStockIfNeeded(RoastingRequest $roastingRequest, string $nextStatus): void
    {
        if (! in_array($nextStatus, [RoastingRequest::STATUS_IN_PROGRESS, RoastingRequest::STATUS_COMPLETED], true)) {
            return;
        }

        $alreadyConsumed = InventoryMovement::where('reference_type', RoastingRequest::class)
            ->where('reference_id', $roastingRequest->id)
            ->where('reason', InventoryMovement::REASON_ROASTING_USAGE)
            ->exists();

        if ($alreadyConsumed) {
            return;
        }

        $product = Product::where('branch_id', $roastingRequest->branch_id)
            ->lockForUpdate()
            ->findOrFail($roastingRequest->product_id);

        $previousQuantity = (float) $product->quantity;
        $quantity = (float) $roastingRequest->quantity;

        if ((float) $product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية المطلوبة للتحميص أكبر من الكمية المتاحة في المخزون.',
            ]);
        }

        $product->quantity = (float) $product->quantity - $quantity;
        $product->save();

        app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

        InventoryMovement::create([
            'product_id' => $product->id,
            'branch_id' => $product->branch_id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'quantity' => $quantity,
            'reason' => InventoryMovement::REASON_ROASTING_USAGE,
            'reference_type' => RoastingRequest::class,
            'reference_id' => $roastingRequest->id,
            'performed_by' => Auth::id(),
            'notes' => 'تم خصم الكمية للمباشرة في التحميص.',
        ]);
    }
}
